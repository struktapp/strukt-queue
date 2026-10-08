# Concurrency and guarantees

The legacy `strukt1/strukt-queue` kept two counters and one key per message and
hoped they would stay consistent. It did not: pops could step past the tail and
skip live messages, two producers could receive the same id, a reply could be
lost reading logic, and `wait()` blocked forever. This rebuild keeps the
storage shape but enforces the invariants the old code assumed.

## Storage layout

For a queue named `jobs` under prefix `acme`:

| Key | Value |
| --- | --- |
| `acme:jobs:tail` | highest id ever issued (never expires) |
| `acme:jobs:head` | last id consumed (never expires) |
| `acme:jobs:<id>` | the message payload |
| `acme:jobs:<id>:reply` | the reply, if any |
| `acme:__queues` | registry of every queue created under the prefix |
| `__queue_prefixes` | server-wide roster of every prefix with registered queues |

Tail and head never expire, so an expiring counter can no longer reset a live
queue or hand out a reused id.

`acme:__queues` is the queue registry that powers `queues()`, `all()`, and the
`kqueue queues` command. Memcached cannot enumerate keys efficiently on every
server, so the package keeps its own index instead: it is updated under CAS on
the first push to a queue (and cleaned up by `flush()`), which keeps concurrent
first pushes to different queues from losing a name. Queues written directly
against the raw client are not listed.

`__queue_prefixes` is the server-wide roster alongside it, also maintained under
CAS. It names every prefix that has a non-empty registry, which is what lets
`kqueue queues` (without `--prefix`) list all queues and `flush --all` (without
`--prefix`) clear them all — and what lets `flush --all` escape the isolation a
single prefix usually provides.

## Every message is handed out at most once

`pop()` works in two steps:

1. **Claim.** Read the tail, then read the head with memcached's compare-and
   swap token. If head is already at or past the tail the queue is empty.
   Otherwise the head is advanced by one with `cas()` — atomically, and only if
   nobody changed it. Two workers that read the same head never both succeed;
   the loser re-reads and takes the next slot.
2. **Rescue.** The slot is read with a brief retry to ride out a push that is
   still storing its payload. If the slot is genuinely dead (expired or an
   interrupted push), the pop skips forward to the next live message and folds
   the dead run into the head in one CAS, so later messages are never stranded
   and the survivor is never handed out twice.

Because a slot can only be claimed once, `pop()` never returns the same message
to two workers.

## What exactly-once means here

Delivery is exactly-once per **claim**, not per crash. `pop()` advances the
head before the payload is returned; if the worker crashes between the claim
and finishing the job, that message is not redelivered. Memcached is memory —
jobs are durable only while the server holds them and a restart loses them.
For reliable pipelines, treat memcached as the transit layer and hand off
durable work to a database: reserve ids from a database, or re-queue crashed
jobs with a watchdog.

## Crash redelivery with a supervisor

The queue-aware `Strukt\Queue\Supervisor` closes most of that gap. Workers it
spawns report each claim on standard output (`CLAIM <id> <payload>` and
`DONE <id>`, see the example worker); the supervisor tracks the in-flight
payloads per worker slot, and when a worker dies anything it claimed but never
finished is pushed back onto the queue under a fresh id. Redelivery is
best-effort **at least once**: the original caller waiting on the old id sees
its reply key never arrive, and the job is processed again by the next worker.

## Practices

- Use a distinct `prefix` per application so separate deployments cannot see
  each other's queues, and so `flush()` can never touch a neighbour.
- Keep the item `ttl` at `0` (never expiry) unless stale messages are
  acceptable. An expired message leaves a hole the consumer skips over; the
  head still advances past it, so an expired message is *not* redelivered.
- Size a queue to memcached limits. A single element is capped near 1 MB, and
  heap eviction is LRU across the whole server, not per queue.
- One `pop()` call = one claim. A long-lived worker should loop, and drain
  quickly, because every unpopped message is a live key.