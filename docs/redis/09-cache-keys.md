# 09 — Cache keys

## Why keys matter

The cache key must represent the **complete effective search state**.  
If two different searches share a key, users see the wrong jobs.

## What goes into `JobQuery::cacheKey()`

From `src/Jobs/JobQuery.php` (version prefix `search:v32:`):

- keywords  
- city / bundesland  
- profession  
- work_mode  
- student / junior / graduate / internship / no_experience / minijob  
- employment  
- english / german_level  
- has_salary  
- match_resume  
- posted (days)  
- sort  
- sources  
- companies  

Normalized → JSON → `sha256` → `search:v32:{hash}`.

## Different searches → different keys

```text
Software Tester + Berlin   ≠   Software Tester + Munich
```

City is part of the payload → different hashes → different Redis/MySQL rows.

## Ordering of sources / keywords

Arrays are encoded as JSON. **Order matters** for the hash.  
`JobQuery` should keep sources/keywords in a stable order before hashing (it builds from structured fields). If you add new filter fields, bump the version (`v32` → `v33`) so old blobs are not reused incorrectly.

## Redis full key

```text
{REDIS_PREFIX}jobs:g{generation}:{logicalKey}

Example:
kaamfit:dev:jobs:g3:search:v32:a1b2c3…
kaamfit:dev:jobs:g3:job:arbeitsagentur:12345
```

## Job detail keys

`JobListing::cacheKey()` → `job:{source}:{externalId}`  
Same L1/L2 path via `JobCache::getListing` / `putListing`.

Next: [10-cache-invalidation.md](10-cache-invalidation.md).
