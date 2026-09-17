# 06 — TTL and expiration

## Why caches expire

A cache that never dies becomes a **stale lie**. Job boards change; ingest refreshes MySQL. Redis must forget old ranked lists.

KaamFit TTLs (in `JobCache`):

| Kind | Constant | Seconds | ≈ |
|------|----------|---------|---|
| Search results | `JobCache::SEARCH_TTL` | 900 | 15 minutes |
| Single job blob | `JobCache::JOB_TTL` | 3600 | 1 hour |

## Commands

```text
SET kaamfit:test "hello"
EXPIRE kaamfit:test 60
TTL kaamfit:test
PTTL kaamfit:test
```

Or set TTL atomically:

```text
SET kaamfit:test "hello" EX 60
```

PHP (`RedisClient::set`) uses `SET … EX` when `$ttlSeconds > 0`.

## What happens when TTL hits 0

Redis **deletes** the key. Next `GET` is a miss → KaamFit rebuilds from MySQL `job_search_cache` / `job_listings`.

## Freshness vs performance

| Short TTL | Long TTL |
|-----------|----------|
| Fresher results | Fewer rebuilds |
| More MySQL / aggregator work | Risk of stale cards |

15 minutes is a deliberate compromise for ranked Job Search lists.

Next: [07-php-integration.md](07-php-integration.md).
