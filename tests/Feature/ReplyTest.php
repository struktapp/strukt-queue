<?php

declare(strict_types=1);

test('a reply can be fetched once the message is consumed', function () {
    $queue = queue_client();
    $name = queue_name('reply');

    $queue->push($name, 'ask');
    $queue->pop($name, $id);

    expect($queue->is_processed($name, $id))->toBeTrue();

    $queue->reply($name, $id, 'answer');

    expect($queue->getReply($name, $id))->toBe('answer');
});

test('an unanswered message has no reply', function () {
    $queue = queue_client();
    $name = queue_name('noreply');

    $queue->push($name, 'ask');
    $queue->pop($name, $id);

    expect($queue->getReply($name, $id))->toBeNull();
});

test('draining a pop sweeps its item but keeps the reply until read', function () {
    $queue = queue_client();
    $name = queue_name('drain');

    $queue->push($name, 'ask');
    $queue->pop($name, $id);
    $queue->reply($name, $id, 'late');

    // The produced item itself is deleted on pop; forcing a manual pop again
    // must not resurrect a stale item under a new id.
    $queue->push($name, 'fresh');
    $queue->pop($name, $id2);

    expect($id2)->toBe(2);
    expect($queue->getReply($name, $id))->toBe('late');
    expect($queue->getReply($name, $id2))->toBeNull();
});

test('waiting times out for a message that is never processed', function () {
    $queue = queue_client();
    $name = queue_name('timeout');

    $queue->push($name, 'stuck');

    expect(queue_wait($queue, $name, 1, 1))->toBeNull();
});

/**
 * Mirrors the global qwait() helper against an existing client, doing the
 * polling loop inline so the test does not need a second connection.
 */
function queue_wait(Strukt\Queue $queue, string $name, int $id, int $seconds): mixed
{
    $deadline = microtime(true) + $seconds;

    do {
        if ($queue->is_processed($name, $id)) {
            return $queue->getReply($name, $id);
        }

        usleep(200000);
    } while (microtime(true) < $deadline);

    return null;
}