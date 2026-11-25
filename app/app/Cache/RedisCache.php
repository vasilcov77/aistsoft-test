<?php

declare(strict_types=1);

namespace App\Cache;

use DateInterval;
use Psr\SimpleCache\CacheInterface;
use Redis;
use Throwable;

/**
 * PSR-16 поверх Valkey. Любая ошибка — cache miss (fail-open).
 */
final class RedisCache implements CacheInterface
{
    private ?Redis $redis = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly ?string $password,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        try {
            $value = $this->connection()?->get($key);
            $decoded = is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : null;

            return is_array($decoded) ? $decoded : $default;
        } catch (Throwable $exception) {
            error_log('cache get failed: ' . $exception->getMessage());

            return $default;
        }
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        try {
            $redis = $this->connection();
            if ($redis === null) {
                return false;
            }

            $payload = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $seconds = $this->ttlSeconds($ttl);

            return (bool) ($seconds > 0 ? $redis->setex($key, $seconds, $payload) : $redis->set($key, $payload));
        } catch (Throwable $exception) {
            error_log('cache set failed: ' . $exception->getMessage());

            return false;
        }
    }

    public function delete(string $key): bool
    {
        try {
            return (bool) $this->connection()?->del($key);
        } catch (Throwable) {
            return false;
        }
    }

    public function clear(): bool
    {
        try {
            return (bool) $this->connection()?->flushDB();
        } catch (Throwable) {
            return false;
        }
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[(string) $key] = $this->get((string) $key, $default);
        }

        return $result;
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        $ok = true;
        foreach ($values as $key => $value) {
            $ok = $this->set((string) $key, $value, $ttl) && $ok;
        }

        return $ok;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        $ok = true;
        foreach ($keys as $key) {
            $ok = $this->delete((string) $key) && $ok;
        }

        return $ok;
    }

    public function has(string $key): bool
    {
        try {
            return (bool) $this->connection()?->exists($key);
        } catch (Throwable) {
            return false;
        }
    }

    private function connection(): ?Redis
    {
        if ($this->redis !== null) {
            return $this->redis;
        }

        $redis = new Redis();
        if (!@$redis->connect($this->host, $this->port, 0.5)) {
            return null;
        }

        $redis->setOption(Redis::OPT_READ_TIMEOUT, 0.5);

        if ($this->password !== null) {
            @$redis->auth($this->password);
        }

        return $this->redis = $redis;
    }

    private function ttlSeconds(null|int|DateInterval $ttl): int
    {
        if ($ttl instanceof DateInterval) {
            $now = new \DateTimeImmutable();

            return max(0, $now->add($ttl)->getTimestamp() - $now->getTimestamp());
        }

        return max(0, (int) $ttl);
    }
}
