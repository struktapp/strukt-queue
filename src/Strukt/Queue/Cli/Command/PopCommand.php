<?php

declare(strict_types=1);

namespace Strukt\Queue\Cli\Command;

use Strukt\Console\Runtime\CommandInterface;
use Strukt\Queue\Cli\Command;

/**
 * @command pop
 * @description Pops an element off the beginning of a queue and prints it.
 * @usage pop QUEUE [options]
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
class PopCommand extends Command implements CommandInterface
{
    public function execute(array $arguments, array $options): int
    {
        $queue = $this->queue($options);
        $value = $queue->pop($arguments['queue'], $id);

        if ($value === false) {
            $this->respond(
                'pop',
                ['queue' => $arguments['queue'], 'empty' => true],
                (bool) $options['json'],
            );

            return 1;
        }

        $this->respond(
            'pop',
            ['queue' => $arguments['queue'], 'id' => $id, 'value' => $value],
            (bool) $options['json'],
        );

        return 0;
    }
}