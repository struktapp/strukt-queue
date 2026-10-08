<?php

declare(strict_types=1);

test('flush empties the queue and its counters', function () {
    $queue = queue_client();
    $name = queue_name('flush');

    $queue->push($name, 'a');
    $queue->push($name, 'b');
    $queue->pop($name, $id);
    $queue->reply($name, $id, 'ok');

    $queue->flush($name);

    expect($queue->is_empty($name))->toBeTrue();
    expect($queue->count($name))->toBe(0);
    expect($queue->is_processed($name, $id))->toBeFalse();

    // A fresh one arrives after the flush as id 1 again, proving the tail
    // counter was really wiped and not merely jumped past.
    expect($queue->push($name, 'again'))->toBe(1);
});

test('two queue names under one prefix do not interfere', function () {
    $queue = queue_client();

    $a = queue_name('a');
    $b = queue_name('b');

    $queue->push($a, 'A');
    $queue->push($b, 'B');
    $queue->push($b, 'B2');

    expect($queue->pop($a))->toBe('A');
    expect($queue->pop($b))->toBe('B');
    expect($queue->is_empty($a))->toBeTrue();
    expect($queue->is_empty($b))->toBeFalse();
});