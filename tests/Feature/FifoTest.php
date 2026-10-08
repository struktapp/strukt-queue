<?php

declare(strict_types=1);

test('an untouched queue reports empty', function () {
    $queue = queue_client();

    expect($queue->is_empty(queue_name('fresh')))->toBeTrue();
    expect($queue->count(queue_name('fresh')))->toBe(0);
});

test('pushed elements pop in insertion order', function () {
    $queue = queue_client();
    $name = queue_name('fifo');

    $queue->push($name, 'first');
    $queue->push($name, 'second');
    $queue->push($name, 'third');

    expect($queue->pop($name, $id1))->toBe('first');
    expect($id1)->toBe(1);

    expect($queue->pop($name, $id2))->toBe('second');
    expect($id2)->toBe(2);

    expect($queue->pop($name, $id3))->toBe('third');
    expect($id3)->toBe(3);
});

test('issuing ids never reuses an earlier id', function () {
    $queue = queue_client();
    $name = queue_name('ids');

    for ($i = 1; $i <= 5; $i++) {
        expect($queue->push($name, "m$i"))->toBe($i);
    }
});

test('count tracks only the unpopped messages', function () {
    $queue = queue_client();
    $name = queue_name('count');

    $queue->push($name, 'a');
    $queue->push($name, 'b');
    $queue->push($name, 'c');

    expect($queue->count($name))->toBe(3);

    $queue->pop($name);
    $queue->pop($name);

    expect($queue->count($name))->toBe(1);
});

test('popping beyond the tail returns false and zeros the id', function () {
    $queue = queue_client();
    $name = queue_name('empty');

    expect($queue->pop($name, $id))->toBeFalse();
    expect($id)->toBe(0);

    $queue->push($name, 'only');
    $queue->pop($name);
    $queue->pop($name);

    expect($queue->is_empty($name))->toBeTrue();
    expect($queue->pop($name, $id))->toBeFalse();
    expect($id)->toBe(0);
});

test('a message is processed as soon as it is popped', function () {
    $queue = queue_client();
    $name = queue_name('done');

    $queue->push($name, 'job');
    expect($queue->is_processed($name, 1))->toBeFalse();

    $queue->pop($name, $id);
    expect($id)->toBe(1);
    expect($queue->is_processed($name, 1))->toBeTrue();
    expect($queue->is_processed($name, 99))->toBeFalse();
});

test('stored values keep their type', function () {
    $queue = queue_client();
    $name = queue_name('types');

    $payload = ['user' => 7, 'tags' => ['a', 'b'], 'ok' => true];
    $queue->push($name, $payload, 30);

    expect($queue->pop($name))->toBe($payload);
});