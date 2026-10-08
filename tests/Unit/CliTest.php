<?php

declare(strict_types=1);

use Strukt\Queue\Cli\Application;

test('the version command reports the release', function () {
    $app = new Application(__DIR__ . '/../../src/Strukt/Queue/Cli/Command');

    $result = run($app, ['kqueue', 'version']);

    expect($result['code'])->toBe(0);
    expect($result['out'])->toContain('version: 0.1.0');
});

test('the list command shows every queue command', function () {
    $app = new Application(__DIR__ . '/../../src/Strukt/Queue/Cli/Command');

    $result = run($app, ['kqueue', 'list']);

    expect($result['code'])->toBe(0);
    foreach (['push', 'pop', 'reply', 'wait', 'status', 'flush', 'queues', 'version'] as $command) {
        expect($result['out'])->toContain($command);
    }
});

test('push and pop work end to end through the cli', function () {
    $app = new Application(__DIR__ . '/../../src/Strukt/Queue/Cli/Command');
    $options = queue_options();
    $name = queue_name('cli');
    $prefix = $options['prefix'];

    $push = run($app, ['kqueue', 'push', $name, 'payload', '--prefix', $prefix]);
    expect($push['code'])->toBe(0);
    expect($push['out'])->toContain('id: 1');

    $status = run($app, ['kqueue', 'status', $name, '--prefix', $prefix]);
    expect($status['code'])->toBe(0);
    expect($status['out'])->toContain('count: 1');

    $pop = run($app, ['kqueue', 'pop', $name, '--prefix', $prefix]);
    expect($pop['code'])->toBe(0);
    expect($pop['out'])->toContain('value: payload');

    $empty = run($app, ['kqueue', 'status', $name, '--prefix', $prefix]);
    expect($empty['code'])->toBe(1);
    expect($empty['out'])->toContain('empty: true');

    run($app, ['kqueue', 'flush', $name, '--prefix', $prefix]);
});

test('the queues command lists every queue under the prefix', function () {
    $app = new Application(__DIR__ . '/../../src/Strukt/Queue/Cli/Command');
    $options = queue_options();
    $prefix = $options['prefix'];

    foreach (['alpha', 'beta'] as $name) {
        run($app, ['kqueue', 'flush', $name, '--prefix', $prefix]);
        run($app, ['kqueue', 'push', $name, 'x', '--prefix', $prefix]);
    }

    $result = run($app, ['kqueue', 'queues', '--prefix', $prefix]);

    expect($result['code'])->toBe(0);
    expect($result['out'])->toContain('alpha');
    expect($result['out'])->toContain('beta');

    $json = run($app, ['kqueue', 'queues', '--prefix', $prefix, '--json']);

    expect($json['code'])->toBe(0);

    $payload = json_decode($json['out'], true);

    expect($payload['queues'])->toHaveKeys(['alpha', 'beta']);
    expect($payload['queues']['alpha']['count'])->toBe(1);

    run($app, ['kqueue', 'flush', 'alpha', '--prefix', $prefix]);
    run($app, ['kqueue', 'flush', 'beta', '--prefix', $prefix]);
});

test('the queues command without a prefix lists every queue across prefixes', function () {
    $app = new Application(__DIR__ . '/../../src/Strukt/Queue/Cli/Command');
    $options = queue_options();
    $prefix = $options['prefix'];
    $other = array_replace(queue_options(), ['prefix' => 'cli-other-' . $prefix]);

    run($app, ['kqueue', 'push', 'one', 'x', '--prefix', $prefix]);
    run($app, ['kqueue', 'push', 'two', 'x', '--prefix', $other['prefix']]);

    $json = run($app, ['kqueue', 'queues', '--json']);

    expect($json['code'])->toBe(0);

    $payload = json_decode($json['out'], true);

    expect($payload['queues'])->toHaveKey($prefix);
    expect($payload['queues'][$prefix])->toHaveKey('one');
    expect($payload['queues'])->toHaveKey($other['prefix']);
    expect($payload['queues'][$other['prefix']])->toHaveKey('two');

    run($app, ['kqueue', 'flush', 'one', '--prefix', $prefix]);
    run($app, ['kqueue', 'flush', 'two', '--prefix', $other['prefix']]);
});

test('flush --all empties every queue under the prefix', function () {
    $app = new Application(__DIR__ . '/../../src/Strukt/Queue/Cli/Command');
    $options = queue_options();
    $prefix = $options['prefix'];

    run($app, ['kqueue', 'push', 'alpha', 'x', '--prefix', $prefix]);
    run($app, ['kqueue', 'push', 'beta', 'x', '--prefix', $prefix]);

    $result = run($app, ['kqueue', 'flush', '--all', '--prefix', $prefix, '--json']);

    expect($result['code'])->toBe(0);

    $payload = json_decode($result['out'], true);

    expect($payload['flushed'])->toBe(2);
    expect($payload['queues'])->toContain($prefix . '/alpha');

    $after = run($app, ['kqueue', 'queues', '--prefix', $prefix, '--json']);

    expect(json_decode($after['out'], true)['queues'])->toBe([]);
});

test('flush --all without a prefix empties every queue on the server', function () {
    $app = new Application(__DIR__ . '/../../src/Strukt/Queue/Cli/Command');
    $options = queue_options();
    $prefix = $options['prefix'];

    run($app, ['kqueue', 'push', 'one', 'x', '--prefix', $prefix]);

    $result = run($app, ['kqueue', 'flush', '--all', '--json']);

    expect($result['code'])->toBe(0);

    $payload = json_decode($result['out'], true);

    expect($payload['flushed'])->toBeGreaterThanOrEqual(1);
    expect($payload['queues'])->toContain($prefix . '/one');

    $after = run($app, ['kqueue', 'queues', '--prefix', $prefix, '--json']);

    expect(json_decode($after['out'], true)['queues'])->toBe([]);
});

test('unknown commands fail loudly', function () {
    exec('php ' . __DIR__ . '/../../bin/kqueue nope 2>&1', $out, $code);

    expect($code)->toBe(1);
    expect(implode("\n", $out))->toContain('Unknown command');
});

/**
 * Runs the console application, capturing stdout, stderr, and the exit code.
 *
 * @return array{code: int, out: string, err: string}
 */
function run(Application $app, array $argv): array
{
    ob_start();
    $code = $app->run($argv);
    $out = (string) ob_get_clean();

    return [
        'code' => $code,
        'out' => $out,
        'err' => '', // the runtime writes errors to STDERR, not the output buffer
    ];
}