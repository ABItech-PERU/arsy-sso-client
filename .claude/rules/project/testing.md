---
paths:
  - "src/Testing/**"
  - "tests/**"
  - "phpunit.xml.dist"
---

# Pruebas (arsy/sso-client)

DIRECTIVA: la suite corre en Orchestra Testbench sin app host, y
`src/Testing/` es API pública que usan las pruebas de los satélites.

## Reglas

1. **Suite.** Se corre con el comando de `CLAUDE.md` §2, en la raíz.
   - `tests/TestCase.php` registra `SocialiteServiceProvider` y el
     provider del paquete, y fija una `app.key` aleatoria por ejecución.
   - Pruebas en `tests/Feature/`, espejo de `src/`, con métodos
     `test_<comportamiento>` en inglés y textos en español.
   - Sesión y caché `array` desde `phpunit.xml.dist`; red con
     `Http::fake()`.
2. **Archivos modelo.**
   - Config que arma `register()`:
     `tests/Feature/Providers/SsoClientServiceProviderTest.php`. Testbench
     registra el provider antes de `defineEnvironment()`, así que la
     prueba cambia la config y lo registra de nuevo con `force: true`.
   - Ruta web del paquete:
     `tests/Feature/Http/Controllers/SsoLoginControllerTest.php`, con
     `withSession()` y su cookie, `GenericUser` sin tabla,
     `Socialite::shouldReceive('driver->user')` y `Log::spy()`.
3. **`InMemoryRedis`.** Implementa `Illuminate\Contracts\Redis\Factory`
   para `Redis::swap()`, con una base por conexión.
4. **Comandos cubiertos.** `InMemoryRedisConnection` cubre solo los que
   usa el paquete (`get`, `setex`, `del`, `hset`, `hgetall`, `hdel`,
   `expire`, `ttl`), cada uno con la caducidad que respeta Redis.
5. **Firma estable.** Cambiar la firma de `src/Testing/` rompe las
   pruebas de los satélites y se trata como cambio de API pública
   (`project/architecture.md` regla 7).
