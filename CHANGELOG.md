# Changelog

Formato basado en [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/)
y versionado [SemVer](https://semver.org/lang/es/).

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
