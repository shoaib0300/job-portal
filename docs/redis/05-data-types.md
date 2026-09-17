# 05 — Redis data types

## String

**What:** One blob of bytes / text per key.  
**Why:** Simplest cache value (JSON, HTML fragment, counter via `INCR`).  
**Commands:** `SET`, `GET`, `INCR`  
**KaamFit:** Job Search cache stores JSON strings.  
**When not:** Structured fields you update independently → use Hash.

## Hash

**What:** Map of field → value under one key.  
**Why:** Object-like records without JSON encode/decode for every field.  
**Commands:** `HSET`, `HGET`, `HGETALL`  
**KaamFit example:** Could store ingest progress fields; today we mostly use strings.  
**When not:** Nested documents → JSON string is fine.

## List

**What:** Ordered sequence.  
**Why:** Queues, recent items.  
**Commands:** `LPUSH`, `RPUSH`, `LRANGE`, `LPOP`  
**KaamFit example:** Future job-ingest queue.  
**When not:** Need uniqueness → Set; need score ranking → Sorted Set.

## Set

**What:** Unordered unique members.  
**Why:** Tags, “seen IDs”, membership tests.  
**Commands:** `SADD`, `SMEMBERS`, `SISMEMBER`  
**KaamFit example:** Track external IDs already processed in a run.  
**When not:** Need order or scores.

## Sorted Set (ZSet)

**What:** Unique members with a score; ordered by score.  
**Why:** Leaderboards, time-ordered feeds.  
**Commands:** `ZADD`, `ZRANGE`, `ZREVRANGE`  
**KaamFit example:** Rank companies by match score (future).  
**When not:** Simple list without scores.

## Streams (intro)

**What:** Append-only log of messages.  
**Why:** Event pipelines, consumer groups.  
**KaamFit:** Not used yet. Learn later when you need reliable queues.

## Exercise

```text
SET kaamfit:demo:str "hi"
HSET kaamfit:demo:hash name Shoaib role learner
HGET kaamfit:demo:hash name
SADD kaamfit:demo:set a b a
SMEMBERS kaamfit:demo:set
LPUSH kaamfit:demo:list 1 2 3
LRANGE kaamfit:demo:list 0 -1
```

Next: [06-ttl-expiration.md](06-ttl-expiration.md).
