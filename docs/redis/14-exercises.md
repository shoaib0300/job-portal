# 14 — Exercises

## A. CLI warm-up

1. `ddev start`
2. `ddev exec -s redis redis-cli PING`
3. Set a key with 30s TTL; watch `TTL` decrease; confirm deletion.

## B. Prefix awareness

1. `SET` a key **without** going through PHP.
2. Confirm PHP `RedisClient` does **not** see it unless you include `kaamfit:dev:` manually in CLI — PHP adds the prefix for you.

## C. Job Search round-trip

1. Ensure Redis is enabled (`REDIS_HOST=redis`).
2. Search Jobs with a distinctive city filter.
3. `SCAN` for `kaamfit:dev:jobs:*search*`.
4. Reload the same search — expect a cache hit path (Redis or MySQL).
5. Run ingest or call something that triggers `JobCache::purgeStale`, then check `jobs:gen`.

## D. Failure mode

1. Stop Redis: `ddev stop` then temporarily rename `.ddev/docker-compose.redis.yaml` and `ddev start`, **or** unset `REDIS_HOST` in `.env`.
2. Confirm Job Search still works.
3. Restore Redis.

## E. Explain in your own words

Write five sentences:

1. What Redis stores in KaamFit.  
2. What MySQL stores.  
3. What a cache key contains.  
4. Why TTL exists.  
5. What happens on a Redis outage.

You are done with the Redis learning path when you can answer E without looking at the docs.
