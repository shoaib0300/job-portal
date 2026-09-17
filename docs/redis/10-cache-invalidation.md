# 10 — Cache invalidation

## Strategies KaamFit uses

### 1. TTL (passive)

Search keys expire after **900s**. Job keys after **3600s**.  
Even without active invalidation, stale data ages out.

### 2. Generation bump (active, Redis)

On `JobCache::purgeStale()` (called after ingest finalize):

```text
INCR kaamfit:dev:jobs:gen
```

New Redis keys use `jobs:g{N+1}:…`.  
Old `gN` keys are simply unused and die via TTL — **no `KEYS *` wipe**.

### 3. MySQL row delete

`purgeStale` also deletes old `job_search_cache` rows and strips aged listings from search blobs.

### 4. Per-key delete

`JobCache::deleteKey` removes Redis + MySQL for one logical key (expired / empty after age filter).

## What we do *not* do

- `FLUSHALL` on every ingest  
- Caching personalized rankings without considering resume changes carefully (`match_resume` is in the key flag, but resume *content* changes still rely on TTL / gen bump)

## When to invalidate

| Event | Action |
|-------|--------|
| Ingest finished | `purgeStale()` → gen bump + MySQL hygiene |
| Soft TTL exceeded | Miss → rebuild |
| Posted date too old | Drop listing / key |

Next: [11-redis-vs-mysql.md](11-redis-vs-mysql.md).
