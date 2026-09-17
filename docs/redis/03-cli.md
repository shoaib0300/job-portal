# 03 — Redis CLI

`redis-cli` is **not** installed in the web container. Use the Redis service container.

From the project root on your host:

```bash
ddev exec -s redis redis-cli
```

Or a single command:

```bash
ddev exec -s redis redis-cli PING
```

## Essential commands

Once you are in the interactive CLI (prompt looks like `127.0.0.1:6379>`), type Redis commands **without** a shell prefix.

### `PING`

```text
PING
```

→ `PONG` means the server is alive.

### `SET` / `GET` / `DEL` / `EXISTS`

```text
SET kaamfit:test "hello"
GET kaamfit:test
EXISTS kaamfit:test
DEL kaamfit:test
EXISTS kaamfit:test
```

Expected idea:

1. `SET` stores the value → `OK`
2. `GET` returns `"hello"`
3. `EXISTS` → `(integer) 1`
4. `DEL` removes it
5. `EXISTS` → `(integer) 0`

One-shot from the host (no interactive session):

```bash
ddev exec -s redis redis-cli SET kaamfit:test "hello"
ddev exec -s redis redis-cli GET kaamfit:test
```

### `TTL` / `EXPIRE` / `PTTL`

```text
SET kaamfit:test "hello"
EXPIRE kaamfit:test 60
TTL kaamfit:test
```

`TTL` returns remaining **seconds** (`-1` = no expiry, `-2` = key missing).  
`PTTL` is the same in **milliseconds**.

### `TYPE`

```text
TYPE kaamfit:test
```

→ `string`, `hash`, `list`, `set`, `zset`, …

### `DBSIZE`

Number of keys in the current DB.

### `KEYS` vs `SCAN`

```text
KEYS *
```

Works on tiny DDEV datasets. **Dangerous in production** — can block Redis while scanning millions of keys.

Prefer:

```text
SCAN 0 MATCH kaamfit:dev:* COUNT 20
```

Or from the host:

```bash
ddev exec -s redis redis-cli --scan --pattern 'kaamfit:dev:*'
```

### Dangerous commands

| Command | Effect |
|---------|--------|
| `FLUSHDB` | Deletes **all keys in the current DB** |
| `FLUSHALL` | Deletes **all keys in all DBs** |

Never run these on production unless you fully intend to wipe the cache.

## Common mistakes

| Wrong | Why it fails | Correct |
|-------|--------------|---------|
| `ddev exec redis-cli -h redis PING` | `redis-cli` is not on **web** | `ddev exec -s redis redis-cli PING` |
| `PING` in the host bash | `PING` is a Redis command, not a Linux command | Run it inside `redis-cli` |
| `ddev exec …` inside `ddev ssh` | Nested DDEV CLI is confusing | Exit to host, or run `redis-cli` via `-s redis` from host |

## Exercise

```text
SET kaamfit:test "hello"
GET kaamfit:test
EXISTS kaamfit:test
TTL kaamfit:test
EXPIRE kaamfit:test 30
TTL kaamfit:test
DEL kaamfit:test
```

Explain each line’s output out loud.

Next: [04-keys-values.md](04-keys-values.md).
