# `push`

Pushes an element onto the end of a queue and prints its id.

## Usage

```bash
push QUEUE VALUE [options]
```

## Arguments

| Name | Type | Required | Description |
|---|---|---|---|
| `queue` | `string` | No | Name of the queue. |
| `value` | `string` | No | Element to store. |

## Options

| Option | Type | Required | Description |
|---|---|---|---|
| `--json` | `bool` | No | Render the result as JSON. |
| `--host` | `string` | No | Memcached host. |
| `--port` | `string` | No | Memcached port. |
| `--prefix` | `string` | No | Key namespace to isolate queues on a shared server. |
| `--ttl` | `string` | No | Seconds the element stays available (0 = never expires). |
