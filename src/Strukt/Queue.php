<?php

declare(strict_types=1);

namespace Strukt;

/**
 * Memcached-backed FIFO message queue.
 *
 * The legacy package hoped that two counters plus one key per message could
 * stay in sync by luck. This rebuild keeps the storage shape (a `tail` counter
 * issues message ids, a `head` counter tracks consumption, the message lives
 * under `<queue>:<id>`, its optional reply under `<queue>:<id>:reply`) and
 * then makes every step that used to race or silently fail explicit:
 *
 * - The counters are initialized with `add()` and only then incremented, so
 *   concurrent first pushes issue unique ids instead of reusing id 1.
 * - `pop()` claims a slot with memcached's CAS (compare-and-swap) after
 *   re-reading the tail, so two workers never consume the same message and can
 *   never run the head past the tail.
 * - A slot whose item is missing (expired or an interrupted push) is skipped
 *   over, never misread as the queue being empty, and later live messages are
 *   still delivered.
 * - Counters never expire; only the message and reply values carry the ttl, so
 *   an expiring counter can no longer reset the queue under a live producer.
 * - The constructor throws instead of `die()`; storage failures throw so a
 *   failed push is visible instead of silently dropping the message.
 * - Every key is namespaced under a configurable prefix.
 *
 * Deliveries are at-most-once: `pop()` advances the head before returning, so
 * a crash after popping does not redeliver. Persisting jobs durably is your
 * job, not memcached's.
 */
final class Queue
{
    private const INDEX = '__queues';

    private const PREFIXES = '__queue_prefixes';

    private \Memcached $memcached;

    private string $prefix;

    private int $ttl;

    private string $host;

    private int $port;

    private bool $sasl;

    /**
     * @param array<string, mixed> $options Optional overrides: host, port, ttl,
     *        username, password, prefix. Any value falls back to the loaded
     *        `memcached_*` environment values, then to the defaults
     *        (127.0.0.1:11211, no expiry, `queue` prefix).
     */
    public function __construct(array $options = [])
    {
        if (!class_exists(\Memcached::class)) {
            throw new \RuntimeException('Unable to build the queue [the Memcached extension is not installed].');
        }

        $this->host = (string) $this->pick($options, 'host', 'memcached_host', '127.0.0.1');
        $this->port = (int) $this->pick($options, 'port', 'memcached_port', 11211);
        $this->prefix = (string) ($options['prefix'] ?? 'queue');
        $this->ttl = (int) $this->pick($options, 'ttl', 'memcached_ttl', 0);

        $username = (string) $this->pick($options, 'username', 'memcached_username', '');
        $password = (string) $this->pick($options, 'password', 'memcached_password', '');
        $this->sasl = $username !== '' && $password !== '';

        $this->memcached = new \Memcached();

        if ($this->sasl) {
            $this->memcached->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
            $this->memcached->setSaslAuthData($username, $password);
        }

        $added = @$this->memcached->addServer($this->host, $this->port);

        if (!$added) {
            throw new \RuntimeException(sprintf(
                'Unable to connect to the queue [%s:%d].',
                $this->host,
                $this->port,
            ));
        }
    }

    /**
     * The underlying memcached client.
     */
    public function client(): \Memcached
    {
        return $this->memcached;
    }

    /**
     * The key prefix every queue name is namespaced under.
     */
    public function prefix(): string
    {
        return $this->prefix;
    }

    /**
     * Adds an element at the end of the queue.
     *
     * @param string $queue Name of the queue.
     * @param mixed $item Value to store (anything the memcached serializer can carry).
     * @param int|null $ttl Seconds the item stays available; null uses the queue default.
     * @return int The id of the element in the queue.
     * @throws \RuntimeException When the item cannot be stored.
     */
    public function push(string $queue, mixed $item, ?int $ttl = null): int
    {
        if ($this->ensure($queue)) {
            $this->register($queue);
        }

        $id = $this->memcached->increment($this->key($queue, 'tail'));

        if ($id === false) {
            throw new \RuntimeException(sprintf('Unable to issue a queue id [%s].', $queue));
        }

        $stored = $this->memcached->add($this->key($queue, (string) $id), $item, $ttl ?? $this->ttl);

        if (!$stored) {
            throw new \RuntimeException(sprintf('Unable to store the item [%s].', (string) $id));
        }

        return (int) $id;
    }

