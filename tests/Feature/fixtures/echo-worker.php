<?php

declare(strict_types=1);

/**
 * A well-behaved supervised worker for the supervisor tests.
 *
 * Prints one chatter line on startup, then drains the queue forever, replying
 * and emitting the DONE protocol line for every message it pops.
 */

require __DIR__ . '/../../../vendor/autoload.php';

use Strukt\Queue;

$prefix = 'example';
$queueName = 'emails';
$supervised = false;

foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--prefix=')) {
        $prefix = substr($argument, 9);
    } elseif (str_starts_with($argument, '--queue=')) {
        $queueName = substr($argument, 8);
    } elseif ($argument === '--supervised') {
        $supervised = true;
    }
}

$queue = new Queue(['prefix' => $prefix]);

echo "hello from worker\n";

while (true) {
    while (($job = $queue->pop($queueName, $id)) !== false) {
        if ($supervised) {
            printf("CLAIM %d %s\n", $id, base64_encode(serialize($job)));
        }

        $queue->reply($queueName, $id, ['sent' => true, 'at' => date('c')]);

        if ($supervised) {
            fwrite(STDOUT, sprintf("DONE %d\n", $id));
        }
    }

    usleep(100000);
}