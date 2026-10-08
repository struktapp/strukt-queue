<?php

declare(strict_types=1);

namespace Strukt\Queue\Cli\Command;

use Strukt\Console\Runtime\CommandInterface;
use Strukt\Queue\Cli\Command;

/**
 * @command wait
 * @description Waits for a message to be popped, then prints its reply.
 * @usage wait QUEUE ID [options]
 *
 * @argument queue
 * @description Name of the queue.
 *
 * @argument id
 * @description Id of the message to wait on.
 *
 * @option seconds
 * @default 10
 * @description How long to wait before giving up.
 *
 * @option json
 * @flag
 * @description Render the result as JSON.
 *
 * @option host
 * @default 127.0.0.1
 * @description Memcached host.
 *
 * @option port
 * @default 11211
 * @description Memcached port.
 *
 * @option prefix
 * @default queue
 * @description Key namespace to isolate queues on a shared server.
 */
class WaitCommand extends Command implements CommandInterface
{
    public function execute(array $arguments, array $options): int
    {
        $queue = $this->queue($options);
        $id = (int) $arguments['id'];
        $seconds = (int) $options['seconds'];
        $deadline = microtime(true) + $seconds;

        do {
            if ($queue->is_processed($arguments['queue'], $id)) {
                $reply = $queue->getReply($arguments['queue'], $id);
                $this->respond(
                    'wait',
                    [
                        'queue' => $arguments['queue'],
                        'id' => $id,
                        'reply' => $reply,
                        'timeout' => false,
                    ],
                    (bool) $options['json'],
                );

                return 0;
            }

            usleep(200000);
        } while (microtime(true) < $deadline);

        $this->respond(
            'wait',
            [
                'queue' => $arguments['queue'],
                'id' => $id,
                'reply' => null,
                'timeout' => true,
            ],
            (bool) $options['json'],
        );

        return 1;
    }
}