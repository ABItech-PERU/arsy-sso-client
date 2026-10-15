---
paths:
  - "src/**"
  - "config/**"
  - "routes/**"
  - "composer.json"
---

# Arquitectura (arsy/sso-client)

DIRECTIVA: el satélite instala el paquete y pone sus variables `SSO_*`
sin tocar `config/services.php`, rutas de auth ni el cliente HTTP hacia
account, como concreción de `tech/laravel-package.md`.

## Tabla anti-vanilla

| Prohibido | Usar |
| --- | --- |
| `Http::withToken($t)->post(config('arsy-sso.oauth_url').'/api/v1/...')` | `Http::arsyAccount(['scope'])->post('/api/v1/...')` |
| Token `client_credentials` pedido a mano o sin caché | `app(AccountServiceToken::class)->get($scopes)` |
| `config('arsy-sso.user_model', '\App\Models\User')` en `src/` | `config('arsy-sso.user_model')`; el default vive en `config/arsy-sso.php` |
| Alias de variable nuevo (`env('SSO_X', env('ARSY_X'))`) | solo `SSO_*` (ADR 0005) |

## Reglas

1. **Estructura.** `src/` en `Console`, `Events`, `Http/Controllers`,
   `Http/Middleware`, `Listeners`, `Providers`, `Services` y `Testing`.
   Los controladores son delgados y delegan en `src/Services/`.
2. **Provider.**
   - `register()` fusiona `config/arsy-sso.php` y arma
     `services.laravelpassport` (`client_id`, `client_secret`, `host` y
     `redirect` = `APP_URL/auth/callback`) para Socialite.
   - Es una excepción de `tech/laravel-package.md` regla 1. Esa config
     sale solo de la del paquete, sin petición, y debe estar lista antes
     del `boot()` de cualquier provider que resuelva Socialite.
   - `boot()` carga `routes/web.php` y la migración, que el satélite no
     necesita publicar, y registra el listener de Socialite,
     `SessionIndexSubscriber`, el alias `sso.auto_login` y la macro
     `Http::arsyAccount`. En consola registra `arsy-sso:link-user`.
3. **Cliente servidor a servidor.** `Http::arsyAccount($scopes)` sale a
   `arsy-sso.oauth_url` con el token `client_credentials` de
   `AccountServiceToken`, cifrado en caché por `client_id` y scopes
   hasta 60 s antes de vencer; ante un 401 lo olvida y reintenta una
   vez. Toda llamada nueva del paquete a account usa esa macro.
4. **Configuración.** Las variables son las de `docs/satelites.md` §1
   de account. Una clave nueva lleva su `env('SSO_*')` en
   `config/arsy-sso.php` y su fila en `docs/guia-de-uso.md` §2.
5. **Dependencias opcionales.** Inertia y Sanctum no están en `require`
   y van en `suggest` (`tech/laravel-package.md`, tabla). El código que
   los usa comprueba `class_exists(Inertia::class)`, con
   `use Inertia\Inertia;` arriba, o depende de su opción de config
   (`routes.token_exchange`).
6. **Evolución.** El paquete se convierte en `arsy/platform-client`
   (tareas en `CLAUDE.md` §1). Aquí entran correcciones y la base del
   paquete nuevo; un módulo nuevo (inbox, notificaciones, marca) espera
   a ese paquete.
7. **Contrato v1** (ADR 0005). Hasta producción, quitar o cambiar API
   pública sube la menor, sin alias ni mayor nueva. Es la excepción de
   `tech/laravel-package.md` regla 7, por el motivo del ADR.
