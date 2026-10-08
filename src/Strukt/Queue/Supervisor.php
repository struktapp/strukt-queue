<?php

declare(strict_types=1);

namespace Strukt\Queue;

use Strukt\Process;
use Strukt\ProcessSupervisor;
use Strukt\Queue as QueueClient;

/**
 * Queue-aware process supervisor with crash redelivery.
 *
 * Compose a {@see ProcessSupervisor} with the queue so a pool of worker
 * processes can be kept alive *and* a message a crashed worker claimed but
 * never finished is returned to the queue. To know what the worker claimed,
 * the worker reports its progress on standard output:
 *
 *     CLAIM <id> <base64-encoded payload>
 *     DONE  <id>
 *
 * The supervisor reads these protocol lines during {@see self::drain()}, tracks
 * the in-flight payloads per worker slot, and on a worker exit hands every
 * claim that was never followed by `DONE` back to {@see Queue::push()}. Ordinary
 * worker chatter is relayed to the drain callback unchanged.
 *
 * Redelivery is best-effort *at least once*: a message is re-pushed under a
 * fresh id, so a producer waiting on the original id sees its reply key never
 * arrive. Deliveries therefore favour not losing work over strict ordering.
 */
final class Supervisor
{
    private ProcessSupervisor $processes;

    /** @var array<int, array<int, mixed>> Slot => claimed message id => payload. */
    private array $inFlight = [];

    private int $redelivered = 0;

    /** @var callable(int, int): void|null Extra hook fired when a worker exits. */
    private $onExit = null;

    private string $fallbackQueue;

    public function __construct(
        int $workers,
        private readonly QueueClient $queue,
        string $queueName = 'emails',
        int $restartCap = -1,
        float $backoff = 0.0,
    ) {
        $this->fallbackQueue = $queueName;
        $this->processes = new ProcessSupervisor($workers, $restartCap, $backoff);
        $this->processes->onExit(function (int $slot, int $exitCode): void {
            $this->returnClaims($slot);

            if ($this->onExit !== null) {
                ($this->onExit)($slot, $exitCode);
            }
        });
    }

    /**
     * Spawns the worker processes.
     *
     * Every worker receives the queue and prefix this supervisor supervises, so
     * a caller never has to repeat them; extra flags (e.g. `--interval=150`)
     * are passed through untouched.
     *
     * @param string $workerScript Path to the worker script.
     * @param list<string> $arguments Extra flags for the script.
     */
    public function start(string $workerScript, array $arguments = []): void
    {
        $this->processes->start(
            array_merge([
                PHP_BINARY,
                $workerScript,
                sprintf('--queue=%s', $this->fallbackQueue),
                sprintf('--prefix=%s', $this->queue->prefix()),
            ], $arguments),
            dirname($workerScript),
        );
    }

    /**
     * Pumps the pool: relays worker output, restarts dead workers, and returns
     * the payloads they claimed but never finished to the queue.
     *
     * @param callable|null $relay Callback receiving (int $slot, string $line)
     *        for ordinary worker output; defaults to echoing to stdout.
     */
    public function drain(?callable $relay = null): void
    {
        $relay ??= static function (int $slot, string $line): void {
            echo $line;
        };

        $this->processes->supervise(function (int $slot, string $line) use ($relay): void {
            if ($this->consumeProtocol($slot, $line)) {
                return;
            }

            $relay($slot, $line);
        });
    }

    /**
     * Terminates every worker and reaps its process.
     *
     * @return array<int, int> Exit codes keyed by slot index.
     */
    public function stop(): array
    {
        return $this->processes->stop();
    }

    /**
     * How many messages were returned to the queue after worker exits.
     */
    public function redelivered(): int
    {
        return $this->redelivered;
    }

    /**
     * How many messages are currently claimed but not yet finished.
     */
    public function inFlight(): int
    {
        $count = 0;

        foreach ($this->inFlight as $claims) {
            $count += count($claims);
        }

        return $count;
    }

    /**
     * The queue name the redelivery returns messages to.
     */
    public function fallbackQueue(): string
    {
        return $this->fallbackQueue;
    }

    /**
     * The underlying process supervisor.
     */
    public function processes(): ProcessSupervisor
    {
        return $this->processes;
    }

    public function count(): int
    {
        return $this->processes->count();
    }

    public function running(): bool
    {
        return $this->processes->running();
    }

    /**
     * @return list<Process>
     */
    public function workers(): array
    {
        return $this->processes->processes();
    }

    public function worker(int $slot): Process
    {
        return $this->processes->process($slot);
    }

    public function restarts(int $slot): int
    {
        return $this->processes->restarts($slot);
    }

    /**
     * Parses one worker protocol line; returns true when it was consumed.
     */
    private function consumeProtocol(int $slot, string $line): bool
    {
        $line = rtrim($line, "\r\n");

        if (preg_match('/^CLAIM (\d+) (.+)$/i', $line, $matches) === 1) {
            $payload = @unserialize(base64_decode($matches[2], true));

            if ($payload !== false) {
                $this->inFlight[$slot][(int) $matches[1]] = $payload;
            }

            return true;
        }

        if (preg_match('/^DONE (\d+)$/i', $line, $matches) === 1) {
            unset($this->inFlight[$slot][(int) $matches[1]]);

            return true;
        }

        return false;
    }

    /**
     * Returns the un-finished claims of a dead worker slot to the queue.
     */
    private function returnClaims(int $slot): void
    {
        $claims = $this->inFlight[$slot] ?? [];
        unset($this->inFlight[$slot]);

        foreach ($claims as $payload) {
            if ($payload === false) {
                continue;
            }

            $this->queue->push($this->fallbackQueue, $payload);
            $this->redelivered++;
        }
    }

    /**
     * Registers an extra hook fired when a worker exits, after the queue's own
     * crash redelivery has run.
     *
     * @param callable(int, int): void $callback Receives (int $slot, int $exitCode).
     */
    public function onExit(callable $callback): void
    {
        $this->onExit = $callback;
    }
}