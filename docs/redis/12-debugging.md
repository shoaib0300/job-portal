# 12 — Debugging

## Is Redis reachable?

```bash
ddev exec -s redis redis-cli PING
```

## Is PHP connected?

```bash
ddev exec php -r 'require "vendor/autoload.php"; require "src/bootstrap.php";
$r = KaamFit\Cache\RedisClient::instance();
echo ($r->enabled() ? "yes" : "no") . " gen=" . $r->jobsGeneration() . "\n";'
```

## Inspect keys (safe)

```bash
ddev exec -s redis redis-cli --scan --pattern 'kaamfit:dev:jobs:*'
```

Or interactively (`ddev exec -s redis redis-cli`):

```text
SCAN 0 MATCH kaamfit:dev:jobs:* COUNT 50
```

## Hit vs miss while browsing Jobs

1. Open Jobs with filters.  
2. Note response time.  
3. Reload same URL within 15 minutes — should be faster if Redis/MySQL cache warm.  
4. Watch Redis:

```text
MONITOR
```

(noisy; Ctrl+C to stop). Prefer `SCAN` + `GET` on a known key.

## Generation

```text
GET kaamfit:dev:jobs:gen
```

After ingest / `purgeStale`, this number increases. New cache writes use the new generation.

## Common problems

| Symptom | Check |
|---------|-------|
| Always miss | `REDIS_HOST` empty? Redis container down? |
| Stale jobs > 15 min | Unexpected — TTL or gen bump broken |
| App error on search | Should not happen; Redis is optional — check PHP logs for other causes |

Next: [13-production.md](13-production.md).
