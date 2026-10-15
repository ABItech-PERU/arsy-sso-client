---
paths:
  - "src/Http/Middleware/**"
  - "src/Http/Controllers/**"
  - "src/Services/SsoAuthenticationService.php"
  - "src/Events/SsoUserAuthenticated.php"
  - "config/arsy-sso.php"
  - "routes/web.php"
  - "database/migrations/**"
---

# SSO (login, auto-login e identidad)

DIRECTIVA: account es la única fuente de identidad; el satélite conoce
al usuario por `sso_id` y guarda de él lo mínimo.

## Tabla anti-vanilla

| Prohibido | Usar |
| --- | --- |
| `redirect()` hacia account en una petición con `X-Inertia` | `Inertia::location($url)`, que fuerza la recarga completa |
| Columna nueva en la migración del paquete (avatar, roles, perfil) | evento con los datos crudos de account y columna del satélite |
| Buscar al usuario solo por `email` | primero `sso_id`; el `email` solo enlaza un usuario sin `sso_id` |
| Un secreto para dos propósitos | el secreto de cada fila de la regla 6 |

## Reglas

1. **Identidad.** `users.sso_id` guarda el `id` (UUID) de account y es
   la llave local. Nunca se recicla ni se reescribe con otro valor.
2. **BD mínima.** La migración solo agrega `sso_id` y
   `sso_last_login_at`. Nombre y avatar van a las columnas que fija la
   config (`user_name_column`, `user_lastname_column`,
   `user_avatar_column`); roles y perfil, al listener del satélite de
   `SsoUserAuthenticated`.
3. **Auto-login híbrido** (`SsoAutoLogin`, alias `sso.auto_login`),
   según `arsy-sso.auto_login.method`.
   - `oauth`, el que pide account. La cookie `ssotoken` es solo un
     radar; si existe y la sesión no tiene `sso_silent_attempted`, hace
     un único salto a `/auth/silent` (`prompt=none`), que registra la
     sesión en account para que pueda cerrarla.
   - `cookie`. Verifica `payload_base64.hmac_sha256` con
     `SSO_COOKIE_SECRET` y su `exp`; sin secreto no autentica a nadie, y
     cualquier fallo deja al visitante anónimo, sin error.
   - `none`. Desactivado.
4. **Silencioso que falla.** Si account no tiene sesión,
   `handleCallback()` devuelve `null` y el callback vuelve a
   `url.intended` sin mostrar error. `rememberIntendedUrl()` guarda la
   página antes del salto, salvo en rutas de auth.
5. **Rutas.** `GET /login` (`login`), `GET /auth/silent`,
   `GET /auth/callback`, `POST /logout` (`logout`) y, con
   `SSO_TOKEN_EXCHANGE_ENABLED`, `POST /api/auth/token`.
   - Chocan con las rutas de auth del satélite; se quitan las del
     satélite.
   - `/auth/callback` no se renombra, porque account tiene registrada
     esa URL de cada satélite.
6. **Un secreto por propósito** (`docs/satelites.md` §6 de account).

   | Variable | Clave | Uso |
   | --- | --- | --- |
   | `SSO_CLIENT_SECRET` | `client_secret` | login OAuth y token `client_credentials` |
   | `SSO_COOKIE_SECRET` | `shared_secret` | firma de `ssotoken`, el mismo valor que en account |
   | `SSO_WEBHOOK_SECRET` | `webhooks.sso` | firma de `POST /api/sso/webhook` |
   | `SSO_BILLING_SECRET` | `webhooks.billing` | lo lee el webhook de billing del satélite; el paquete no lo usa |

7. **Cierre de sesión local.** `POST /logout` avisa a account
   (`/api/logout` con el token de la sesión) y borra `ssotoken` y
   `arsy_logged_in` en el dominio de `SSO_COOKIE_DOMAIN`.