    /**
     * Pops an element from the beginning of the queue.
     *
     * @param string $queue Name of the queue.
     * @param int|null $id This variable receives the id of the consumed element.
     * @return mixed The stored value, or `FALSE` when the queue is empty.
     */
    public function pop(string $queue, ?int &$id = null): mixed
    {
        $claimed = $this->claim($queue);

        if ($claimed === null) {
            $id = 0;

            return false;
        }

        [$delivered, $value] = $this->rescue($queue, $claimed);

        if ($value === null) {
            $id = 0;

            return false;
        }

        $id = $delivered;

        return $value;
    }

    /**
     * Whether the queue is empty.
     *
     * @return bool `TRUE` when no consumed message remains ahead.
     */
    public function is_empty(string $queue, ?string $prefix = null): bool
    {
        return $this->tail($queue, $prefix) <= $this->head($queue, $prefix);
    }

    /**
     * Whether the message with a given id has been popped.
     *
     * @param string $queue Name of the queue.
     * @param int $id Message id.
     * @return bool True when the head has already passed the message.
     */
    public function is_processed(string $queue, int $id): bool
    {
        $head = $this->head($queue);

        return $head > 0 && $id <= $head;
    }

    /**
     * The number of messages currently ahead in the queue.
     */
    public function count(string $queue, ?string $prefix = null): int
    {
        return max(0, $this->tail($queue, $prefix) - $this->head($queue, $prefix));
    }

    /**
     * Sends back a reply for a consumed message.
     *
     * @param string $queue Name of the queue.
     * @param int $id Id of the message being replied to.
     * @param mixed $reply Reply to post.
     * @param int|null $ttl Seconds the reply stays available; null uses the queue default.
     * @return bool True when the reply was stored.
     */
    public function reply(string $queue, int $id, mixed $reply, ?int $ttl = null): bool
    {
        return $this->memcached->add(
            $this->key($queue, $id . ':reply'),
            $reply,
            $ttl ?? $this->ttl,
        );
    }

    /**
     * Fetches a reply for a message, removing it once read.
     *
     * @param string $queue Name of the queue.
     * @param int $id Id of the message the reply belongs to.
     * @return mixed The reply, or `null` when none has been posted yet.
     */
    public function getReply(string $queue, int $id): mixed
    {
        $key = $this->key($queue, $id . ':reply');
        $reply = $this->memcached->get($key);

        if ($this->memcached->getResultCode() === \Memcached::RES_NOTFOUND) {
            return null;
        }

        $this->memcached->delete($key);

        return $reply;
    }

    /**
     * Purges a queue entirely, counters included.
     *
     * Mainly for cleanup: deletes the head and tail counters and every message
     * and reply slot up to the current tail.
     *
     * @param string $queue Name of the queue.
     * @param string|null $prefix Namespace to purge under; `null` uses the
     *        client's own prefix.
     */
    public function flush(string $queue, ?string $prefix = null): void
    {
        $prefix ??= $this->prefix;
        $tail = $this->tail($queue, $prefix);

        for ($id = 1; $id <= $tail; $id++) {
            $this->memcached->delete($this->keyFor($prefix, $queue, (string) $id));
            $this->memcached->delete($this->keyFor($prefix, $queue, $id . ':reply'));
        }

        $this->memcached->delete($this->keyFor($prefix, $queue, 'head'));
        $this->memcached->delete($this->keyFor($prefix, $queue, 'tail'));
        $this->unregister($queue, $prefix);
    }

    /**
     * The queues registered under this prefix.
     *
     * Memcached cannot enumerate its own keys on this server, so every queue
     * created through `push()` is tracked in a small registry (`<prefix>:__queues`)
     * that `flush()` cleans up. Queues written directly against the raw client
     * are not listed.
     *
     * @return string[] Queue names under the prefix.
     */
    public function queues(): array
    {
        return $this->setMembers($this->keyRegistry());
    }

    /**
     * Every prefix that has at least one registered queue on this server.
     *
     * A server-wide roster (`__queue_prefixes`) records every prefix a queue
     * was ever registered under, so a queueing app can discover what its Shared
     * memcached holds. The roster is cleaned up when the last queue of a
     * prefix is flushed.
     *
     * @return string[] Active queue prefixes.
     */
    public function prefixes(): array
    {
        return $this->setMembers($this->keyRoster());
    }

