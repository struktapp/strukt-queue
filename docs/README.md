# Strukt Queue documentation

A memcached-backed FIFO message queue with replies for PHP 8.2+, rebuilt from
the scratch-pad `strukt1/strukt-queue` into a concurrency-safe component. The
storage is plain memcached keys, but every step that used to race or fail
silently in the original is now explicit and guarded.

Topics:

- [Installation](installation.md) — requirements, composer, the `kqueue` binary.
- [Usage](usage.md) — the `Strukt\Queue` API with the producer/consumer pattern.
- [Helpers](helpers.md) — the global `qpush`/`qpop`/`qreply`/`qwait` functions.
- [Command line](cli.md) — every `kqueue` command.
- [Concurrency and guarantees](concurrency.md) — exactly-once handoff, the CAS
  pop, missed-slot rescue, and what it cannot do.
- [Migration](migration.md) — what changed and broke compared to
  `strukt1/strukt-queue`.

For a moving target to watch while you build against the listing APIs, run the
activity generator: `php example/generate.php --prefix demo` (see the README's
Testing section).