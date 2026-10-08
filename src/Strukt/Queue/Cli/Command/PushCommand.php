<?php

declare(strict_types=1);

namespace Strukt\Queue\Cli\Command;

use Strukt\Console\Runtime\CommandInterface;
use Strukt\Queue\Cli\Command;

/**
 * @command push
 * @description Pushes an element onto the end of a queue and prints its id.
 * @usage push QUEUE VALUE [options]
 *
 * @argument queue
 * @description Name of the queue.
 *
 * @argument value
 * @description Element to store.
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
 * @description Seconds the element stays available (0 = never expires).
 */
class PushCommand extends Command implements CommandInterface
{
    public function execute(array $arguments, array $options): int
    {
        $id = $this->queue($options)->push($arguments['queue'], $arguments['value']);

        $this->respond('push', ['queue' => $arguments['queue'], 'id' => $id], (bool) $options['json']);

        return 0;
    }
}