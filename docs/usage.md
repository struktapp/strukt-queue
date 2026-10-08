# Usage

Everything lives on one class, `Strukt\Queue`. Build it once and share it; it
is stateless besides the memcached connection.

```php
use Strukt\Queue;

$queue = new Queue([
    'host'   => '127.0.0.1',
    'port'   => 11211,
    'prefix' => 'acme',   // isolate apps on a shared server
    'ttl'    => 0,        // 0 keeps items until they are popped
]);
```

## Producer

```php
$id = $queue->push('jobs', ['user' => 7, 'type' => 'export']);
```

`push()` stores any value the memcached serializer can carry (scalars, arrays,
objects) and returns the id the message will be consumed under. Ids are
monotonic within a queue and are never reused, even across failed pushes.

## Consumer

```php
while (($job = $queue->pop('jobs', $id)) !== false) {
    echo "handling job $id",
    $queue->reply('jobs', $id, $result);
}
```

`pop()` returns the next element, or `FALSE` when the queue is empty, and
writes the consumed id into the referenced variable. The id can be used to
`reply()`.

## Reply and wait

```php
$id = $queue->push('jobs', $payload);

// worker pops and replies (see above)

$reply = $queue->getReply('jobs', $id);   // null until answered
```

`getReply()` returns `null` while no reply exists and removes the reply once
read. To block until a reply arrives, use the global `qwait()` helper or the
polling loop it wraps:

```php
$reply = qwait('jobs', $id, 30);   // returns null after 30s
```

## Inspection

```php
$queue->count('jobs');        // messages currently ahead
$queue->is_empty('jobs');     // true when nothing remains
$queue->is_processed('jobs', $id); // true once the id has been popped
```

## Cleanup

```php
$queue->flush('jobs');
```

`flush()` deletes every message, reply, and counter of a queue so a fresh
`push()` starts ids over at 1. It is intended for maintenance and tests.

## Worker pool

Run several worker processes against a queue from one class: `Strukt\Queue\Supervisor`.
It composes `strukt/process`'s `ProcessSupervisor`, so workers that die are
restarted in place under a restart policy, and any message a dead worker
claimed but never finished is returned to the queue (crash redelivery).

```php
use Strukt\Queue\Supervisor;

$supervisor = new Supervisor(3, $queue, 'jobs', restartCap: 5, backoff: 0.5);
$supervisor->start(PHP_BINARY, ['/path/to/worker.php', '--interval=150']);
```

The supervisor hands every worker the queue and prefix it supervises, so the
caller only passes extra flags. Poll the pool in a loop:

```php
while ($running) {
    $supervisor->drain(static function (int $slot, string $line): void {
        echo $line;                       // worker chatter
    });
    // ... check replies with $queue->getReply()
    usleep(100000);
}

$supervisor->stop();
```

For redelivery to work the workers must report their progress on standard
output with `CLAIM <id> <payload>` before processing a message and `DONE <id>`
after replying (the example `worker.php` does this behind its `--supervised`
flag). The supervisor parses those lines, relays everything else, and re-pushes
un-finished claims to the queue when a worker exits.

## Undergoing connection

If you need SASL authentication, pass `username` and `password`. The client
then switches to the binary protocol automatically.