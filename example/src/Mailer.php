<?php

declare(strict_types=1);

namespace Strukt\Queue\Example;

/**
 * Dummy mailer.
 *
 * Pretends to deliver the email and reports whether it would have been
 * accepted. In real life this calls an SMTP client, an API-based provider, or
 * whatever the app uses; the queue does not care what the worker does with the
 * payload.
 */
final class Mailer
{
    /**
     * Attempts delivery of a mail.
     *
     * @param Mail $mail Email to send.
     * @return bool True when the email was accepted for delivery.
     */
    public function send(Mail $mail): bool
    {
        if (!filter_var($mail->to(), FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        echo sprintf(
            "   [mailer] dispatching '%s' to %s\n",
            $mail->subject(),
            $mail->to(),
        );

        return true;
    }
}