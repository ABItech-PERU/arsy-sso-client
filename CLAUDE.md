# CLAUDE.md

## 1. Proyecto

**arsy/sso-client.** Paquete Composer que conecta un satélite Arsy con
account, el IdP OAuth2 con Passport, vía Socialite y
`socialiteproviders/laravelpassport`. Da login y auto-login, webhooks de
cierre de sesión único y cliente servidor a servidor.

- Lo instalan `arsy-academy-app`, `arsy-coworker-app` y
  `arsy-innova-app` con `^1.2` desde el VCS. El contrato lo fija account
  en `../../arsy-account-app/docs/satelites.md`.
- Evoluciona a `arsy/platform-client`; tareas PC-01 a PC-09 en
  `../../arsy-architecture/tareas/platform-client.md`.
- Contrato v1 sin capas de compatibilidad hasta producción (ADR 0005),
  aplicado en `project/architecture.md` regla 7.
- Idioma (excepción de `core/04` regla 3). Clases, métodos, variables,
  tablas, columnas y rutas en inglés; mensajes, comentarios, docs y
  commits en español.

## 2. Comandos

```bash
php -l src/Services/SsoAuthenticationService.php    # cada archivo tocado
composer validate
# suite del paquete, desde WSL en la raíz del repo
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app \
  composer:2 install
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app \
  --entrypoint php composer:2 vendor/bin/phpunit
# desde arsy-academy-app, contra su vendor/arsy/sso-client
docker exec arsy_academy_app php artisan test --filter=Sso
```

## 3. Puertas de calidad

Sin CI ni linters. Antes de entregar, estas cuatro.

1. `php -l` sin errores en cada archivo tocado.
2. `composer validate` sin avisos.
3. La suite del paquete (`tests/`, Orchestra Testbench) en verde, con la
   prueba del cambio según `project/testing.md`.
4. La suite SSO de academy (`tests/Feature/Sso/`, callback, webhooks,
   auto-login e índice de sesiones) en verde cuando el cambio la
   alcanza. Corre contra el paquete del `vendor/` de academy (`^1.2`
   desde el VCS); su contenedor solo monta el repo de academy
   (`docker/compose/docker-compose.dev.yml:20`), así que un repositorio
   `path` hacia `_composer/` no se ve dentro.

Lo que quede sin probar se declara al entregar, con el comportamiento
concreto (p. ej. «auto-login `cookie` sin ejecutar»); no se simula una
prueba.

## 4. Lo que sorprende

1. **El Composer de Windows no trabaja bien sobre `\\wsl.localhost`.**
   `composer.bat` falla porque `cmd.exe` no acepta rutas UNC, y
   `php composer.phar` funciona pero puede dejar un archivo `NUL` en la
   raíz, que se borra y nunca se commitea. El PHP de Windows tampoco
   abre `vendor/autoload.php` por esa ruta; la suite corre con Docker
   desde WSL (§2).
2. **El paquete trae `/login` y `/logout` con nombre `login` y
   `logout`.** Chocan con las de auth del satélite, que debe quitar las
   suyas (`project/domain/sso.md` regla 5).
3. **El cierre de sesión único depende del driver de sesión.** Sin
   `SESSION_DRIVER=redis` el webhook solo dispara el evento y nadie
   cierra la sesión salvo el listener del satélite.

## 5. Configuración de Claude Code

Dónde va cada pieza, `core/06`; cómo se escribe, `core/08`. Proyecto en
**modo compartido**. `CLAUDE.md` y `.claude/` se versionan aquí para el
equipo, y su versión vive en el repositorio privado `abitech-dev/_claude`,
con el `stack` declarado en su `claude-sync.json`.

- `rules/core/`, `rules/tech/{testing,documentation}.md`, `agents/`,
  `hooks/*.py` y las skills son copia de la base global. Se editan en
  `~/.claude` y llegan con `sync.py guardar global` y luego
  `sync.py restaurar arsy-sso-client`.
- Las demás de `rules/tech/` vienen de la biblioteca `stacks/`;
  `tech/laravel-package.md` es idéntica en `abitech-payments-laravel`.
  Se editan aquí y vuelven con `sync.py guardar arsy-sso-client`.
- Lo propio vive en `rules/project/`, `settings.json` y
  `rule-cases.json`.
- Una buena práctica que convenga fijar se propone como cambio en su
  regla; nunca se aplica en silencio.
- `.agents/` guarda reglas de otra herramienta y Claude Code no las
  carga; ante una diferencia mandan `.claude/rules/` y, en commits,
  `core/03`.

## 6. Despliegue

- Un paquete no se despliega; se publica según
  `tech/laravel-package.md` regla 7. El push pide confirmación
  (`settings.json`).
- Cada satélite fija la versión en su `composer.lock`. Subirla es un
  cambio de ese satélite, con su suite.

## 7. Deuda conocida

| Tema | Estado |
|---|---|
| Contraseña obligatoria | la migración del paquete no hace `users.password` nullable ni la guía lo pide; con la tabla de Laravel, crear un usuario SSO falla por `NOT NULL`; tarea DT-12 |
| Modelo de la app en `src/` | 7 `config('arsy-sso.user_model', '\\App\\Models\\User')` en `SsoAuthenticationService.php:152,190,211` y `SsoWebhookHandlerService.php:51,83,105,131`; tarea DT-04 |
| Inertia en línea | `\Inertia\Inertia::location()` y `class_exists('\Inertia\Inertia')` en `SsoAutoLogin.php:43-44`; string de clase en `SsoAuthenticationService.php:26`; tarea DT-04 |
| Callback interceptado | con `oauth` y la cookie, `SsoAutoLogin` (`:35-54`) también salta en el primer `/auth/callback`; se resuelve en PC-01 |
| Rutas sin prefijo | `login` y `logout` sin nombre propio ni opción para desactivarlas (`routes/web.php:9,12`; `tech/laravel-package.md` regla 4); tarea DT-05 |
| Rangos sin CI | `composer.json:7-20` admite PHP `^8.1\|^8.2\|^8.3` e `illuminate/*` `^10.0\|^11.0\|^12.0\|^13.0`; la suite pasa a mano con Laravel 10 en PHP 8.1, 11 y 12 en PHP 8.3 y 13 en PHP 8.3 y 8.5, sin CI que repita la matriz (`tech/laravel-package.md` regla 5); tarea DT-06 |
| Código muerto | `hasIdpSession()` (`SsoAuthenticationService.php:80`) y `services.arsy_account.webhook_secret` (`SsoClientServiceProvider.php:35`) sin consumidor aquí ni en los satélites; tarea DT-07 |
| Comentarios que repiten el código | `SsoClientServiceProvider.php:27,50-71`, `SsoAutoLogin.php:23,30,56,82,86`, `SsoWebhookHandlerService.php:91,109,135` (`core/02`); tarea DT-08 |
| Distribución | sin `.gitattributes`; el `vendor/` del satélite recibe `docs/`, `tests/`, `phpunit.xml.dist`, `.agents/`, `.claude/` y `CLAUDE.md` (`tech/laravel-package.md` regla 8); tarea DT-09 |
| Docs | `docs/construccion.md` narra la historia y cita un `routes/api.php` inexistente (`:22`); `docs/guia-de-uso.md` instala `^1.1.0` (`:20,34`) y apunta a `c:/laragon/www/arsy-sso-client` (`:43`); tarea DT-10 |
