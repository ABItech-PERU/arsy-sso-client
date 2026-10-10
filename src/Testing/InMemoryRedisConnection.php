<?php

namespace Arsy\SSOClient\Testing;

use BadMethodCallException;
use Closure;
use Illuminate\Redis\Connections\Connection;

/**
 * Comandos que usan las sesiones (RedisStore) y SsoSessionIndex. La
 * caducidad sigue a `now()`, así que respeta `Carbon::setTestNow()`.
 */
class InMemoryRedisConnection extends Connection
{
    /** @var array<string, string|array<string, string>> */
    private array $data = [];

    /** @var array<string, int> */
    private array $expiresAt = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function get(string $key): ?string
    {
        $value = $this->live($key);

        return is_string($value) ? $value : null;
    }

    public function setex(string $key, int $seconds, string|int|float $value): bool
    {
        $this->data[$key] = (string) $value;
        $this->expiresAt[$key] = now()->getTimestamp() + $seconds;

        return true;
    }

    public function del(string ...$keys): int
    {
        $deleted = collect($keys)->filter(fn (string $key) => $this->live($key) !== null)->count();

        foreach ($keys as $key) {
            unset($this->data[$key], $this->expiresAt[$key]);
        }

        return $deleted;
    }

    public function hset(string $key, string $field, string $value): int
    {
        $hash = $this->live($key) ?? [];
        $isNew = ! array_key_exists($field, $hash);
        $hash[$field] = $value;
        $this->data[$key] = $hash;

        return (int) $isNew;
    }

    /**
     * @return array<string, string>
     */
    public function hgetall(string $key): array
    {
        $hash = $this->live($key);

        return is_array($hash) ? $hash : [];
    }

    public function hdel(string $key, string ...$fields): int
    {
        $hash = $this->hgetall($key);
        $deleted = count(array_intersect_key($hash, array_flip($fields)));
        $remaining = array_diff_key($hash, array_flip($fields));

        if ($remaining === []) {
            $this->del($key);
        } else {
            $this->data[$key] = $remaining;
        }

        return $deleted;
    }

    public function expire(string $key, int $seconds): bool
    {
        if ($this->live($key) === null) {
            return false;
        }

        $this->expiresAt[$key] = now()->getTimestamp() + $seconds;

        return true;
    }

    /**
     * Segundos restantes; -1 sin caducidad, -2 si no existe.
     */
    public function ttl(string $key): int
    {
        if ($this->live($key) === null) {
            return -2;
        }

        return isset($this->expiresAt[$key]) ? $this->expiresAt[$key] - now()->getTimestamp() : -1;
    }

    public function command($method, array $parameters = [])
    {
        throw new BadMethodCallException("InMemoryRedis no implementa [{$method}].");
    }

    public function createSubscription($channels, Closure $callback, $method = 'subscribe')
    {
        throw new BadMethodCallException('InMemoryRedis no implementa suscripciones.');
    }

    private function live(string $key): string|array|null
    {
        if (isset($this->expiresAt[$key]) && $this->expiresAt[$key] <= now()->getTimestamp()) {
            unset($this->data[$key], $this->expiresAt[$key]);
        }

        return $this->data[$key] ?? null;
    }
}
