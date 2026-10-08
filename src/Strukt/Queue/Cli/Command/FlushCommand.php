<?php

declare(strict_types=1);

namespace Strukt\Queue\Cli\Command;

use Strukt\Console\Runtime\CommandInterface;
use Strukt\Queue\Cli\Command;

/**
 * @command flush
 * @description Purges a queue entirely, or every queue under a prefix (-a).
 * @usage flush [QUEUE] [options]
 *
 * @argument queue
 * @description Name of the queue to flush; ignored when --all is given.
 *
 * @option json
 * @flag
 * @description Render the result as JSON.
 *
 * @option all
 * @flag
 * @description Flush every queue registered under the prefix, or across the
 * whole server when no prefix is given.
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
 * @description Key namespace to isolate queues on a shared server.
 */
class FlushCommand extends Command implements CommandInterface
{
    public function execute(array $arguments, array $options): int
    {
        if ((bool) $options['all']) {
            $flushed = $this->queue($options)->flushAll($options['prefix'] ?? null);

            $this->respond(
                'flush',
                ['flushed' => count($flushed), 'queues' => $flushed],
                (bool) $options['json'],
            );

            return 0;
        }

        $name = (string) $arguments['queue'];

        $this->queue($options)->flush($name, $options['prefix'] ?? null);

        $this->respond(
            'flush',
            ['queue' => $name, 'flushed' => true],
            (bool) $options['json'],
        );

        return 0;
    }
}