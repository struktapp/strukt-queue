<?php

declare(strict_types=1);

namespace Strukt\Queue\Example;

use Strukt\Queue;

/**
 * Consumer side of the email pipeline.
 *
 * Drains the queue with {@see Queue::pop()}, hands each mail to the mailer,
 * and posts the send outcome back with {@see Queue::reply()}. Because pop() is
 * CAS-guarded, any number of workers may run concurrently without the same
 * mail reaching two of them.
 */
final class Worker
{
    /**
     * @param Queue $queue Shared queue client.
     * @param Mailer $mailer The mail transport.
     * @param string $queueName Queue to consume.
     * @param bool $supervised Whether to emit the CLAIM/DONE protocol lines a
     *        queue supervisor parses for crash redelivery.
     */
    public function __construct(
        private readonly Queue $queue,
        private readonly Mailer $mailer,
        private readonly string $queueName = 'emails',
        private readonly bool $supervised = false,
    ) {
    }

    /**
     * Polls and processes the queue until it drains.
     *
     * @return int Number of messages handled.
     */
    public function run(): int
    {
        $handled = 0;

        while (($job = $this->queue->pop($this->queueName, $id)) !== false) {
            if ($this->supervised) {
                printf("CLAIM %d %s\n", $id, base64_encode(serialize($job)));
            }

            echo sprintf("          handling message %d\n", $id);

            $sent = $this->mailer->send(Mail::fromArray($job));
            $this->queue->reply(
                $this->queueName,
                $id,
                ['sent' => $sent, 'at' => date('c')],
            );

            if ($this->supervised) {
                fwrite(STDOUT, sprintf("DONE %d\n", $id));
            }

            $handled++;
        }

        return $handled;
    }

    /**
     * The queue name this worker consumes.
     */
    public function listensTo(): string
    {
        return $this->queueName;
    }

    /**
     * Runs as a long-lived worker process: drain the queue, rest for a moment,
     * drain again. Returns immediately when `$once` is set after a single pass.
     *
     * @param int $intervalMs How long to sleep between polls.
     * @param bool $once Whether to exit after the first drain.
     */
    public function serve(int $intervalMs = 200, bool $once = false): void
    {
        do {
            $this->run();

            if ($once) {
                return;
            }

            usleep($intervalMs * 1000);
        } while (true);
    }
}