<?php

declare(strict_types=1);

namespace Strukt\Queue\Cli\Command;

use Strukt\Console\Runtime\CommandInterface;
use Strukt\Queue\Cli\Command;

/**
 * @command reply
 * @description Posts a reply for a consumed message.
 * @usage reply QUEUE ID VALUE [options]
 *
 * @argument queue
 * @description Name of the queue.
 *
 * @argument id
 * @description Id of the message being replied to.
 *
 * @argument value
 * @description Reply to post.
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
 *
 * @option ttl
 * @default 0
 * @description Seconds the reply stays available (0 = never expires).
 */
class ReplyCommand extends Command implements CommandInterface
{
    public function execute(array $arguments, array $options): int
    {
        $id = (int) $arguments['id'];
        $stored = $this->queue($options)->reply($arguments['queue'], $id, $arguments['value']);

        $this->respond(
            'reply',
            ['queue' => $arguments['queue'], 'id' => $id, 'stored' => $stored],
            (bool) $options['json'],
        );

        return $stored ? 0 : 1;
    }
}