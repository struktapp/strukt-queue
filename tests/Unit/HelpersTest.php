<?php

declare(strict_types=1);

use Strukt\Queue;

test('every helper function is registered', function () {
    foreach (['qpush', 'qpop', 'qreply', 'qwait', 'qempty', 'qprocessed'] as $name) {
        expect(function_exists($name))->toBeTrue();
    }
});

test('the helper registry tracks the queue package', function () {
    if (!function_exists('helper')) {
        $this->markTestSkipped('Strukt base helper registry not available');
    }

    expect(helper('queue'))->toContain('qpush', 'qpop', 'qwait');
});

test('qpush and qpop round trip an element id', function () {
    $options = queue_options();
    $name = queue_name('helpers');

    $id = qpush($name, 'help', $options);

    expect($id)->toBe(1);
    expect(qpop($name, $popped, $options))->toBe('help');
    expect($popped)->toBe(1);
    expect(qprocessed($name, $id, $options))->toBeTrue();
});

test('qempty reports the queue state', function () {
    $options = queue_options();
    $name = queue_name('hempty');

    expect(qempty($name, $options))->toBeTrue();

    qpush($name, 'filling', $options);

    expect(qempty($name, $options))->toBeFalse();
});

test('qreply stores a reply and qwait delivers it once processed', function () {
    $options = queue_options();
    $name = queue_name('hreply');

    $id = qpush($name, 'ask', $options);
    qpop($name, $popped, $options);
    qreply($name, $id, 'answer', $options);

    $q = new Queue($options);
    $q->push($name, 'filler');

    expect(qwait($name, $id, 1, $options))->toBe('answer');
});

test('qwait times out for an unprocessed message instead of waiting forever', function () {
    $options = queue_options();
    $name = queue_name('htimeout');

    $id = qpush($name, 'stuck', $options);

    $started = microtime(true);
    expect(qwait($name, $id, 1, $options))->toBeNull();
    expect(microtime(true) - $started)->toBeLessThan(3);
});