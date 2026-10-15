# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/)
y versionado [SemVer](https://semver.org/lang/es/).

## [Sin publicar]

### Cambiado

- **Incompatible.** `SSO_AUTO_LOGIN_METHOD` vale `oauth` por defecto, el
  método que pide account; quien dependía de `cookie` sin fijarlo debe
  ponerlo en su `.env`.
- **Incompatible.** Los eventos del paquete ya no usan el trait
  `Dispatchable` de `laravel/framework`; se disparan con `event(new …)` y
  ya no traen `::dispatch()`.
- `composer.json` declara los `illuminate/*` que usa `src/` y sugiere
  `inertiajs/inertia-laravel` y `laravel/sanctum`.
- **Incompatible.** Solo se leen las variables `SSO_*`; los alias `ARSY_*`
  ya no sirven.
- **Incompatible.** `POST /api/auth/token` acepta solo la forma v1 de
  `/api/user`: sin `id` en la raíz responde error, y el nombre sale de
  `first_name` y `last_name`, nunca de `name`.

### Añadido

- `routes.auth` (`SSO_AUTH_ROUTES_ENABLED`, `true` por defecto) apaga
  `GET /login` y `POST /logout` cuando el satélite trae sus propias rutas
  de auth.
- Suite propia con Orchestra Testbench y PHPUnit (`composer test`).
- Comando `arsy-sso:link-user {email} {sso_id}` que vincula a mano una
  cuenta local sin `sso_id` con su usuario de account y lo deja en el log.

### Corregido

- `POST /api/auth/token` con un modelo de usuario sin `HasApiTokens`
  responde un error en español antes de llamar a account; ya no crea al
  usuario para fallar después en `createToken()`.
- El `redirect` de OAuth se arma con `config('app.url')`; con
  `config:cache` quedaba relativo (`/auth/callback`).
- `POST /logout` invalida la sesión y regenera el token CSRF; la sesión
  anterior y su token dejan de servir.
- Un fallo del callback OAuth muestra un mensaje fijo y deja la excepción
  en el log; el usuario ya no ve su detalle interno.

### Seguridad

- **Incompatible; sale en 2.0.0.** El login (callback OAuth, cookie
  `ssotoken` y `POST /api/auth/token`) identifica al usuario solo por
  `sso_id`. Antes tomaba por su correo una
  cuenta local de otra persona; ahora un correo ya usado se rechaza y la
  cuenta se vincula con `arsy-sso:link-user`. El canje de token exige el
  `id` del usuario.

## [1.2.0] - 2026-10-10

### Añadido

- Índice de sesiones por usuario en Redis (`SsoSessionIndex`): hash
  `sso:user_sessions:{user_id}` en la conexión de sesiones, alimentado en
  cada petición autenticada y al cerrar sesión (`SessionIndexSubscriber`);
  caduca con `session.lifetime`.
- `Arsy\SSOClient\Testing\InMemoryRedis` para probar el índice y el cierre
  de sesión único sin servidor Redis.

### Cambiado

- El cierre de sesión único por webhook exige `SESSION_DRIVER=redis`:
  destruye las sesiones del índice con el handler de sesiones. Ya no
  consulta la tabla `sessions`; con otro driver solo dispara
  `SsoUserLoggedOutViaWebhook`.

## [1.1.2] - 2026-10-06

### Seguridad

- Auto-login `cookie`: falla cerrado si `SSO_COOKIE_SECRET` está vacío.
  Antes el HMAC se calculaba con clave vacía y la cookie `ssotoken` podía
  falsificarse para iniciar sesión como cualquier usuario.

## [1.1.1] - 2026-10-05

### Corregido

- Auto-login `oauth`: el salto silencioso conserva la página visitada
  (`url.intended`); antes el usuario aterrizaba en `redirect_after_login`.

## [1.1.0] - 2026-09-30

### Añadido

- Macro `Http::arsyAccount(array $scopes)` para llamadas servidor-a-servidor
  a la Central con token OAuth2 `client_credentials` (cacheado cifrado,
  renovación y reintento automático ante `401`).
- Servicio `AccountServiceToken` y configuración `service_client.timeout`
  (`SSO_SERVICE_TIMEOUT`).

### Cambiado

- `POST /api/auth/token` pasa a ser opt-in (`routes.token_exchange`,
  `SSO_TOKEN_EXCHANGE_ENABLED`, desactivado por defecto): exigía Sanctum y
  columnas de token que la mayoría de satélites no tiene.

## [1.0.0]

- Versión inicial: login OAuth2, auto-login híbrido, webhooks SSO y eventos.
