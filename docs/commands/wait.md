# `wait`

Waits for a message to be popped, then prints its reply.

## Usage

```bash
wait QUEUE ID [options]
```

## Arguments

| Name | Type | Required | Description |
|---|---|---|---|
| `queue` | `string` | No | Name of the queue. |
| `id` | `string` | No | Id of the message to wait on. |

## Options

| Option | Type | Required | Description |
|---|---|---|---|
| `--seconds` | `string` | No | How long to wait before giving up. |
| `--json` | `bool` | No | Render the result as JSON. |
| `--host` | `string` | No | Memcached host. |
| `--port` | `string` | No | Memcached port. |
| `--prefix` | `string` | No | Key namespace to isolate queues on a shared server. |
