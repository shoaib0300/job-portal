# Redis in KaamFit

Redis is an **in-memory data store**. People use it for caching, fast lookups, counters, queues, and other temporary or high-speed workloads.

In KaamFit we use Redis as an optional **L1 acceleration layer** in front of Job Search — not as the source of truth for jobs.

## MySQL vs Redis (simple mental model)

| | MySQL | Redis |
|---|---|---|
| Role in KaamFit | Persistent application data | Fast temporary / cache data |
| Jobs | `job_listings` (source of truth) | Optional hot copy of ranked search results |
| Survives restart | Yes | Often no (our DDEV Redis runs without AOF/RDB) |
| Speed | Disk-backed, solid | Memory-backed, very fast |
| If it is empty | Bad — you lost data | Fine — rebuild from MySQL / ingest |

```text
External job sources
        ↓
expensive / slow search + ingest
        ↓
MySQL job_listings  ← source of truth
        ↓
MySQL job_search_cache  ← durable hot cache (~15 min search)
        ↓
Redis (optional L1)  ← same payload, even faster repeats
        ↓
User sees Job Search results
```

**Redis is not the source of truth for KaamFit jobs.** If Redis disappears, Job Search still works via MySQL.

## Why KaamFit benefits

1. Repeat searches with the same filters (city, keywords, boards) hit memory instead of decoding large MySQL JSON blobs.
2. Job detail cards that were recently viewed can return instantly.
3. You learn a real production pattern: **cache in front of a database**, with TTL and invalidation.

## Learning roadmap

Read these in order:

1. [01 — Basics](01-basics.md) — what Redis is
2. [02 — Installation (DDEV)](02-installation.md) — how KaamFit runs Redis
3. [03 — CLI](03-cli.md) — `ddev exec -s redis redis-cli`, then `PING` / `SET` / `GET`
4. [04 — Keys and values](04-keys-values.md) — namespacing
5. [05 — Data types](05-data-types.md) — string, hash, list, set, zset
6. [06 — TTL / expiration](06-ttl-expiration.md) — why caches die on purpose
7. [07 — PHP integration](07-php-integration.md) — `KaamFit\Cache\RedisClient`
8. [08 — KaamFit Job Search cache](08-kaamfit-job-cache.md) — end-to-end flow
9. [09 — Cache keys](09-cache-keys.md) — `search:v32:…` and Redis prefixes
10. [10 — Cache invalidation](10-cache-invalidation.md) — generation bump + TTL
11. [11 — Redis vs MySQL](11-redis-vs-mysql.md) — when to use which
12. [12 — Debugging](12-debugging.md) — hits, misses, CLI checks
13. [13 — Production](13-production.md) — hosts, memory, persistence
14. [14 — Exercises](14-exercises.md) — practice tasks

## Code map (after you finish the docs)

| Piece | Path |
|-------|------|
| Redis client | `src/Cache/RedisClient.php` |
| Job cache (MySQL + Redis L1) | `src/Jobs/JobCache.php` |
| Search orchestration | `src/Jobs/JobAggregator.php` |
| Cache key from filters | `src/Jobs/JobQuery::cacheKey()` |
| DDEV Redis service | `.ddev/docker-compose.redis.yaml` |
| Env template | `.env.example` (`REDIS_HOST`, `REDIS_PORT`, `REDIS_PREFIX`) |

Start with [01-basics.md](01-basics.md).
