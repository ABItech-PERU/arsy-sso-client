---
paths:
  - "src/Testing/**"
---

# Pruebas (arsy/sso-client)

DIRECTIVA: `src/Testing/` es API pública que usan las pruebas de los
satélites, así que cambia con el mismo cuidado que el resto del paquete.

## Reglas

1. **`InMemoryRedis`.** Implementa `Illuminate\Contracts\Redis\Factory`
   para `Redis::swap()`, con una base por conexión.
2. **Comandos cubiertos.** `InMemoryRedisConnection` cubre solo los que
   usa el paquete (`get`, `setex`, `del`, `hset`, `hgetall`, `hdel`,
   `expire`, `ttl`), cada uno con la caducidad que respeta Redis.
3. **Firma estable.** Cambiar la firma de estas clases rompe las pruebas
   de los satélites y se trata como cambio de API pública
   (`project/architecture.md` regla 7).
