<?php

declare(strict_types=1);

/**
 * Non-stop queue activity generator.
 *
 * Keeps a rotating window of queues alive under a prefix so you can watch
 * `kqueue queues` (in another shell) while queues continuously appear and
 * disappear, e.g.:
 *
 *   terminal 1:  while true; do kqueue queues --prefix demo --json; sleep 1; done
 *   terminal 2:  php example/generate.php --prefix demo --pace 2 --window 5
 *
 * Options:
 *
 *   --host    Memcached host (default 127.0.0.1)
 *   --port    Memcached port (default 11211)
 *   --prefix  Key namespace (default queue)
 *   --base    Queue name prefix (default gen)
 *   --window  How many queues stay alive at once (default 8)
 *   --pace    Seconds per generation step (default 1.0)
 *   --ttl     Message lifetime in seconds (default 0 = never expires)
 *   --steps   Stop after this many steps (useful for scripts; default never)
 *   --verbose Print each generation step
 *
 * Runs until interrupted with Ctrl-C unless --steps is given.
 */

require __DIR__ . '/../vendor/autoload.php';

use Strukt\Queue\Example\Generator;

$flags = getopt('', [
    'host:',
    'port:',
    'prefix:',
    'base:',
    'window:',
    'pace:',
    'ttl:',
    'steps:',
    'verbose',
]);

$options = [
    'host' => $flags['host'] ?? '127.0.0.1',
    'port' => $flags['port'] ?? 11211,
    'prefix' => $flags['prefix'] ?? 'queue',
    'base' => $flags['base'] ?? 'gen',
    'window' => (int) ($flags['window'] ?? 8),
    'pace' => (float) ($flags['pace'] ?? 1.0),
    'ttl' => (int) ($flags['ttl'] ?? 0),
    'verbose' => isset($flags['verbose']),
];

$steps = isset($flags['steps']) ? (int) $flags['steps'] : null;

exit((new Generator($options))->run($steps));