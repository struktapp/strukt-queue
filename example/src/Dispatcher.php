<?php

declare(strict_types=1);

namespace Strukt\Queue\Example;

use Strukt\Queue;

/**
 * Producer side of the email pipeline.
 *
 * Pushes a mail onto the queue and, later, waits for the worker to post its
 * reply. The wait is bounded so a caller never hangs on a lost message.
 */
final class Dispatcher
{
    /**
     * @param Queue $queue Shared queue client.
     * @param string $queueName Queue to push onto.
     */
    public function __construct(
        private readonly Queue $queue,
        private readonly string $queueName = 'emails',
    ) {
    }

    /**
     * Queues a mail for the worker.
     *
     * @return int Id handed back to the caller for {@see self::wait()}.
     */
    public function dispatch(Mail $mail): int
    {
        return $this->queue->push($this->queueName, $mail->toArray());
    }

    /**
     * Blocks until the worker has processed and answered the message.
     *
     * @param int $id Message id from {@see self::dispatch()}.
     * @param int $seconds How long to wait before giving up.
     * @return array<string, mixed>|null The worker's reply, or `null` on timeout.
     */
    public function wait(int $id, int $seconds = 5): ?array
    {
        return qwait($this->queueName, $id, $seconds, ['prefix' => $this->queue->prefix()]);
    }

    /**
     * Non-blocking reply check, for polling loops that want to do other work
     * (like relaying worker output) between checks.
     *
     * @param int $id Message id from {@see self::dispatch()}.
     * @return array<string, mixed>|null The worker's reply, or `null` while it
     *        is still unprocessed or unanswered.
     */
    public function peek(int $id): ?array
    {
        if (!$this->queue->is_processed($this->queueName, $id)) {
            return null;
        }

        return $this->queue->getReply($this->queueName, $id);
    }
}