# `flush`

Purges a queue entirely, or every queue under a prefix (-a).

## Usage

```bash
flush [QUEUE] [options]
```

## Arguments

| Name | Type | Required | Description |
|---|---|---|---|
| `queue` | `string` | No | Name of the queue to flush; ignored when --all is given. |

## Options

| Option | Type | Required | Description |
|---|---|---|---|
| `--json` | `bool` | No | Render the result as JSON. |
| `--all` | `bool` | No | Flush every queue registered under the prefix, or across the |
| `--host` | `string` | No | Memcached host. |
| `--port` | `string` | No | Memcached port. |
| `--prefix` | `string` | No | Key namespace to isolate queues on a shared server. |
