<?php

declare(strict_types=1);

namespace Strukt\Queue\Cli\Command;

use Strukt\Console\Runtime\CommandInterface;
use Strukt\Queue\Cli\Command;

/**
 * @command status
 * @description Reports the state of a queue.
 * @usage status QUEUE [options]
 *
 * @argument queue
 * @description Name of the queue.
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
class StatusCommand extends Command implements CommandInterface
{
    public function execute(array $arguments, array $options): int
    {
        $queue = $this->queue($options);

        $this->respond(
            'status',
            [
                'queue' => $arguments['queue'],
                'empty' => $queue->is_empty($arguments['queue']),
                'count' => $queue->count($arguments['queue']),
            ],
            (bool) $options['json'],
        );

        return $queue->is_empty($arguments['queue']) ? 1 : 0;
    }
}