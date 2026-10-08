<?php

declare(strict_types=1);

use Strukt\Queue as QueueClient;
use Strukt\Queue\Example\Mail;
use Strukt\Queue\Supervisor;

function supervisor_queue(): Supervisor
{
    return new Supervisor(2, queue_client(), queue_name('supervisor'));
}

test('supervised workers drain relay worker output', function () {
    $supervisor = supervisor_queue();
    $supervisor->start(__DIR__ . '/fixtures/echo-worker.php');

    $lines = [];
    $deadline = microtime(true) + 4;
    $seen = false;

    while (microtime(true) < $deadline && !$seen) {
        $supervisor->drain(static function (int $slot, string $line) use (&$lines): void {
            $lines[] = trim($line);
        });
        $seen = count($lines) >= 2;
        usleep(100000);
    }

    $supervisor->stop();

    expect($supervisor->running())->toBeFalse()
        ->and($lines)->toHaveCount(2)
        ->and($lines)->each->toBe('hello from worker');
});

test('a worker that crashes after claiming has its message returned to the queue', function () {
    $queue = queue_client();
    $name = queue_name('crash');
    $supervisor = new Supervisor(1, $queue, $name, restartCap: 0);
    $supervisor->start(__DIR__ . '/fixtures/crash-worker.php');

    $payload = ['to' => 'alice@example.com', 'subject' => 'die', 'body' => 'boom'];
    $original = $queue->push($name, $payload);

    usleep(400000); // give the worker time to claim + crash

    $supervisor->drain(static function (): void {
    });

    expect($supervisor->redelivered())->toBe(1)
        ->and($supervisor->inFlight())->toBe(0);

    $back = $queue->pop($name, $redelivered);
    expect($back)->toBe($payload)
        ->and($redelivered)->not->toBe($original);

    $supervisor->stop();
});

test('a completed message is never returned to the queue', function () {
    $queue = queue_client();
    $name = queue_name('done');
    $supervisor = new Supervisor(1, $queue, $name);
    $supervisor->start(__DIR__ . '/fixtures/echo-worker.php');

    $payload = ['to' => 'bob@example.com', 'subject' => 'keep', 'body' => 'x'];
    $queue->push($name, $payload);

    usleep(400000);
    $supervisor->drain(static function (): void {
    });
    $supervisor->stop();

    expect($supervisor->redelivered())->toBe(0)
        ->and($queue->is_processed($name, 1))->toBeTrue();
});

test('dead workers are replaced under the pool and keep their slot', function () {
    $queue = queue_client();
    $name = queue_name('respawn');
    $supervisor = new Supervisor(2, $queue, $name, restartCap: 2);
    $supervisor->start(__DIR__ . '/fixtures/crash-worker.php');

    $queue->push($name, ['to' => 'dave@example.com', 'subject' => 'revive', 'body' => 'x']);

    for ($i = 0; $i < 6; $i++) {
        $supervisor->drain(static function (): void {
        });
        usleep(200000);
    }

    $processes = $supervisor->processes();
    expect($processes->totalRestarts())->toBeGreaterThanOrEqual(1)
        ->and($processes->count())->toBe(2);

    $supervisor->stop();
});

test('an email flow reaches replies end to end', function () {
    $queue = queue_client();
    $name = queue_name('emails');
    $supervisor = new Supervisor(3, $queue, $name);
    $supervisor->start(__DIR__ . '/../../example/worker.php', ['--interval=100', '--supervised']);

    $mails = [
        ['to' => 'alice@example.com', 'subject' => 'Hello Alice', 'body' => 'first'],
        ['to' => 'bob@example.com', 'subject' => 'Hello Bob', 'body' => 'second'],
        ['to' => 'carol@example.com', 'subject' => 'Hello Carol', 'body' => 'third'],
    ];

    $ids = [];
    foreach ($mails as $mail) {
        $ids[] = $queue->push($name, $mail);
    }

    $replied = [];
    $deadline = microtime(true) + 10;

    while (count($replied) < count($ids) && microtime(true) < $deadline) {
        $supervisor->drain(static function (): void {
        });

        foreach ($ids as $index => $id) {
            if ($queue->is_processed($name, $id) && ($reply = $queue->getReply($name, $id)) !== null) {
                $replied[$id] = $reply;
                unset($ids[$index]);
            }
        }

        usleep(100000);
    }

    $supervisor->stop();

    expect($replied)->toHaveCount(count($mails));
    expect(array_column($replied, 'sent'))->each->toBeTrue();
})->skip(function () {
    return !function_exists('proc_open');
}, 'proc_open not available');