# `status`

Reports the state of a queue.

## Usage

```bash
status QUEUE [options]
```

## Arguments

| Name | Type | Required | Description |
|---|---|---|---|
| `queue` | `string` | No | Name of the queue. |

## Options

| Option | Type | Required | Description |
|---|---|---|---|
| `--json` | `bool` | No | Render the result as JSON. |
| `--host` | `string` | No | Memcached host. |
| `--port` | `string` | No | Memcached port. |
| `--prefix` | `string` | No | Key namespace to isolate queues on a shared server. |
