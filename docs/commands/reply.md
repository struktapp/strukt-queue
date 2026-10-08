# `reply`

Posts a reply for a consumed message.

## Usage

```bash
reply QUEUE ID VALUE [options]
```

## Arguments

| Name | Type | Required | Description |
|---|---|---|---|
| `queue` | `string` | No | Name of the queue. |
| `id` | `string` | No | Id of the message being replied to. |
| `value` | `string` | No | Reply to post. |

## Options

| Option | Type | Required | Description |
|---|---|---|---|
| `--json` | `bool` | No | Render the result as JSON. |
| `--host` | `string` | No | Memcached host. |
| `--port` | `string` | No | Memcached port. |
| `--prefix` | `string` | No | Key namespace to isolate queues on a shared server. |
| `--ttl` | `string` | No | Seconds the reply stays available (0 = never expires). |
