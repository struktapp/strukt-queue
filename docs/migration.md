# Migration from strukt1/strukt-queue

The legacy queue was a memcached-backed FIFO with the same producer/consumer
shapes. The new `strukt4/queue` (composer package `strukt/queue`) changes
behavior where the old one silently broke, and renames some surfaces.

## What changed

| Aspect | Legacy | Strukt 4 |
| --- | --- | --- |
| Class | `Strukt\Queue` | `Strukt\Queue` (same) |
| Storage keys | `<queue>_tail`, `<queue>_head`, `<queue>_<id>`, `<queue>_<id>_reply` | `<prefix>:<queue>:tail`, `:head`, `:<id>`, `:<id>:reply` |
| Missing extension | `die("...Memcached required...")` | throws `RuntimeException` |
| Failed push | silently returned `FALSE` after advancing the tail | throws, tail keeps the gap but the rescuer skips it |
| Concurrent first pushes | could both issue id 1 | counters are `add()`-initialised, then incremented |
| `pop()` | `increment(head)` then read; could pass the tail and skip live messages | CAS-guarded claim; never passes the tail |
| Empty slot | read `FALSE`, returned `FALSE` as if empty | skipped over, later live messages still delivered |
| `getReply()` missing | indistinguishable from a `FALSE` reply | returns `null` via result code |
| `wait()` | `sleep(1)` forever | bounded by `$seconds`, returns `null` |
| Namespacing | none | `prefix` option isolates apps on a shared server |
| Helper names | `push`, `pop`, `reply`, `wait`, `is_empty`, ... | `qpush`, `qpop`, `qreply`, `qwait`, `qempty`, `qprocessed`, ... |

## API differences

- `pop()` reports the consumed id through the by-reference `$id` argument
  instead of the legacy peek-then-pop dance, so a consumed message can always
  be replied to by id.
- `push()` returns the id; a storage failure throws instead of returning false.
- `reply()` uses `add()`, so a message already answered refuses a second reply
  (legacy silently overwrote).
- `flush($queue)` is new and deletes messages, replies, and counters.

## Data migration

Queues produced by the legacy package under keys like `jobs_1` will not be
found by the new `jobs` (their keys are prefixed and colon-separated). Replays
are not provided; the new package is meant for new traffic. If you need the old
keys read once, map them with the legacy client and re-`push()` them.