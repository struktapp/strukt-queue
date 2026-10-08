<?php

declare(strict_types=1);

namespace Strukt\Queue\Cli;

/**
 * Shared behavior for every queue command: resolves the memcached connection
 * from the command options and renders a result as JSON or a short report.
 */
abstract class Command
{
    /**
     * Builds a queue client from the connection-related flags.
     *
     * @param array<string, mixed> $options Options.
     * @return Strukt\Queue Queue client.
     */
    protected function queue(array $options): \Strukt\Queue
    {
        $config = [];

        if (($options['host'] ?? null) !== null) {
            $config['host'] = (string) $options['host'];
        }

        if (($options['port'] ?? null) !== null) {
            $config['port'] = (int) $options['port'];
        }

        if (($options['prefix'] ?? null) !== null) {
            $config['prefix'] = (string) $options['prefix'];
        }

        if (($options['ttl'] ?? null) !== null) {
            $config['ttl'] = (int) $options['ttl'];
        }

        return new \Strukt\Queue($config);
    }

    /**
     * Prints a result either as JSON or as a short plain text report.
     *
     * @param string $command Command name, shown in the report header.
     * @param array<string, mixed> $result Command result.
     * @param bool $json Whether to render as JSON.
     */
    protected function respond(string $command, array $result, bool $json): void
    {
        echo $json
            ? json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL
            : $this->format($command, $result);
    }

    /**
     * Renders a result as a short plain text report.
     *
     * @param string $command Command name, shown in the header.
     * @param array<string, mixed> $result Command result.
     * @return string Report text.
     */
    private function format(string $command, array $result): string
    {
        $report = "Strukt Queue — $command\n──────────────────────\n";

        foreach ($result as $key => $value) {
            if (is_array($value)) {
                $report .= "$key:\n";

                foreach ($value as $nested => $nestedValue) {
                    $report .= '  ' . $nested . ': '
                        . (is_array($nestedValue) ? json_encode($nestedValue) : $this->render($nestedValue)) . "\n";
                }

                continue;
            }

            $report .= str_replace('_', ' ', $key) . ': ' . $this->render($value) . "\n";
        }

        return $report;
    }

    /**
     * Renders one scalar value for the report.
     */
    private function render(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => '',
            default => (string) $value,
        };
    }
}