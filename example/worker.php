<?php

declare(strict_types=1);

/**
 * A long-lived email worker process.
 *
 * Drains the queue handed off by example/email.php, replies to each message,
 * then sleeps briefly and polls again. Spawn several of these concurrently; the
 * CAS-guarded pop() guarantees no email is handled twice.
 *
 * Options:
 *   --prefix=<string>   queue prefix (default: example)
 *   --queue=<string>    queue to consume (default: emails)
 *   --interval=<ms>     pause between polls (default: 200)
 *   --once              process one batch, then exit
 *   --supervised        emit the CLAIM/DONE protocol lines a supervisor parses
 *
 * Run with: php example/worker.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Strukt\Queue;
use Strukt\Queue\Example\Mailer;
use Strukt\Queue\Example\Worker;

$prefix = 'example';
$queueName = 'emails';
$interval = 200;
$once = false;
$supervised = false;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--once') {
        $once = true;
    } elseif ($argument === '--supervised') {
        $supervised = true;
    } elseif (str_starts_with($argument, '--prefix=')) {
        $prefix = substr($argument, 9);
    } elseif (str_starts_with($argument, '--queue=')) {
        $queueName = substr($argument, 8);
    } elseif (str_starts_with($argument, '--interval=')) {
        $interval = (int) substr($argument, 11);
    }
}

$worker = new Worker(new Queue(['prefix' => $prefix]), new Mailer(), $queueName, $supervised);

printf("worker %d: watching queue '%s'\n", getmypid(), $worker->listensTo());
$worker->serve($interval, $once);