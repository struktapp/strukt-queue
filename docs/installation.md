# Installation

## Requirements

- PHP 8.2+
- the `memcached` PHP extension (the `libmemcached`-based one, not
  `ext-memcache`)
- a reachable memcached server, default `127.0.0.1:11211`

Check the extension:

```bash
php -m | grep memcached
```

## Composer

```bash
composer require strukt/queue
```

The package pulls in `strukt/base` (the `Env` class and helper registry),
`strukt/console` (the command runtime), and `strukt/process` (the worker-pool
runner used by the supervised example) automatically. The `kqueue` binary is
linked into `vendor/bin/kqueue`.

## Configuration

The `Queue` constructor accepts an options array:

| Option | Environment override | Default | Purpose |
| --- | --- | --- | --- |
| `host` | `memcached_host` | `127.0.0.1` | Memcached server address. |
| `port` | `memcached_port` | `11211` | Memcached server port. |
| `ttl` | `memcached_ttl` | `0` | Item expiry in seconds (`0` = never). |
| `prefix` | — | `queue` | Key namespace for this application. |
| `username` | `memcached_username` | — | SASL username (optional). |
| `password` | `memcached_password` | — | SASL password (optional). |

Environment values are read through `Strukt\Env::withFile()` when loaded, so a
`.env` file from the legacy setup keeps working:

```php
Strukt\Env::withFile('.env');   // MEMCACHED_HOST=127.0.0.1 etc.
$queue = new Strukt\Queue();
```

Explicit options always win over environment values.

## Running the tests

The suite talks to a real memcached. It needs a live server on the default
port and the `pcntl` extension for the fork-based concurrency tests:

```bash
composer install
composer test
```

Each test run uses a random key prefix and leaves nothing behind.