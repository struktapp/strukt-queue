<?php

declare(strict_types=1);

namespace Strukt\Queue\Cli\Command;

use Strukt\Console\Runtime\CommandInterface;
use Strukt\Queue\Cli\Command;

/**
 * @command queues
 * @description Lists every queue, per prefix, or confined to one prefix.
 * @usage queues [options]
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
 * @description Key namespace to list; all prefixes are shown when omitted.
 */
class QueuesCommand extends Command implements CommandInterface
{
    public function execute(array $arguments, array $options): int
    {
        $queue = $this->queue($options);

        if (($options['prefix'] ?? null) === null) {
            $queues = [];

            foreach ($queue->all() as $prefix => $names) {
                foreach ($names as $name) {
                    $queues[$prefix][$name] = [
                        'count' => $queue->count($name, $prefix),
                        'empty' => $queue->is_empty($name, $prefix),
                    ];
                }
            }

            $this->respond('queues', ['queues' => $queues], (bool) $options['json']);

            return 0;
        }

        $queues = [];

        foreach ($queue->queues() as $name) {
            $queues[$name] = [
                'count' => $queue->count($name),
                'empty' => $queue->is_empty($name),
            ];
        }

        $this->respond('queues', ['queues' => $queues], (bool) $options['json']);

        return 0;
    }
}