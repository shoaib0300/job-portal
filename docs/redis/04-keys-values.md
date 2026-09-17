# 04 — Keys and values

## The model

```text
key  →  value
```

Examples:

```text
user:123:name          →  Shoaib
job:arbeitsagentur:42  →  {JSON job card}
jobs:search:abc123     →  {JSON ranked listings}
```

Keys are strings. Values can be strings (often JSON), hashes, lists, etc.

## Namespacing

Without prefixes, apps collide:

```text
search:v32:deadbeef   ← who owns this?
```

KaamFit prefixes every Redis key via `REDIS_PREFIX` (default `kaamfit:dev:`).

Full Redis key example:

```text
kaamfit:dev:jobs:g0:search:v32:<sha256>
│          │    │  │
│          │    │  └─ logical JobCache key from JobQuery::cacheKey()
│          │    └─ generation (invalidation epoch)
│          └─ feature area
└─ environment
```

## Environment separation

| Prefix | Use |
|--------|-----|
| `kaamfit:dev:` | Local DDEV |
| `kaamfit:prod:` | Production |

Why it matters:

- A shared Redis must not mix staging and production search blobs.
- You can flush `dev` without touching `prod` (with careful `SCAN` + `DEL`, not `FLUSHALL`).

## Logical vs storage keys

| Layer | Key shape |
|-------|-----------|
| `JobQuery::cacheKey()` | `search:v32:` + sha256 of **normalized filters** |
| MySQL `job_search_cache.query_hash` | Same key, truncated/hashed to ≤80 chars via `JobCache::storageKey()` |
| Redis | `REDIS_PREFIX` + `jobs:g{gen}:` + logical key |

Same search filters → same hash → same cache entry.

Next: [05-data-types.md](05-data-types.md).
