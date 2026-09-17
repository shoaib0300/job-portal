# 08 — KaamFit Job Search cache

This is the practical chapter. Read it slowly.

## Existing architecture (audit summary)

Before Redis, KaamFit already had:

1. **`job_listings` (MySQL)** — durable index; filled by `bin/jobs_ingest.php` / `JobsIngest`.
2. **`job_search_cache` (MySQL)** — hot KV for ranked search JSON and job detail JSON (`JobCache`).
3. **File cache** — sidebar stats in `storage/cache/jobs-sidebar-stats.json` (300s).

Redis was **not** present. It is now an **optional L1** in front of `JobCache`, not a replacement for MySQL.

## Target request flow

```text
User
 ↓
Job Search (JobsController)
 ↓
JobQuery (normalize filters)
 ↓
cacheKey = search:v32:{sha256(filters)}
 ↓
JobAggregator::search
 ↓
JobCache::get(cacheKey, SEARCH_TTL=900)
   ├── Redis HIT  → return listings
   ├── Redis MISS
   │     ↓
   │   MySQL job_search_cache HIT → warm Redis → return
   └── both MISS
         ↓
      JobStore::search (MySQL job_listings)
         ↓
      post-filter / dedupe / rank
         ↓
      JobCache::put (Redis + MySQL)
         ↓
      paginate for the user
```

## Classes

| Class | Role |
|-------|------|
| `JobQuery` | Filters + `cacheKey()` |
| `JobAggregator` | Orchestrates search / live ingest detail |
| `JobStore` | Source-of-truth CRUD on `job_listings` |
| `JobCache` | MySQL + Redis cache API |
| `RedisClient` | Optional Redis transport |
| Sources under `src/Jobs/Sources/` | Board adapters |

## Important rule

**DB is source of truth; cache only speeds repeat filters.**  
(`JobAggregator::search` comment in code.)

Ingest still writes MySQL. Redis never owns job identity.

Next: [09-cache-keys.md](09-cache-keys.md).
