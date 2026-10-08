# `queues`

Lists every queue, per prefix, or confined to one prefix.

## Usage

```bash
queues [options]
```

## Options

| Option | Type | Required | Description |
|---|---|---|---|
| `--json` | `bool` | No | Render the result as JSON. |
| `--host` | `string` | No | Memcached host. |
| `--port` | `string` | No | Memcached port. |
| `--prefix` | `string` | No | Key namespace to list; all prefixes are shown when omitted. |