    /**
     * Every registered queue on the server, grouped by prefix.
     *
     * @return array<string, string[]> Prefix => queue names.
     */
    public function all(): array
    {
        $all = [];

        foreach ($this->prefixes() as $prefix) {
            $key = $prefix . ':' . self::INDEX;
            $names = $this->setMembers($key);

            if ($names !== []) {
                $all[$prefix] = $names;
            }
        }

        return $all;
    }

    /**
     * Purges every registered queue, under this prefix or across the whole
     * server.
     *
     * @param string|null $prefix Limit the purge to one prefix; `null` clears
     *        every queue on the server.
     * @return string[] The `prefix/queue` pairs that were flushed.
     */
    public function flushAll(?string $prefix = null): array
    {
        $targets = $prefix !== null
            ? [$prefix => $prefix . ':' . self::INDEX]
            : array_combine(
                $this->prefixes(),
                array_map(fn (string $p) => $p . ':' . self::INDEX, $this->prefixes()),
            );

        $flushed = [];

        foreach ($targets as $targetPrefix => $registryKey) {
            $names = $this->setMembers($registryKey);

            foreach ($names as $name) {
                $this->flush($name, $targetPrefix);
                $flushed[] = $targetPrefix . '/' . $name;
            }
        }

        return $flushed;
    }

    /**
     * Creates the head and tail counters if they do not exist yet.
     *
     * @return bool True when the queue was just created.
     */
    private function ensure(string $queue): bool
    {
        $head = $this->memcached->add($this->key($queue, 'head'), 0, 0);
        $tail = $this->memcached->add($this->key($queue, 'tail'), 0, 0);

        return $head || $tail;
    }

    /**
     * Claims the next slot for the queue with a CAS-guarded head advance.
     *
     * @return int|null The claimed slot, or `null` when the queue is empty.
     */
    private function claim(string $queue): ?int
    {
        while (true) {
            $tail = $this->tail($queue);
            $headKey = $this->key($queue, 'head');
            $snapshot = $this->memcached->get($headKey, null, \Memcached::GET_EXTENDED);

            if ($snapshot === false || $this->memcached->getResultCode() !== \Memcached::RES_SUCCESS) {
                return null;
            }

            $head = (int) $snapshot['value'];

            if ($head >= $tail) {
                return null;
            }

            if ($this->memcached->cas($snapshot['cas'], $headKey, $head + 1, 0)) {
                return $head + 1;
            }
        }
    }

    /**
     * Reads a claimed slot, waiting briefly for an in-flight push and skipping
     * permanently dead slots in favour of the next live message.
     *
     * @return array{0: int, 1: mixed} The delivered slot id and its value, or
     *        the claimed slot with a `null` value when nothing live remains.
     */
    private function rescue(string $queue, int $claimed): array
    {
        $id = $claimed;

        for ($i = 0; $i < 20; $i++) {
            $key = $this->key($queue, (string) $id);
            $value = $this->memcached->get($key);

            if ($this->memcached->getResultCode() === \Memcached::RES_SUCCESS) {
                $this->memcached->delete($key);

                return [$id, $value];
            }

            usleep(1000);
        }

        $tail = $this->tail($queue);
        $headKey = $this->key($queue, 'head');

        for ($id = $claimed + 1; $id <= $tail; $id++) {
            $key = $this->key($queue, (string) $id);
            $item = $this->memcached->get($key);

            if ($this->memcached->getResultCode() !== \Memcached::RES_SUCCESS) {
                continue;
            }

            // Read the head with its own CAS token; skip when another consumer
            // already swallowed past the survivor.
            $head = $this->memcached->get($headKey, null, \Memcached::GET_EXTENDED);

            if ($head === false || $this->memcached->getResultCode() !== \Memcached::RES_SUCCESS) {
                return null;
            }

            if ((int) $head['value'] >= $id) {
                return [$claimed, null];
            }

            // Fold the dead scan into the head so the survivor is only ever
            // delivered once; if the CAS loses, another worker is delivering it.
            if (!$this->memcached->cas($head['cas'], $headKey, $id, 0)) {
                return [$claimed, null];
            }

            $this->memcached->delete($key);

            return [$id, $item];
        }

        return [$claimed, null];
    }

