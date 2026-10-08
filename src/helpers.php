<?php

declare(strict_types=1);

/**
 * Global queue helpers.
 *
 * Mirrors the legacy package: `helper('queue')` selects the "queue" package
 * inside the shared Strukt base registry, and every `helper_add()` guard
 * registers the corresponding helper name while keeping the global functions
 * from being declared twice when multiple packages ship one of them.
 *
 * @see Strukt\Queue
 */

use Strukt\Queue;

helper('queue');

if (helper_add('qpush')) {
    /**
     * Globally pushes an element onto a queue.
     *
     * @param string $name Queue name.
     * @param mixed $item Element to store.
     * @param array<string, mixed> $options Memcached options (host, port, ttl, prefix, ...).
     * @return int Id of the queued element.
     */
    function qpush(string $name, mixed $item, array $options = []): int
    {
        return (new Queue($options))->push($name, $item);
    }
}

if (helper_add('qpop')) {
    /**
     * Globally pops an element off a queue.
     *
     * @param string $name Queue name.
     * @param int|null $id This variable receives the id of the consumed element.
     * @param array<string, mixed> $options Memcached options (host, port, ttl, prefix, ...).
     * @return mixed The element, or `FALSE` when the queue is empty.
     */
    function qpop(string $name, ?int &$id = null, array $options = []): mixed
    {
        return (new Queue($options))->pop($name, $id);
    }
}

if (helper_add('qreply')) {
    /**
     * Globally posts a reply for a consumed message.
     *
     * @param string $name Queue name.
     * @param int $id Id of the message being replied to.
     * @param mixed $reply Reply to post.
     * @param array<string, mixed> $options Memcached options (host, port, ttl, prefix, ...).
     * @return bool True when the reply was stored.
     */
    function qreply(string $name, int $id, mixed $reply, array $options = []): bool
    {
        return (new Queue($options))->reply($name, $id, $reply);
    }
}

if (helper_add('qwait')) {
    /**
     * Globally waits for a message to be popped and replied to.
     *
     * Polls the queue until the message is processed, then returns its reply.
     * Unlike the legacy busy-loop this is bounded: it gives up after $seconds
     * and returns `null`.
     *
     * @param string $name Queue name.
     * @param int $id Id of the message to wait on.
     * @param int $seconds How long to wait before giving up.
     * @param array<string, mixed> $options Memcached options (host, port, ttl, prefix, ...).
     * @return mixed The reply, `null` when the message was never processed.
     */
    function qwait(string $name, int $id, int $seconds = 10, array $options = []): mixed
    {
        $queue = new Queue($options);
        $deadline = microtime(true) + $seconds;

        do {
            if ($queue->is_processed($name, $id)) {
                return $queue->getReply($name, $id);
            }

            usleep(200000);
        } while (microtime(true) < $deadline);

        return null;
    }
}

if (helper_add('qempty')) {
    /**
     * Globally checks whether a queue is empty.
     *
     * @param string $name Queue name.
     * @param array<string, mixed> $options Memcached options (host, port, ttl, prefix, ...).
     * @return bool True when the queue is empty.
     */
    function qempty(string $name, array $options = []): bool
    {
        return (new Queue($options))->is_empty($name);
    }
}

if (helper_add('qprocessed')) {
    /**
     * Globally checks whether a message has been popped.
     *
     * @param string $name Queue name.
     * @param int $id Message id.
     * @param array<string, mixed> $options Memcached options (host, port, ttl, prefix, ...).
     * @return bool True when the message is processed.
     */
    function qprocessed(string $name, int $id, array $options = []): bool
    {
        return (new Queue($options))->is_processed($name, $id);
    }
}