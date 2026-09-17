# 13 — Production considerations

## Configuration

| Setting | Suggestion |
|---------|------------|
| `REDIS_HOST` | Private hostname / managed Redis |
| `REDIS_PORT` | Usually 6379 (or TLS port from provider) |
| `REDIS_PREFIX` | `kaamfit:prod:` |
| Memory | Cap with `maxmemory` + `allkeys-lru` for pure cache |
| Persistence | Optional for cache-only; enable if Redis holds queues/sessions later |
| Auth | Require password / ACL on public networks |
| TLS | Use if Redis is off-VPC |

## Security

- Do not expose Redis to the public internet.
- Do not store passwords, session secrets, or PII in Job Search cache blobs (cards are already public-ish job metadata).
- Keep CSRF / auth on application routes; Redis is not a security boundary.

## Operations

- Monitor memory fragmentation and evictions.
- Prefer `SCAN` over `KEYS`.
- Never `FLUSHALL` on shared Redis.
- Deploy app so missing Redis = degraded cache, not downtime.

## Scaling

Multiple web nodes can share one Redis. MySQL `job_search_cache` remains a shared L2 if you keep write-through (current design).

Next: [14-exercises.md](14-exercises.md).
