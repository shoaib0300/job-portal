# 01 — Redis basics

## What is Redis?

**Redis** = **RE**mote **D**ictionary **S**erver.

It keeps data in **RAM** (memory). Reading from RAM is much faster than reading from disk. That is why Redis is popular for **caching**.

Think of it like a giant shared whiteboard of named slots:

```text
name          →  "Shoaib"
jobs:search:1 →  { ... JSON of job cards ... }
```

Each slot has a **key** (the name) and a **value** (the content).

## Key ideas

### In-memory data store

Data lives primarily in the Redis process memory. Fast. Limited by how much RAM you give Redis.

### Key → value

Almost everything starts with: “look up this key, get this value.”

### Why memory is fast

CPU can access RAM in nanoseconds; disks and networks are slower. Caching stores answers you already computed so the next request skips expensive work.

### Persistence options

Redis *can* write snapshots (RDB) or an append-only log (AOF) to disk. KaamFit’s DDEV Redis is configured **without** persistence on purpose — it is a disposable cache. Production may enable persistence for other Redis uses, but Job Search still treats Redis as optional.

### Cache vs database

| Cache | Database |
|-------|----------|
| OK to lose | Must not lose |
| Short TTL | Long-lived rows |
| Speed | Correctness + history |

### Redis server

The long-running process that stores keys (`redis-server`).

### Redis client

Anything that talks to the server:

- `redis-cli` (terminal)
- PHP (`KaamFit\Cache\RedisClient` or `ext-redis`)

### Redis CLI

Interactive tool:

```bash
redis-cli
```

Example session:

```text
127.0.0.1:6379> SET name "Shoaib"
OK
127.0.0.1:6379> GET name
"Shoaib"
```

**What happened?**

1. `SET name "Shoaib"` stored the string `Shoaib` under key `name`.
2. `GET name` asked Redis for that key.
3. Redis returned `"Shoaib"`.

## Exercise

1. Start Redis (see [02-installation.md](02-installation.md)).
2. Run `PING` — expect `PONG`.
3. `SET learn:redis "hello"` then `GET learn:redis`.

Next: [02-installation.md](02-installation.md).
