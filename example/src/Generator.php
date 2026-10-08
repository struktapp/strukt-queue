<?php

declare(strict_types=1);

namespace Strukt\Queue\Example;

use Strukt\Queue;

/**
 * Non-stop activity generator for inspecting the live queue set.
 *
 * Keeps a rotating window of queues alive. Every "pace" a fresh queue is born
 * (a handful of messages are pushed onto it) and the queue that just fell out
 * of the window is flushed, so a concurrent `kqueue queues` — or plain
 * `$queue->queues()` — shows queues appearing and disappearing continuously.
 *
 * It is a testing tool, built for watching a prefix under load while you
 * develop against the listing and monitoring APIs. It is not a load tester and
 * makes no effort to produce realistic traffic; its only job is to keep a
 * moving set of queue names in front of you.
 */
final class Generator
{
    private Queue $queue;

    private string $base;

    private int $window;

    private float $pace;

    private int $ttl;

    private bool $verbose;

    private int $generation = 1;

    private int $seq = 1;

    private bool $running = true;

    /**
     * @param array<string, mixed> $options Optional overrides: host, port,
     *        prefix, base (queue name prefix), window (live queue count), pace
     *        (seconds between steps), ttl (message lifetime), verbose.
     */
    public function __construct(array $options = [])
    {
        $config = [];

        foreach (['host', 'port', 'prefix', 'ttl'] as $key) {
            if (isset($options[$key])) {
                $config[$key] = $options[$key];
            }
        }

        $this->queue = new Queue($config);

        $this->base = (string) ($options['base'] ?? 'gen');
        $this->window = (int) ($options['window'] ?? 8);
        $this->pace = (float) ($options['pace'] ?? 1.0);
        $this->ttl = (int) ($options['ttl'] ?? 0);
        $this->verbose = (bool) ($options['verbose'] ?? false);
    }

    /**
     * Runs until interrupted by SIGINT/SIGTERM, or for exactly $steps steps.
     *
     * When pcntl is unavailable the run never terminates on its own unless a
     * step limit is given.
     *
     * @param int|null $steps Bounded run for tests; `null` means forever.
     * @return int The number of generations produced (0 while stopping).
     */
    public function run(?int $steps = null): int
    {
        $this->reset();

        $done = 0;

        echo sprintf(
            "[generator] cycling %s-00001 .. %s-%05d under prefix '%s' (window %d, pace %ss)\n",
            $this->base,
            $this->base,
            $this->window,
            $this->queue->prefix(),
            $this->window,
            $this->pace,
        );

        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, fn () => $this->stop());
            pcntl_signal(SIGTERM, fn () => $this->stop());
        }

        while ($this->running && ($steps === null || $done < $steps)) {
            $this->step();
            $done++;
            usleep((int) ($this->pace * 1_000_000));
        }

        if ($this->verbose) {
            echo sprintf(
                "[generator] stopped after %d generations (%d messages)\n",
                $this->generation - 1,
                $this->seq - 1,
            );
        }

        return $done;
    }

    /**
     * Signals the run loop to finish after the current step.
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * Flushes every queue this generator's names under the active prefix, so a
     * re-run (or a crashed one) always starts from a clean slate.
     */
    public function reset(): void
    {
        foreach ($this->queue->queues() as $name) {
            if (str_starts_with($name, $this->base . '-')) {
                $this->queue->flush($name);
            }
        }

        $this->generation = 1;
        $this->seq = 1;
    }

    /**
     * One generation tick: born a queue, flush the one that left the window,
     * then advance.
     */
    private function step(): void
    {
        $born = $this->generation;
        $name = fn (int $generation) => sprintf('%s-%05d', $this->base, $generation);

        $messageCount = random_int(1, 3);

        for ($i = 0; $i < $messageCount; $i++) {
            $this->queue->push(
                $name($born),
                ['seq' => $this->seq++, 'at' => microtime(true)],
                $this->ttl,
            );
        }

        if ($this->verbose) {
            $from = max(1, $born - $this->window + 1);
            echo sprintf(
                "[generator] born %s (+%d)  flushed %s  live %s .. %s\n",
                $name($born),
                $messageCount,
                $born > $this->window ? $name($born - $this->window) : '-',
                $name($from),
                $name($born),
            );
        }

        $dies = $born - $this->window;

        if ($dies >= 1) {
            $this->queue->flush($name($dies));
        }

        $this->generation++;
    }
}