<?php

/** Pest configuration shared by the queue test suite. */

use PHPUnit\Framework\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

/**
 * Shared connection values for the test memcached.
 *
 * Everything runs against a local memcached on the default port; tests use a
 * namespaced key prefix unique to the process so they never collide with other
 * data on the server and leave nothing behind.
 */
function queue_options(): array
{
    return [
        'host' => '127.0.0.1',
        'port' => 11211,
        'prefix' => 'queue-test-' . bin2hex(random_bytes(4)),
        'ttl' => 30,
    ];
}

/**
 * A fresh Queue client for a unique queue name under the test prefix.
 */
function queue_client(): Strukt\Queue
{
    return new Strukt\Queue(queue_options());
}

/**
 * Builds a queue name unique to a single test.
 */
function queue_name(string $base): string
{
    return sprintf('t_%s_%s', bin2hex(random_bytes(3)), $base);
}

/**
 * Whether process forking is available for the concurrency tests.
 */
function pcntl_available(): bool
{
    return function_exists('pcntl_fork');
}

/**
 * Forks $workers child processes, releases the barrier file so they all start
 * at the same instant, then waits for every child.
 *
 * @param string $release Barrier file the children spin on; it must not exist
 *        yet and is created by this function once every child is forked.
 * @param callable(int):void $job Child workload, receives the worker index.
 */
function spawn(int $workers, string $release, callable $job): void
{
    $pids = [];

    for ($i = 0; $i < $workers; $i++) {
        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('Unable to fork worker process.');
        }

        if ($pid === 0) {
            $job($i);
            exit(0);
        }

        $pids[] = $pid;
    }

    touch($release);

    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }
}

/**
 * Creates a barrier file every child spins on until the parent releases it, so
 * fork bombs start their work at the same instant.
 */
function barrier(string $file): void
{
    while (!is_file($file)) {
        usleep(5000);
    }
}