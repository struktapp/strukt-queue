# Command line

The `kqueue` binary wraps every operation in a small, scriptable CLI. All
commands accept `--host`, `--port`, and `--prefix` (and `--ttl` where it
applies) to target a specific server, and a `--json` flag to emit machine
readable output. `kqueue list` lists the commands and `kqueue help <command>`
or `kqueue <command> --help` show usage; the per-command reference is generated
under `docs/commands/`.

## Quick tour

```bash
kqueue version                 # 0.1.0
kqueue push jobs 'payload'     # -> id: 1
kqueue status jobs             # count: 1 (exit 0), empty: true (exit 1)
kqueue pop jobs                # -> id: 1, value: payload
kqueue reply jobs 1 'done'     # -> stored: true
kqueue wait jobs 1 --seconds 5 # -> reply: done
kqueue queues                  # list every queue, across every prefix
kqueue queues --prefix jobs    # list every queue under the prefix
kqueue flush jobs              # purge one queue
kqueue flush --all --prefix jobs  # purge every queue under the prefix
kqueue flush --all             # purge every queue on the server
```

## Scripting

With `--json`, every command prints a single JSON object on stdout and uses the
exit code as the boolean result. For example, draining until empty:

```bash
while kqueue pop jobs --json > /dev/null; do :; done
```

`pop` exits `0` when an element was consumed and `1` when the queue is empty;
`status` exits `0` when the queue has pending messages and `1` when it is
empty; `reply` exits `0` when stored and `1` when a reply already exists;
`wait` exits `1` when it times out.

## Per-command reference

See `docs/commands/` for the generated markdown for each command.