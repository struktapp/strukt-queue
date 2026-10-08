<?php

declare(strict_types=1);

use Strukt\Queue;

test('a missing slot is skipped to keep the messages behind it alive', function () {
    $opts = queue_options();
    $queue = new Queue($opts);
    $name = queue_name('rescue');

    $queue->push($name, 'dead-slot');
    $queue->push($name, 'survivor');

    $queue->client()->delete($opts['prefix'] . ':' . $name . ':1');

    expect($queue->pop($name, $id))->toBe('survivor');
    expect($id)->toBe(2);
    expect($queue->is_empty($name))->toBeTrue();
});

test('only dead slots left pops false instead of stranding the queue', function () {
    $opts = queue_options();
    $queue = new Queue($opts);
    $name = queue_name('dead');

    $queue->push($name, 'ghost');
    $queue->client()->delete($opts['prefix'] . ':' . $name . ':1');

    expect($queue->pop($name, $id))->toBeFalse();
    expect($queue->is_empty($name))->toBeTrue();
});

test('an item that expires after push is skipped for later arrivals', function () {
    $opts = queue_options();
    $queue = new Queue($opts);
    $name = queue_name('ttl');

    $queue->push($name, 'expiring', 1);
    $queue->push($name, 'kept');

    sleep(2);

    expect($queue->pop($name, $id))->toBe('kept');
    expect($id)->toBe(2);
});

test('concurrent pops never hand the same message to two workers', function () {
    if (!pcntl_available()) {
        $this->markTestSkipped('pcntl not available');
    }

    $opts = queue_options();
    $queue = new Strukt\Queue($opts);
    $name = queue_name('dup');
    $workers = 4;
    $per = 10;
    $log = tempnam(sys_get_temp_dir(), 'qd');
    $go = $log . '.cg';

    for ($i = 0; $i < $workers * $per; $i++) {
        $queue->push($name, $i);
    }

    spawn($workers, $go, function ($w) use ($opts, $name, $log, $go) {
        barrier($go);
        $client = new Strukt\Queue($opts);
        $local = [];

        while (($value = $client->pop($name, $id)) !== false) {
            $local[] = $value;
        }

        file_put_contents($log, implode(',', $local) . "\n", FILE_APPEND | LOCK_EX);
    });

    $seen = [];
    foreach (file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $seen = array_merge($seen, array_map('intval', explode(',', $line)));
    }

    sort($seen);

    expect(array_values(array_unique($seen)))->toEqual(range(0, $workers * $per - 1));
    expect($queue->is_empty($name))->toBeTrue();
});

test('concurrent producers never reuse an id', function () {
    if (!pcntl_available()) {
        $this->markTestSkipped('pcntl not available');
    }

    $opts = queue_options();
    $name = queue_name('pids');
    $workers = 4;
    $per = 15;
    $log = tempnam(sys_get_temp_dir(), 'qp');
    $go = $log . '.pg';

    spawn($workers, $go, function ($w) use ($opts, $name, $log, $per, $go) {
        barrier($go);
        $client = new Strukt\Queue($opts);

        for ($i = 0; $i < $per; $i++) {
            $id = $client->push($name, $w * $per + $i);
            file_put_contents($log, "p:$id\n", FILE_APPEND | LOCK_EX);
        }
    });

    $ids = [];
    foreach (file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $ids[] = (int) substr($line, 2);
    }

    sort($ids);
    $ids = array_values(array_unique($ids));

    expect($ids)->toHaveCount($workers * $per);
    expect($ids)->toEqual(range(1, $workers * $per));
});

test('concurrent consumers deliver every message exactly once', function () {
    if (!pcntl_available()) {
        $this->markTestSkipped('pcntl not available');
    }

    $opts = queue_options();
    $name = queue_name('once');
    $producers = 3;
    $consumers = 3;
    $per = 12;
    $total = $producers * $per;
    $log = tempnam(sys_get_temp_dir(), 'qc');
    $go = $log . '.pg';
    $go2 = $log . '.cg';

    spawn($producers, $go, function ($w) use ($opts, $name, $per, $go) {
        barrier($go);
        $client = new Strukt\Queue($opts);

        for ($i = 0; $i < $per; $i++) {
            $client->push($name, $w * $per + $i);
        }
    });

    spawn($consumers, $go2, function () use ($opts, $name, $log, $go2) {
        barrier($go2);
        $client = new Strukt\Queue($opts);

        while (($value = $client->pop($name, $id)) !== false) {
            file_put_contents($log, "c:$value\n", FILE_APPEND | LOCK_EX);
        }
    });

    $seen = [];
    foreach (file($log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $seen[] = (int) substr($line, 2);
    }

    sort($seen);
    $seen = array_values(array_unique($seen));

    expect($seen)->toHaveCount($total);
    expect($seen)->toEqual(range(0, $total - 1));

    $queue = queue_client();
    expect($queue->is_empty($name))->toBeTrue();
});