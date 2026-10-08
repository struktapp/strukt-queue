<?php

declare(strict_types=1);

namespace Strukt\Queue\Example;

/**
 * Email payload carried through the queue.
 *
 * A value object so the producer and the worker agree on one shape; the queue
 * stores it as a plain array (anything the memcached serializer can carry).
 */
final class Mail
{
    /**
     * @param string $to Recipient address.
     * @param string $subject Email subject.
     * @param string $body Email body.
     */
    public function __construct(
        private readonly string $to,
        private readonly string $subject,
        private readonly string $body,
    ) {
    }

    /**
     * Recipient address.
     */
    public function to(): string
    {
        return $this->to;
    }

    /**
     * Email subject.
     */
    public function subject(): string
    {
        return $this->subject;
    }

    /**
     * Email body.
     */
    public function body(): string
    {
        return $this->body;
    }

    /**
     * Serialized form the queue stores.
     *
     * @return array{to: string, subject: string, body: string}
     */
    public function toArray(): array
    {
        return [
            'to'      => $this->to,
            'subject' => $this->subject,
            'body'    => $this->body,
        ];
    }

    /**
     * Rebuilds a mail from the array the queue handed back to a worker.
     *
     * @param array<string, mixed> $data Payload from {@see self::toArray()}.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) $data['to'],
            (string) $data['subject'],
            (string) $data['body'],
        );
    }
}