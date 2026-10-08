<?php

declare(strict_types=1);

use Strukt\Queue;
use Strukt\Queue\Example\Generator;

function gen_options(): array
{
    $opts = queue_options();

    return array_replace($opts, ['base' => 'gen']);
}

test('a short run leaves exactly the window queues alive', function () {
    $opts = gen_options();
    $generator = new Generator(array_replace($opts, ['window' => 5, 'pace' => 0.001]));
    $queue = new Queue($opts);

    $generator->run(5);

    $names = $queue->queues();

    expect($names)->toHaveCount(5);
    foreach (['gen-00001', 'gen-00002', 'gen-00003', 'gen-00004', 'gen-00005'] as $name) {
        expect($names)->toContain($name);
    }
});

test('the oldest queue is flushed once the window moves past it', function () {
    $opts = gen_options();
    $generator = new Generator(array_replace($opts, ['window' => 3, 'pace' => 0.001]));
    $queue = new Queue($opts);

    $generator->run(6);

    expect($queue->queues())->toHaveCount(3);
    expect($queue->queues())->toContain('gen-00006');
    expect($queue->queues())->not->toContain('gen-00001');
    expect($queue->queues())->not->toContain('gen-00002');
    expect($queue->queues())->not->toContain('gen-00003');
});

test('reset gives the generator a clean slate', function () {
    $opts = gen_options();
    $generator = new Generator(array_replace($opts, ['window' => 2, 'pace' => 0.001]));
    $queue = new Queue($opts);

    $generator->run(2);
    $generator->reset();

    expect($queue->queues())->toBe([]);
});