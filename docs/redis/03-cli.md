# 03 — Redis CLI

Open a CLI:

```bash
ddev exec redis-cli -h redis
```

## Essential commands

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

Repeat with the returned cursor until it is `0`.

### Dangerous commands

| Command | Effect |
|---------|--------|
| `FLUSHDB` | Deletes **all keys in the current DB** |
| `FLUSHALL` | Deletes **all keys in all DBs** |

Never run these on production unless you fully intend to wipe the cache.

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
