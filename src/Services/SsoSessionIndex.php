<?php

namespace Arsy\SSOClient\Services;

use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Session;

/**
 * Índice por usuario de sus sesiones locales en Redis, para el cierre de
 * sesión único sin recorrer todas las sesiones.
 *
 * Hash `sso:user_sessions:{user_id}`: id de sesión local => id de sesión de
 * la Central ('' si no vino del callback OAuth). Vive en la conexión de las
 * sesiones y caduca con `session.lifetime`, renovado en cada petición.
 */
class SsoSessionIndex
{
    private const KEY_PREFIX = 'sso:user_sessions:';

    public function enabled(): bool
    {
        return config('session.driver') === 'redis';
    }

    public function track(int|string $userId, string $sessionId, ?string $idpSessionId): void
    {
        $key = $this->key($userId);

        $this->connection()->hset($key, $sessionId, $idpSessionId ?? '');
        $this->connection()->expire($key, $this->ttlSeconds());
    }

    public function forget(int|string $userId, string $sessionId): void
    {
        $this->connection()->hdel($this->key($userId), $sessionId);
    }

    /**
     * Sin `$idpSessionId` destruye todas las sesiones del usuario.
     *
     * @return int sesiones destruidas
     */
    public function revoke(int|string $userId, ?string $idpSessionId = null): int
    {
        $key = $this->key($userId);

        $sessionIds = collect($this->connection()->hgetall($key) ?: [])
            ->filter(fn (string $idp) => $idpSessionId === null || $idp === $idpSessionId)
            ->keys()
            ->map(fn (int|string $sessionId) => (string) $sessionId);

        $handler = Session::getHandler();
        $sessionIds->each(fn (string $sessionId) => $handler->destroy($sessionId));

        if ($idpSessionId === null) {
            $this->connection()->del($key);
        } elseif ($sessionIds->isNotEmpty()) {
            $this->connection()->hdel($key, ...$sessionIds->all());
        }

        return $sessionIds->count();
    }

    private function key(int|string $userId): string
    {
        return self::KEY_PREFIX.$userId;
    }

    private function ttlSeconds(): int
    {
        return (int) config('session.lifetime', 120) * 60;
    }

    private function connection(): Connection
    {
        return Redis::connection(config('session.connection'));
    }
}
