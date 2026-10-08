<?php

declare(strict_types=1);

use Strukt\Queue;

test('the prefix option is carried by the client', function () {
    $queue = new Queue(array_replace(queue_options(), ['prefix' => 'acme']));

    expect($queue->prefix())->toBe('acme');
});

test('the default prefix is queue', function () {
    $queue = new Queue(['host' => '127.0.0.1', 'port' => 11211]);

    expect($queue->prefix())->toBe('queue');
});

test('the memcached client is reachable', function () {
    $queue = queue_client();

    expect($queue->client()::class)->toBe(Memcached::class);

    $queue->client()->getVersion();

    expect($queue->client()->getResultCode())->toBe(Memcached::RES_SUCCESS);
});

test('queue names are isolated per prefix', function () {
    $queue = queue_client();
    $name = queue_name('iso');

    $queue->push($name, 'one');

    $other = new Queue(['host' => '127.0.0.1', 'port' => 11211, 'prefix' => 'other']);

    expect($other->is_empty($name))->toBeTrue();
});

test('queues lists every queue pushed under the prefix', function () {
    $queue = queue_client();
    $alpha = queue_name('alpha');
    $beta = queue_name('beta');

    $queue->push($alpha, 'one');
    $queue->push($beta, 'two');

    $names = $queue->queues();

    expect($names)->toContain($alpha, $beta);
});

test('queues is empty on a fresh prefix', function () {
    $queue = queue_client();

    expect($queue->queues())->toBe([]);
});

test('flush removes the queue from the registry', function () {
    $queue = queue_client();
    $name = queue_name('gone');

    $queue->push($name, 'one');
    $queue->flush($name);

    expect($queue->queues())->not->toContain($name);
});

test('all lists every registered queue grouped by prefix', function () {
    $queue = queue_client();
    $other = new Queue(array_replace(queue_options(), ['prefix' => 'all-prefix-other']));
    $name = queue_name('all');
    $otherName = queue_name('other');

    $queue->push($name, 'one');
    $other->push($otherName, 'two');

    $all = $queue->all();

    expect($all)->toHaveKey($queue->prefix());
    expect($all[$queue->prefix()])->toContain($name);
    expect($all['all-prefix-other'])->toContain($otherName);
});

test('all ignores prefixes whose registry was fully flushed', function () {
    $queue = queue_client();
    $name = queue_name('own');

    $queue->push($name, 'one');
    $queue->flush($name);

    $all = $queue->all();

    expect($all)->not->toHaveKey($queue->prefix());
});

test('prefixes reports every prefix that has a registered queue', function () {
    $queue = queue_client();
    $other = new Queue(array_replace(queue_options(), ['prefix' => 'all-prefix-other']));

    $queue->push(queue_name('one'), 'one');
    $other->push(queue_name('two'), 'two');

    expect($queue->prefixes())->toContain($queue->prefix());
    expect($queue->prefixes())->toContain('all-prefix-other');
});

test('flushAll empties every queue under one prefix', function () {
    $queue = queue_client();
    $alpha = queue_name('alpha');
    $beta = queue_name('beta');
    $other = new Queue(array_replace(queue_options(), ['prefix' => 'all-prefix-other']));
    $otherName = queue_name('other');

    $queue->push($alpha, 'one');
    $queue->push($beta, 'two');
    $other->push($otherName, 'three');

    $flushed = $queue->flushAll($queue->prefix());

    expect($flushed)->toHaveCount(2);
    expect($flushed)->toContain($queue->prefix() . '/' . $alpha);
    expect($queue->queues())->toBe([]);
    expect($other->queues())->toContain($otherName);
});

test('flushAll without a prefix empties every queue on the server', function () {
    $queue = queue_client();
    $other = new Queue(array_replace(queue_options(), ['prefix' => 'all-prefix-flush-' . bin2hex(random_bytes(2))]));
    $name = queue_name('one');
    $prefix = $queue->prefix();
    $otherPrefix = $other->prefix();

    $queue->push($name, 'one');
    $other->push(queue_name('two'), 'two');

    $flushed = $queue->flushAll();

    expect($flushed)->toContain($prefix . '/' . $name);
    expect(array_filter($flushed, fn (string $pair) => str_starts_with($pair, $prefix . '/')))->toHaveCount(1);
    expect(array_filter($flushed, fn (string $pair) => str_starts_with($pair, $otherPrefix . '/')))->toHaveCount(1);
});