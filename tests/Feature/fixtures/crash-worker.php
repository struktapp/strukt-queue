<?php

declare(strict_types=1);

/**
 * A crash-prone supervised worker for the supervisor tests.
 *
 * Watches the queue until a message arrives, claims it by emitting the CLAIM
 * protocol line, then dies without replying -- simulating a worker that crashes
 * mid-job. A supervisor watching the protocol must return the claimed payload
 * to the queue.
 */

require __DIR__ . '/../../../vendor/autoload.php';

use Strukt\Queue;

$prefix = 'example';
$queueName = 'emails';

foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--prefix=')) {
        $prefix = substr($argument, 9);
    } elseif (str_starts_with($argument, '--queue=')) {
        $queueName = substr($argument, 8);
    }
}

$queue = new Queue(['prefix' => $prefix]);

while (true) {
    if (($job = $queue->pop($queueName, $id)) !== false) {
        printf("CLAIM %d %s\n", $id, base64_encode(serialize($job)));
        exit(1);
    }

    usleep(50000);
}