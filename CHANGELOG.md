# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/)
y versionado [SemVer](https://semver.org/lang/es/).

## [Sin publicar]

### Añadido

- Suite propia con Orchestra Testbench y PHPUnit (`composer test`).
- Comando `arsy-sso:link-user {email} {sso_id}` que vincula a mano una
  cuenta local sin `sso_id` con su usuario de account y lo deja en el log.

### Corregido

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