    /**
     * The current tail (highest issued id), 0 when the queue is untouched.
     */
    private function tail(string $queue, ?string $prefix = null): int
    {
        return (int) $this->memcached->get($this->keyFor($prefix ?? $this->prefix, $queue, 'tail'));
    }

    /**
     * The current head (last consumed slot), 0 when nothing was consumed.
     */
    private function head(string $queue, ?string $prefix = null): int
    {
        return (int) $this->memcached->get($this->keyFor($prefix ?? $this->prefix, $queue, 'head'));
    }

    /**
     * Namespaced storage key for a queue part under this client's prefix.
     */
    private function key(string $queue, string $part): string
    {
        return $this->keyFor($this->prefix, $queue, $part);
    }

    /**
     * Namespaced storage key for a queue part under an explicit prefix.
     */
    private function keyFor(string $prefix, string $queue, string $part): string
    {
        return $prefix . ':' . $queue . ':' . $part;
    }

    /**
     * Key of the registry that tracks this prefix's queues.
     */
    private function keyRegistry(): string
    {
        return $this->prefix . ':' . self::INDEX;
    }

    /**
     * Server-wide roster of every prefix that has registered queues.
     */
    private function keyRoster(): string
    {
        return self::PREFIXES;
    }

    /**
     * Registers a queue under its prefix, and the prefix in the server roster,
     * unless both are already known.
     *
     * Both collections are read-modify-written under CAS, so concurrent first
     * pushes never lose a queue or a prefix.
     */
    private function register(string $queue): void
    {
        $this->setAdd($this->keyRegistry(), $queue);
        $this->setAdd($this->keyRoster(), $this->prefix);
    }

    /**
     * Removes a queue from its prefix registry, and the prefix from the server
     * roster once its registry is empty.
     */
    private function unregister(string $queue, ?string $prefix = null): void
    {
        $prefix ??= $this->prefix;
        $key = $prefix . ':' . self::INDEX;

        $this->setRemove($key, $queue);

        if ($this->setMembers($key) === []) {
            $this->setRemove($this->keyRoster(), $prefix);
        }
    }

    /**
     * Reads a string collection stored under an arbitrary key.
     *
     * @return string[] Sorted members, `[]` when the key is missing.
     */
    private function setMembers(string $key): array
    {
        $members = $this->memcached->get($key);

        if ($this->memcached->getResultCode() !== \Memcached::RES_SUCCESS) {
            return [];
        }

        $members = is_array($members) ? $members : [];

        sort($members);

        return $members;
    }

    /**
     * Adds a member to a string collection, creating the key when missing.
     */
    private function setAdd(string $key, string $member): void
    {
        while (true) {
            $snapshot = $this->memcached->get($key, null, \Memcached::GET_EXTENDED);
            $members = [];

            if ($snapshot !== false && $this->memcached->getResultCode() === \Memcached::RES_SUCCESS) {
                $members = (array) $snapshot['value'];
            }

            if (in_array($member, $members, true)) {
                return;
            }

            $members[] = $member;

            if ($snapshot === false || $this->memcached->getResultCode() !== \Memcached::RES_SUCCESS) {
                if ($this->memcached->add($key, $members, 0)) {
                    return;
                }

                continue;
            }

            if ($this->memcached->cas($snapshot['cas'], $key, $members, 0)) {
                return;
            }
        }
    }

    /**
     * Removes a member from a string collection, deleting the key when empty.
     */
    private function setRemove(string $key, string $member): void
    {
        while (true) {
            $snapshot = $this->memcached->get($key, null, \Memcached::GET_EXTENDED);

            if ($snapshot === false || $this->memcached->getResultCode() !== \Memcached::RES_SUCCESS) {
                return;
            }

            $members = (array) $snapshot['value'];

            foreach (array_keys($members, $member, true) as $index) {
                unset($members[$index]);
            }

            $members = array_values($members);

            if (empty($members)) {
                if ($this->memcached->delete($key)) {
                    return;
                }

                continue;
            }

            if ($this->memcached->cas($snapshot['cas'], $key, $members, 0)) {
                return;
            }
        }
    }

    /**
     * Resolves an option, falling back to an environment value and a default.
     */
    private function pick(array $options, string $key, string $envKey, string|int $default): string|int
    {
        if (array_key_exists($key, $options)) {
            return $options[$key];
        }

        if (class_exists(Env::class) && Env::has($envKey)) {
            return Env::get($envKey);
        }

        return $default;
    }
}