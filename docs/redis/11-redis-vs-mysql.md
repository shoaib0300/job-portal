# 11 — Redis vs MySQL in KaamFit

## Decision table

| Need | Use |
|------|-----|
| Store jobs permanently | MySQL `job_listings` |
| Deduplicate across boards (`content_key`) | MySQL |
| Ranked search result for 15 minutes | Redis L1 + MySQL `job_search_cache` |
| Survive Redis restart | MySQL still has the blob (or rebuild from listings) |
| Counters / locks / queues (future) | Redis |
| User accounts, applications, resumes | MySQL (never Redis as SoT) |

## Mental picture

```text
MySQL  = library shelves (books stay)
Redis  = desk pile of photocopies (fast, disposable)
```

## Anti-patterns

- Putting `job_listings` only in Redis  
- Treating Redis failure as application failure  
- Caching without TTL  
- Sharing `kaamfit:prod:` and `kaamfit:dev:` prefixes  

Next: [12-debugging.md](12-debugging.md).
