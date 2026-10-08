# Strukt Queue

A memcached-backed FIFO message queue with replies for PHP 8.2+, rebuilt from
the scratch-pad `strukt1/strukt-queue` into a concurrency-safe component. It
supports the classic producer/consumer flow — push a job, pop it, answer with a
reply, wait for the answer — synchronised properly so workers can run in
parallel without losing or duplicating messages.

```php
use Strukt\Queue;

$queue = new Queue(['prefix' => 'acme']);

$id = $queue->push('jobs', ['user' => 7, 'type' => 'export']);

// ...in a worker...
$job = $queue->pop('jobs', $id);
$queue->reply('jobs', $id, 'exported-42.pdf');

// ...in the caller...
$reply = qwait('jobs', $id, 30);
```

## Requirements

- PHP 8.2+
- the `memcached` extension (`memcached`, `libmemcached`)
- a running memcached server (default `127.0.0.1:11211`)

## Installation

```bash
composer require strukt/queue
```

The package is self-contained; `strukt/base` (environment values and the helper
registry), `strukt/console` (the command runtime), and `strukt/process` (the
worker-pool runner behind the supervised demo) come along automatically.
The `kqueue` binary is installed into `vendor/bin/`.

## Feature summary

- **FIFO delivery** with monotonic, never-reused ids.
- **Exactly-once handoff** under parallel workers: `pop()` is CAS-guarded, so
  no two workers ever receive the same message and the head can never overtake
  the tail.
- **Replies** let the caller block until a job is processed and answered.
- **Namespaces** (`prefix` option) isolate many apps or environments on one
  memcached server.
- **Loud failures** — the constructor throws instead of `die()`, a failed
  `push()` throws instead of silently dropping the message, and `wait()` gives
  up after a timeout instead of hanging forever.
- **Rescue path** — an expired or missing slot is skipped over, never mistaken
  for an empty queue.
- **Crash redelivery** — `Strukt\Queue\Supervisor` keeps a pool of workers
  alive (restart cap + backoff) and returns to the queue any message a dead
  worker claimed but never finished.

## CLI

A `kqueue` command line tool is bundled. See the
[command reference](docs/cli.md); `kqueue list` and `kqueue help <command>`
work locally too:

```bash
kqueue push jobs 'my payload'
kqueue pop jobs
kqueue status jobs
kqueue queues
kqueue queues --prefix jobs
kqueue flush jobs
kqueue flush --all --prefix jobs
kqueue flush --all
```

## API

The full surface is one class, `Strukt\Queue`:

| Method | Purpose |
| --- | --- |
| `push($queue, $item, $ttl = null)` | Enqueue a value, returns its id. |
| `pop($queue, &$id = null)` | Dequeue the next value (`FALSE` when empty). |
| `reply($queue, $id, $reply, $ttl = null)` | Store a reply for a message. |
| `getReply($queue, $id)` | Fetch the reply (`null` when none yet). |
| `is_empty($queue)` | Whether nothing remains to be consumed. |
| `is_processed($queue, $id)` | Whether a message has been popped. |
| `count($queue)` | Messages currently ahead. |
| `flush($queue)` | Purge a queue, messages and counters. |
| `flushAll($prefix = null)` | Purge every queue under a prefix (or the whole server), returns the flushed `prefix/queue` pairs. |
| `queues()` | Names of every queue pushed under the prefix. |
| `all()` | Every queue on the server, grouped by `prefix`. |
| `prefixes()` | Every prefix that has a registered queue. |
| `client()` | The underlying `Memcached` instance. |
| `prefix()` | The key namespace in use. |

Global helpers `qpush`, `qpop`, `qreply`, `qwait`, `qempty`, and `qprocessed`
mirror these methods and are registered through the Strukt base helper
registry.

## Testing

An activity generator, `example/generate.php`, keeps a rotating window of queues
alive under a prefix so you can watch them appear and disappear — useful while
building or testing tooling against `kqueue queues`:

```bash
# terminal 1
php example/generate.php --prefix demo --window 8 --pace 1

# terminal 2
kqueue queues --prefix demo
```

It pushes a burst of messages onto a fresh queue each "pace" and flushes the
queue that falls out of the window, so the set under the prefix never stops
churning. Stop it with Ctrl-C; the window it leaves behind is the last state.
`kqueue flush --all --prefix demo` (or `kqueue flush --all` for the whole
server) sweeps the residue away.

## Guidelines

Design notes, trade-offs, and the differences from the legacy package live in
[the docs](docs/README.md). Read
[concurrency and guarantees](docs/concurrency.md) before building a pipeline on
top of it.

## License

MIT