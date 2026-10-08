<?php

declare(strict_types=1);

/**
 * Queued email delivery with a pool of worker processes.
 *
 * A message is never sent inline; it is pushed onto a queue and a *worker
 * process* pops it, hands it to the (dummy) mailer, and posts the outcome back
 * as a reply. This script:
 *
 *   1. starts a supervisor that spawns several `worker.php` processes,
 *   2. dispatches a batch of emails,
 *   3. polls each message until its reply arrives (relaying worker chatter),
 *   4. stops the workers and prints the outcomes.
 *
 * Any number of workers may run against the same queue: pop() is CAS-guarded,
 * so each email reaches exactly one worker. To process a single batch inline
 * instead, run `php example/worker.php --once`.
 *
 * The classes behind this wiring isolate the concerns:
 * {@see Strukt\Queue\Example\Dispatcher} pushes and waits,
 * {@see Strukt\Queue\Example\Worker} pops and hands off,
 * {@see Strukt\Queue\Example\Mailer} "sends", and
 * {@see Strukt\Queue\Supervisor} supervises the pool, restarting workers and
 * returning to the queue any message a dead worker claimed but never finished.
 *
 * Requires memcached on 127.0.0.1:11211 and the pcntl-free proc_open API.
 *
 * Run with: php example/email.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Strukt\Queue;
use Strukt\Queue\Example\Dispatcher;
use Strukt\Queue\Example\Mail;
use Strukt\Queue\Supervisor;

$queue = new Queue(['prefix' => 'example']);

// Clean slate so re-runs always start at id 1.
$queue->flush('emails');

$dispatcher = new Dispatcher($queue);

// --- workers ----------------------------------------------------------------

$supervisor = new Supervisor(3, $queue, 'emails');
$supervisor->start(__DIR__ . '/worker.php', ['--interval=150', '--supervised']);

// --- producer ---------------------------------------------------------------

$ids = [];

echo "producer: queueing emails\n";
foreach (mails() as $mail) {
    $ids[] = $dispatcher->dispatch($mail);
    echo sprintf("          queued '%s' as message %d\n", $mail->subject(), end($ids));
}

// --- caller -----------------------------------------------------------------

echo "caller:   polling for replies\n";

$pending = $ids;
$replies = [];
$deadline = microtime(true) + 8;

while ($pending !== [] && microtime(true) < $deadline) {
    $supervisor->drain();

    foreach ($pending as $index => $id) {
        $reply = $dispatcher->peek($id);

        if ($reply !== null) {
            $replies[$id] = $reply;
            unset($pending[$index]);
        }
    }

    usleep(200000);
}

$supervisor->stop();

foreach ($ids as $id) {
    if (!isset($replies[$id])) {
        echo sprintf("          message %d: no reply within the timeout\n", $id);
        exit(1);
    }

    $status = $replies[$id]['sent'] ? 'SENT' : 'REJECTED';
    echo sprintf("          message %d: %s (replied %s)\n", $id, $status, $replies[$id]['at']);
}

echo "\ndone.\n";

/**
 * Sample mail for the demo.
 *
 * @return list<Mail> Mixed batch, some the mailer accepts and some it rejects.
 */
function mails(): array
{
    return [
        new Mail('alice@example.com', 'Invoice 1042 attached', 'Please find the invoice enclosed.'),
        new Mail('bob@example.com', 'Your quarterly report is ready', 'Hello Bob, the numbers are attached. — Finance'),
        new Mail('carol@example.com', 'Password reset requested', 'Use the link below to set a new password.'),
        new Mail('broken-address', 'This one will be rejected', 'The mailer refuses addresses without a real domain.'),
        new Mail('dave@example.com', 'Welcome aboard', 'Thanks for joining the platform.'),
    ];
}