<?php

declare(strict_types=1);

namespace KaamFit\Cache;

/**
 * Tiny Redis client (RESP) for KaamFit caching.
 * Uses ext-redis when available; otherwise a minimal TCP RESP client.
 * If Redis is unreachable, all methods no-op / return null (cache is optional).
 */
final class RedisClient
{
    private static ?self $instance = null;
    private static bool $disabled = false;

    /** @var \Redis|resource|null */
    private mixed $conn = null;
    private bool $isExt = false;
    private string $prefix;

    private function __construct()
    {
        $this->prefix = (string) (getenv('REDIS_PREFIX') ?: 'kaamfit:dev:');
        $host = (string) (getenv('REDIS_HOST') ?: '');
        $port = (int) (getenv('REDIS_PORT') ?: 6379);
        if ($host === '' || self::$disabled) {
            return;
        }
        try {
            if (class_exists(\Redis::class)) {
                $r = new \Redis();
                if (@$r->connect($host, $port, 0.35)) {
                    $r->setOption(\Redis::OPT_READ_TIMEOUT, 0.35);
                    $this->conn = $r;
                    $this->isExt = true;
                    return;
                }
            }
            $errno = 0;
            $errstr = '';
            $fp = @stream_socket_client(
                "tcp://{$host}:{$port}",
                $errno,
                $errstr,
                0.35
            );
            if (is_resource($fp)) {
                stream_set_timeout($fp, 0, 350000);
                $this->conn = $fp;
            }
        } catch (\Throwable) {
            $this->conn = null;
        }
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    /** Force-disable for tests / emergencies. */
    public static function disable(): void
    {
        self::$disabled = true;
        self::$instance = null;
    }

    public function enabled(): bool
    {
        return $this->conn !== null;
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function ping(): bool
    {
        $r = $this->command(['PING']);
        return $r === 'PONG' || $r === true;
    }

    public function get(string $key): ?string
    {
        $r = $this->command(['GET', $this->prefix . $key]);
        return is_string($r) ? $r : null;
    }

    public function set(string $key, string $value, int $ttlSeconds = 0): bool
    {
        if ($ttlSeconds > 0) {
            $r = $this->command(['SET', $this->prefix . $key, $value, 'EX', (string) $ttlSeconds]);
        } else {
            $r = $this->command(['SET', $this->prefix . $key, $value]);
        }
        return $r === true || $r === 'OK';
    }

    public function del(string $key): bool
    {
        $r = $this->command(['DEL', $this->prefix . $key]);
        return is_int($r) ? $r > 0 : (bool) $r;
    }

    public function exists(string $key): bool
    {
        $r = $this->command(['EXISTS', $this->prefix . $key]);
        return (int) $r > 0;
    }

    public function ttl(string $key): int
    {
        return (int) $this->command(['TTL', $this->prefix . $key]);
    }

    public function incr(string $key): int
    {
        return (int) $this->command(['INCR', $this->prefix . $key]);
    }

    /** Bump job-cache generation so old Redis search keys become unused. */
    public function bumpJobsGeneration(): int
    {
        return $this->incr('jobs:gen');
    }

    public function jobsGeneration(): int
    {
        $v = $this->get('jobs:gen');
        return $v !== null && ctype_digit($v) ? (int) $v : 0;
    }

    /** @param list<string|int> $parts */
    private function command(array $parts): mixed
    {
        if ($this->conn === null) {
            return null;
        }
        try {
            if ($this->isExt && $this->conn instanceof \Redis) {
                $cmd = strtoupper((string) $parts[0]);
                $args = array_slice($parts, 1);
                return match ($cmd) {
                    'PING' => $this->conn->ping(),
                    'GET' => $this->conn->get((string) $args[0]),
                    'SET' => count($args) >= 4 && strtoupper((string) $args[2]) === 'EX'
                        ? $this->conn->setex((string) $args[0], (int) $args[3], (string) $args[1])
                        : $this->conn->set((string) $args[0], (string) $args[1]),
                    'DEL' => $this->conn->del((string) $args[0]),
                    'EXISTS' => $this->conn->exists((string) $args[0]),
                    'TTL' => $this->conn->ttl((string) $args[0]),
                    'INCR' => $this->conn->incr((string) $args[0]),
                    default => null,
                };
            }
            if (!is_resource($this->conn)) {
                return null;
            }
            $payload = '*' . count($parts) . "\r\n";
            foreach ($parts as $p) {
                $s = (string) $p;
                $payload .= '$' . strlen($s) . "\r\n" . $s . "\r\n";
            }
            fwrite($this->conn, $payload);
            return $this->readResp();
        } catch (\Throwable) {
            $this->conn = null;
            return null;
        }
    }

    private function readResp(): mixed
    {
        if (!is_resource($this->conn)) {
            return null;
        }
        $line = fgets($this->conn);
        if ($line === false) {
            return null;
        }
        $type = $line[0] ?? '';
        $rest = substr($line, 1);
        return match ($type) {
            '+' => rtrim($rest, "\r\n"),
            '-' => null,
            ':' => (int) $rest,
            '$' => $this->readBulk((int) $rest),
            '*' => $this->readArray((int) $rest),
            default => null,
        };
    }

    private function readBulk(int $len): ?string
    {
        if ($len < 0 || !is_resource($this->conn)) {
            return null;
        }
        $data = '';
        $remaining = $len + 2; // include CRLF
        while ($remaining > 0) {
            $chunk = fread($this->conn, $remaining);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $data .= $chunk;
            $remaining -= strlen($chunk);
        }
        return substr($data, 0, $len);
    }

    /** @return list<mixed>|null */
    private function readArray(int $count): ?array
    {
        if ($count < 0) {
            return null;
        }
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = $this->readResp();
        }
        return $out;
    }
}
