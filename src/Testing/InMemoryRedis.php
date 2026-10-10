<?php

namespace Arsy\SSOClient\Testing;

use Illuminate\Contracts\Redis\Factory;

/**
 * Redis en memoria para tests sin servidor (CI): `Redis::swap(new InMemoryRedis)`
 * antes de la primera petición. Una base aislada por nombre de conexión.
 */
class InMemoryRedis implements Factory
{
    /** @var array<string, InMemoryRedisConnection> */
    private array $connections = [];

    public function connection($name = null): InMemoryRedisConnection
    {
        $name = $name ?: 'default';

        return $this->connections[$name] ??= new InMemoryRedisConnection($name);
    }
}
