# 02 — Installation (KaamFit + DDEV)

This project did **not** ship with Redis historically. Redis was added as an optional DDEV service.

## What exists in this repo

| File | Purpose |
|------|---------|
| `.ddev/docker-compose.redis.yaml` | Redis 7 Alpine container |
| `.ddev/config.yaml` → `web_environment` | `REDIS_HOST` / `REDIS_PORT` / `REDIS_PREFIX` for PHP |
| `.env.example` | Documents the same vars for non-DDEV / production |
| `src/Cache/RedisClient.php` | PHP client (optional; no-ops if Redis is down) |

Service name inside Docker: **`redis`**  
Port inside the Docker network: **`6379`**  
(No host port is published — only containers on the DDEV network need Redis.)

## Start everything

From the project root (**on your host**, not inside `ddev ssh`):

```bash
ddev start
```

DDEV merges `docker-compose.redis.yaml` automatically. After start, `ddev describe` should list a **redis** service.

## Important: where `redis-cli` lives

| Place | Has `redis-cli`? |
|-------|------------------|
| Host machine (`~/www/mnk`) | Usually **no** |
| Web container (`ddev exec` / `ddev ssh`) | **No** — PHP image does not include Redis tools |
| Redis container (`ddev exec -s redis …`) | **Yes** — Alpine Redis image includes `redis-cli` |

So this will fail (what you saw):

```bash
# Wrong — redis-cli is not installed on the web container
ddev exec redis-cli -h redis PING
# bash: redis-cli: command not found
```

## Check Redis is up (correct commands)

Run these from the **project root on the host**:

```bash
ddev exec -s redis redis-cli PING
```

Expected:

```text
PONG
```

Interactive session:

```bash
ddev exec -s redis redis-cli
```

Then type Redis commands (`PING`, `SET`, `GET`, `QUIT`). You do **not** need `-h redis` here — you are already inside the Redis container.

One-shot examples:

```bash
ddev exec -s redis redis-cli SET kaamfit:test "hello"
ddev exec -s redis redis-cli GET kaamfit:test
ddev exec -s redis redis-cli DEL kaamfit:test
```

### Alternative: Docker directly

```bash
docker exec -it ddev-kaamfit-redis redis-cli PING
```

(Replace `kaamfit` with your DDEV project name if different.)

## Environment variables

| Variable | DDEV default | Meaning |
|----------|--------------|---------|
| `REDIS_HOST` | `redis` | Hostname of the Redis service (from PHP / web) |
| `REDIS_PORT` | `6379` | Port |
| `REDIS_PREFIX` | `kaamfit:dev:` | Key namespace for this environment |

These are set via `.ddev/config.yaml` → `web_environment` so the **web** container can reach Redis as hostname `redis`.

To **disable** Redis in PHP (MySQL cache still works): remove or empty `REDIS_HOST` in `web_environment` / `.env`, then `ddev restart`.

## Local vs production

| | Local (DDEV) | Production |
|---|---|---|
| Host | Docker DNS name `redis` | Managed Redis URL / private IP |
| Prefix | `kaamfit:dev:` | `kaamfit:prod:` |
| Persistence | Off (`--save ""`) | Decide with ops |
| Failure mode | App continues without Redis | Same — cache is optional |
| CLI | `ddev exec -s redis redis-cli` | Vendor / cloud console |

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
