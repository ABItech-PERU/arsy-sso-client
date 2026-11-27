# Arsy SSO Laravel

Conecta las apps Laravel de Arsy con account para el inicio de sesión único y la sincronización de usuarios

<p>
  <a href="https://packagist.org/packages/arsy/sso-laravel"><img alt="Versión en Packagist" src="https://img.shields.io/packagist/v/arsy/sso-laravel?style=flat&label=packagist"></a>
  <a href="https://github.com/ABItech-PERU/arsy-sso-laravel/actions/workflows/ci.yml"><img alt="Integración continua" src="https://img.shields.io/github/actions/workflow/status/ABItech-PERU/arsy-sso-laravel/ci.yml?branch=main&style=flat&label=integraci%C3%B3n%20continua"></a>
  <img alt="Laravel 12 y 13" src="https://img.shields.io/badge/laravel-12%20y%2013-ff2d20?style=flat&logo=laravel&logoColor=white">
  <a href="LICENSE"><img alt="Licencia MIT" src="https://img.shields.io/badge/licencia-MIT-2ea043?style=flat"></a>
</p>

```mermaid
flowchart LR
  N["Navegador"] -- "GET /login" --> S["App satélite<br>con arsy/sso-laravel"]
  S -- "OAuth2" --> A["account<br>IdP con Passport"]
  A -- "webhook firmado" --> S
  S -- "client_credentials" --> A
```

## Características

- Rutas de inicio de sesión, callback y cierre de sesión listas, con OAuth2
  contra account por Socialite y Laravel Passport.
- Inicio de sesión silencioso, que entra sin pedir datos si la persona ya
  inició sesión en account, también desde páginas de Inertia.
- Webhooks firmados que actualizan al usuario, cierran sus sesiones o lo
  bloquean al suspenderlo o eliminarlo.
- Solo agrega `sso_id` y `sso_last_login_at` a `users` y deja `password`
  nullable, porque un usuario SSO no tiene contraseña local.
- `Http::arsyAccount($scopes)` para llamar a account de servidor a
  servidor, con el token en caché.
- Eventos para guardar los datos propios de cada app, como nombre, avatar o
  roles.

## Requisitos

| Herramienta | Versión |
| --- | --- |
| PHP | 8.2 o superior; 8.3 con Laravel 13 |
| Laravel | 12 o 13 |
| Redis | para el cierre de sesión único, con `SESSION_DRIVER=redis` |

## Inicio rápido

```bash
composer require arsy/sso-laravel
php artisan vendor:publish --tag=arsy-sso-config
php artisan migrate
```

Las variables de entorno y el registro de la app en account se explican en
la [guía de uso](docs/guia-de-uso.md).

## Uso

| Evento | Cuándo se dispara |
| --- | --- |
| `SsoUserAuthenticated` | Una persona inicia sesión |
| `SsoWebhookUserUpdated` | La persona cambia sus datos en account |
| `SsoUserLoggedOutViaWebhook` | account cierra su sesión |
| `SsoWebhookUserSuspended` | account suspende al usuario |
| `SsoWebhookUserDeleted` | account elimina al usuario |

Todos viven en `Arsy\SSOClient\Events`.

## Documentación

| Quiero… | Documento |
| --- | --- |
| Instalar el paquete en una app | [guia-de-uso.md](docs/guia-de-uso.md) |
| Entender cómo está construido | [construccion.md](docs/construccion.md) |
| Saber por qué se publica así | [ADR 0001](docs/decisiones/0001-ramas-y-publicacion.md) |
| Ver los cambios por versión | [CHANGELOG.md](CHANGELOG.md) |

## Contribuir

Los pasos y lo que revisa la integración continua están en
[CONTRIBUTING.md](CONTRIBUTING.md).

## Licencia

Copyright © 2026 ABItech Perú. Licencia MIT, cuyo texto figura en
[LICENSE](LICENSE).
