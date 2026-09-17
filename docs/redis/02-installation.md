# 02 — Installation (KaamFit + DDEV)

This project did **not** ship with Redis historically. Redis was added as an optional DDEV service.

## What exists in this repo

| File | Purpose |
|------|---------|
| `.ddev/docker-compose.redis.yaml` | Redis 7 Alpine container + env for `web` |
| `.env.example` | Documents `REDIS_HOST`, `REDIS_PORT`, `REDIS_PREFIX` |
| `src/Cache/RedisClient.php` | PHP client (optional; no-ops if Redis is down) |

Service name inside Docker: **`redis`**  
Port inside the Docker network: **`6379`**  
(No host port is published — only the `web` container needs Redis.)

From `.ddev/docker-compose.redis.yaml`, the web container receives:

```text
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PREFIX=kaamfit:dev:
```

## Start everything

From the project root:

```bash
ddev start
```

DDEV merges `docker-compose.redis.yaml` automatically.

## Check Redis is up

### From the web container (recommended)

```bash
ddev exec redis-cli -h redis PING
```

Expected:

```text
PONG
```

Interactive:

```bash
ddev exec redis-cli -h redis
```

Then type `PING`, `SET`, `GET`, `QUIT`.

### If `redis-cli` is missing on web

```bash
ddev exec sh -c 'command -v redis-cli || apt-get update && apt-get install -y redis-tools'
```

(Or exec into the Redis container:)

```bash
ddev exec -s redis redis-cli PING
```

## Environment variables

| Variable | DDEV default | Meaning |
|----------|--------------|---------|
| `REDIS_HOST` | `redis` | Hostname of the Redis service |
| `REDIS_PORT` | `6379` | Port |
| `REDIS_PREFIX` | `kaamfit:dev:` | Key namespace for this environment |

To **disable** Redis in PHP (MySQL cache still works): unset `REDIS_HOST` or leave it empty in `.env`.

Copy from `.env.example` into your local `.env` if you want to override DDEV defaults.

## Local vs production

| | Local (DDEV) | Production |
|---|---|---|
| Host | Docker DNS name `redis` | Managed Redis URL / private IP |
| Prefix | `kaamfit:dev:` | `kaamfit:prod:` |
| Persistence | Off (`--save ""`) | Decide with ops |
| Failure mode | App continues without Redis | Same — cache is optional |

## Verify from PHP

After `ddev start`:

```bash
ddev exec php -r 'require "vendor/autoload.php"; require "src/bootstrap.php";
use KaamFit\Cache\RedisClient;
$r = RedisClient::instance();
echo $r->enabled() ? "enabled\n" : "disabled\n";
var_export($r->ping());
echo "\n";'
```

Next: [03-cli.md](03-cli.md).
