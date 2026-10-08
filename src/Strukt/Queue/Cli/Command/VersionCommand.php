<?php

declare(strict_types=1);

namespace Strukt\Queue\Cli\Command;

use Strukt\Console\Runtime\CommandInterface;
use Strukt\Queue\Cli\Command;

/**
 * @command version
 * @description Reports the current version.
 * @usage version [options]
 *
 * @option json
 * @flag
 * @description Render the result as JSON.
 */
class VersionCommand extends Command implements CommandInterface
{
    /** Release reported by the version command. */
    private const VERSION = '0.1.0';

    public function execute(array $arguments, array $options): int
    {
        $this->respond('version', ['version' => self::VERSION], (bool) $options['json']);

        return 0;
    }
}