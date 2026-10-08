# Helpers

The global helpers mirror the `Strukt\Queue` methods and are registered through
the Strukt base helper registry. Every function accepts the same options array
as `new Queue(...)` as its last argument, so caller and worker can agree on a
queue connection.

They are declared only when the registry is present, so the package never
depends on bootstrap order.

| Helper | Signature | Returns |
| --- | --- | --- |
| `qpush` | `qpush($name, $item, $options = [])` | `int` message id |
| `qpop` | `qpop($name, &$id = null, $options = [])` | element or `FALSE` |
| `qreply` | `qreply($name, $id, $reply, $options = [])` | `bool` stored |
| `qwait` | `qwait($name, $id, $seconds = 10, $options = [])` | reply or `null` |
| `qempty` | `qempty($name, $options = [])` | `bool` |
| `qprocessed` | `qprocessed($name, $id, $options = [])` | `bool` |

## Example

```php
helper('queue');   // selects the queue package in the registry

$id = qpush('mail', ['to' => 'bob@example.com']);

// ...somewhere a worker...
$job = qpop('mail', $mid);
qreply('mail', $mid, 'sent');

// ...back in the caller...
$reply = qwait('mail', $id, 60);
```

## Differences from the legacy helpers

The legacy shipped uncolored names (`push`, `pop`, `wait`, ...). Those are
prone to collision with other libraries, so the new package prefixes them with
`q`. Calling code needs only to rename the function. The other changes:

- `pop` now also reports the id via the `$id` argument instead of returning a
  `[$id, $data]` tuple paired with a separate id lookup.
- `wait` is bounded — it returns `null` after `$seconds` instead of blocking
  forever.
- every function accepts an explicit `$options` argument instead of reading
  only the convention `memcached_*` environment values.