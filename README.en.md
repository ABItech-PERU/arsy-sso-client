<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/imagenes/logo-oscuro.svg">
    <img alt="SSO for Laravel" src="docs/imagenes/logo-claro.svg" width="320">
  </picture>
</p>

<h1 align="center">SSO Laravel</h1>

<p align="center">Connects Laravel apps to a Laravel Passport identity hub for single sign-on and user sync</p>

<p align="center">
  <a href="https://packagist.org/packages/abitechdev/sso-laravel"><img alt="Packagist version" src="https://img.shields.io/packagist/v/abitechdev/sso-laravel?style=flat&color=1c6ccc&label=packagist"></a>
  <a href="https://github.com/abitech-dev/abitech-sso-laravel/actions/workflows/ci.yml"><img alt="Continuous integration" src="https://img.shields.io/github/actions/workflow/status/abitech-dev/abitech-sso-laravel/ci.yml?branch=main&style=flat&label=continuous%20integration"></a>
  <img alt="Laravel 12 and 13" src="https://img.shields.io/badge/laravel-12%20and%2013-ff2d20?style=flat&logo=laravel&logoColor=white">
  <a href="LICENSE"><img alt="MIT license" src="https://img.shields.io/badge/license-MIT-2ea043?style=flat"></a>
</p>

<p align="center">
  <a href="#quick-start">Quick start</a> &nbsp;|&nbsp;
  <a href="#usage">Usage</a> &nbsp;|&nbsp;
  <a href="#documentation">Documentation</a> &nbsp;|&nbsp;
  <a href="README.md">Español</a>
</p>

<picture>
  <source media="(prefers-color-scheme: dark)" srcset="docs/diagramas/flujo-dark.svg">
  <img alt="The browser goes through your Laravel app, which signs in against the hub with OAuth2, receives its signed webhooks, calls it server to server and keeps sessions in Redis" src="docs/diagramas/flujo.svg">
</picture>

A company with several Laravel apps and a Laravel Passport identity hub
installs the package in each app, and people sign in once to use them all.
Arsy uses it to connect its apps to its hub, account.

## What it does

<table>
  <tr>
    <td width="50%" valign="top">
      <picture><source media="(prefers-color-scheme: dark)" srcset="docs/iconos/acceso-dark.svg"><img alt="Sign-in" src="docs/iconos/acceso.svg" width="28"></picture><br><strong>Sign-in, ready</strong><br>Login, callback and logout routes with OAuth2 against the hub.
    </td>
    <td width="50%" valign="top">
      <picture><source media="(prefers-color-scheme: dark)" srcset="docs/iconos/silencioso-dark.svg"><img alt="Silent sign-in" src="docs/iconos/silencioso.svg" width="28"></picture><br><strong>No second prompt</strong><br>If the person already signed in at the hub, the app knows them, Inertia included.
    </td>
  </tr>
  <tr>
    <td width="50%" valign="top">
      <picture><source media="(prefers-color-scheme: dark)" srcset="docs/iconos/webhook-dark.svg"><img alt="Webhooks" src="docs/iconos/webhook.svg" width="28"></picture><br><strong>Changes right away</strong><br>Signed webhooks update, sign out or block the user.
    </td>
    <td width="50%" valign="top">
      <picture><source media="(prefers-color-scheme: dark)" srcset="docs/iconos/datos-dark.svg"><img alt="Database" src="docs/iconos/datos.svg" width="28"></picture><br><strong>Your database, untouched</strong><br>It only adds <code>sso_id</code> and <code>sso_last_login_at</code> to <code>users</code>.
    </td>
  </tr>
  <tr>
    <td width="50%" valign="top">
      <picture><source media="(prefers-color-scheme: dark)" srcset="docs/iconos/servidor-dark.svg"><img alt="Server to server" src="docs/iconos/servidor.svg" width="28"></picture><br><strong>Server-to-server calls</strong><br><code>Http::ssoCentral()</code> calls the hub with a cached token.
    </td>
    <td width="50%" valign="top">
      <picture><source media="(prefers-color-scheme: dark)" srcset="docs/iconos/eventos-dark.svg"><img alt="Events" src="docs/iconos/eventos.svg" width="28"></picture><br><strong>Your data, your way</strong><br>Events to store name, avatar or roles in each app.
    </td>
  </tr>
</table>

## Quick start

```bash
composer require abitechdev/sso-laravel
php artisan vendor:publish --tag=sso-config
php artisan migrate
```

```env
SSO_OAUTH_URL=https://central.example.com
SSO_CLIENT_ID=...
SSO_CLIENT_SECRET=...
SSO_WEBHOOK_SECRET=...
```

With those four variables, `GET /login` sends the person to the hub and
brings them back signed in. Registering the app at the hub and the other
variables are covered in the [usage guide](docs/guia-de-uso.md), in Spanish.

## Usage

Listen to sign-ins to store your app's own data:

```php
use Abitech\Sso\Events\SsoUserAuthenticated;
use Illuminate\Support\Facades\Event;

Event::listen(function (SsoUserAuthenticated $event) {
    $event->user->update(['avatar' => $event->idpUser->getAvatar()]);
});
```

And call the hub as the app itself:

```php
$response = Http::ssoCentral(['billing:checkout'])->post('/api/v1/checkout');
```

## Compatibility

| Tool | Version |
| --- | --- |
| PHP | 8.2 or later; 8.3 with Laravel 13 |
| Laravel | 12 or 13 |
| Redis | For single sign-out, with `SESSION_DRIVER=redis` |

## Documentation

| I want to… | Document |
| --- | --- |
| Install the package in an app | [guia-de-uso.md](docs/guia-de-uso.md) (Spanish) |
| Understand how it is built | [construccion.md](docs/construccion.md) (Spanish) |
| Know why it is released this way | [ADR 0001](docs/decisiones/0001-ramas-y-publicacion.md) (Spanish) |
| See changes by version | [CHANGELOG.md](CHANGELOG.md) |

## Contributing

The steps and what continuous integration checks are in
[CONTRIBUTING.md](CONTRIBUTING.md). To report a vulnerability, follow
[SECURITY.md](SECURITY.md).

## License

Copyright © 2026 ABItech Perú. MIT license, see [LICENSE](LICENSE).
