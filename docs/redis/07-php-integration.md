# 07 — PHP integration

## Flow

```text
PHP (JobCache)
   ↓
KaamFit\Cache\RedisClient
   ↓
Redis server (host REDIS_HOST)
```

## Library choice in KaamFit

Composer stays dependency-light (`composer.json` has no Predis).

`RedisClient`:

1. Uses **`ext-redis`** (`\Redis`) when the extension is installed.
2. Otherwise speaks **RESP over TCP** with a tiny built-in client.
3. If `REDIS_HOST` is empty or connect fails → **disabled**; callers treat cache as optional.

No second Redis library was added.

## Connection

Env:

```text
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PREFIX=kaamfit:dev:
```

```php
use KaamFit\Cache\RedisClient;

$redis = RedisClient::instance();
if (!$redis->enabled()) {
    // MySQL path only
}
```

## Set / get with TTL

Conceptually:

```php
$redis->set('jobs:demo', json_encode(['ok' => true]), 60);
$raw = $redis->get('jobs:demo');
$data = $raw !== null ? json_decode($raw, true) : null;
```

Keys you pass to `get`/`set` are **relative** — the client prepends `REDIS_PREFIX`.

## Serialization

KaamFit stores **JSON strings** (same payloads as MySQL `job_search_cache`).  
Always `json_encode` / `json_decode` with error checks.

## Error handling

- Connect timeouts are short (~0.35s).
- Failures null out the connection; later calls no-op.
- Job Search must never 500 because Redis is down.

## Cache hit vs miss

| Term | Meaning |
|------|---------|
| Hit | `GET` returned a value → serve it |
| Miss | `null` → load MySQL / compute → `SET` |

In `JobCache::get`:

1. Redis GET  
2. On miss → MySQL `job_search_cache`  
3. On MySQL hit → warm Redis for next time  

Next: [08-kaamfit-job-cache.md](08-kaamfit-job-cache.md).
