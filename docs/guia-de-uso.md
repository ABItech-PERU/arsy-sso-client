# Guía de Uso e Instalación (SSO Client)

Bienvenido a la guía de integración del paquete `arsy-sso-client`. Esta guía documenta cómo conectar tu aplicación "Satélite" al servidor central de identidades (Account Arsy).

## 0. Registro en Account Arsy

Registra tu satélite en el panel de **Account Arsy**. Te pedirá tres URLs:
- **Redirección OAuth:** `https://tusitio.com/auth/callback`
- **Webhook SSO:** `https://tusitio.com/api/sso/webhook`
- **Webhook Billing (Si aplica):** `https://tusitio.com/api/billing/webhook`

Guarda el **Client ID**, **Client Secret** y el **Webhook Secret** generados.

## 1. Instalación

Dependiendo de dónde albergues el paquete, elige una opción:

- **Opción A: Packagist (Público)**
  ```bash
  composer require arsy/sso-client:"^1.1.0"
  ```

- **Opción B: GitHub (Privado/VCS)**
  Añade esto a tu `composer.json` y luego instala:
  ```json
  "repositories": [
      {
          "type": "vcs",
          "url": "https://github.com/ABItech-PERU/arsy-sso-client"
      }
  ]
  ```
  ```bash
  composer require arsy/sso-client:"^1.1.0"
  ```

- **Opción C: Local (Desarrollo)**
  Añade la ruta local de tu paquete al `composer.json` y luego instala:
  ```json
  "repositories": [
      {
          "type": "path",
          "url": "c:/laragon/www/arsy-sso-client"
      }
  ]
  ```
  ```bash
  composer require arsy/sso-client *@dev
  ```

## 2. Variables de Entorno (.env)

Agrega esto al `.env` del satélite:

```env
# Central IDP
SSO_OAUTH_URL=https://account.arsy.com

# Credenciales OAuth
SSO_CLIENT_ID=tu_client_id
SSO_CLIENT_SECRET=tu_client_secret

# Secretos y Cookies
SSO_WEBHOOK_SECRET=tu_webhook_secret
SSO_COOKIE_SECRET=tu_cookie_secret
SSO_COOKIE_DOMAIN=.arsy.test
SSO_COOKIE_NAME=ssotoken

# Configuración SSO
SSO_AUTO_LOGIN_METHOD=oauth
SSO_REDIRECT_AFTER_LOGIN=/dashboard

# Webhooks de pago (si el satélite cobra vía billing de la Central)
SSO_BILLING_SECRET=tu_billing_secret

# Opcionales
SSO_SERVICE_TIMEOUT=15              # segundos, llamadas servidor-a-servidor
SSO_TOKEN_EXCHANGE_ENABLED=false    # POST /api/auth/token (requiere Sanctum)
```

## 3. Preparar Base de Datos

Publica la configuración y ejecuta la migración que añade `sso_id` y tokens a tu tabla de usuarios:

```bash
php artisan vendor:publish --tag=arsy-sso-config
php artisan migrate
```

Añade los campos al `$fillable` de tu `app/Models/User.php`:
```php
protected $fillable = [ 'name', 'email', 'password', 'sso_id', 'sso_last_login_at' ];
```

## 4. Uso del SSO Básico

El paquete inyecta las rutas automáticamente.
- **Login:** `<a href="{{ route('login') }}">Iniciar Sesión</a>`
- **Logout:** Haz un POST a `{{ route('logout') }}`. Ejecuta un Hard-Logout local y en la central.

## 5. Auto-Login Silencioso e Híbrido

Para loguear automáticamente a usuarios que ya están en la Central, registra el middleware en tu `bootstrap/app.php` (Laravel 11+):

```php
$middleware->web(append: [
    \Arsy\SSOClient\Http\Middleware\SsoAutoLogin::class,
]);
```

### Métodos de Auto-Login (`SSO_AUTO_LOGIN_METHOD`):
- **`oauth` (Híbrido - Recomendado):** Utiliza la cookie compartida (`ssotoken`) como un "radar" ultrarrápido. Si detecta la cookie, redirige silenciosamente a la central (`prompt=none`) una sola vez para oficializar la sesión vía OAuth, registrando al satélite en el panel de conexiones.
  - **Soporte Inertia.js:** Si el salto silencioso se activa desde un enlace de Inertia (Ajax), el middleware fuerza automáticamente un "Hard Reload" (`Inertia::location`) para evitar bloqueos de CORS.
- **`cookie` (Legacy):** Lee y resuelve el HMAC directamente. Es más rápido pero no registra el satélite en las conexiones activas de la central.
- **`none`:** Desactiva el auto-login.

## 6. Sincronización Webhook (Single Sign-Out)

El paquete levanta automáticamente un Webhook protegido en `/api/sso/webhook`.
Cuando el usuario cierra sesión en la central, la central envía un evento `session.revoked` al satélite. El paquete intercepta el Webhook y **destruye automáticamente la sesión local en tiempo real** (Hard Logout).
*Nota:* Tu satélite debe usar `SESSION_DRIVER=database` o `redis` para que la destrucción de sesiones funcione.

## 7. Eventos (Extensibilidad)

Si necesitas asignar roles o guardar datos adicionales al loguear, escucha el evento `SsoUserAuthenticated`:

```php
use Arsy\SSOClient\Events\SsoUserAuthenticated;

public function handle(SsoUserAuthenticated $event): void
{
    $user = $event->user;       // Usuario en BD local
    $idpUser = $event->idpUser; // Objeto OAuth de la central
    
    // La Central no envía roles: cada satélite gestiona los suyos.
    // Ej: if ($user->roles()->doesntExist()) $user->assignRole('ESTUDIANTE');
    // Campos disponibles en $idpUser->user: ver docs/satelites.md de Account.
}
```
En Laravel 11+, el autodescubrimiento registrará tu Listener inmediatamente.

## 8. Llamadas servidor-a-servidor a la Central

Para consumir APIs de Account Arsy como el propio satélite (billing, etc.)
usa el macro `Http::arsyAccount()`. Obtiene un token OAuth2
`client_credentials` con `SSO_CLIENT_ID`/`SSO_CLIENT_SECRET`, lo cachea
cifrado hasta poco antes de expirar y, ante un `401`, lo renueva y reintenta
una vez.

```php
use Illuminate\Support\Facades\Http;

$response = Http::arsyAccount(['billing:checkout'])
    ->post('/api/v1/checkout/sessions', $payload)
    ->throw();
```

- Los scopes deben existir en la Central y el cliente debe tener el grant
  `client_credentials`.
- El macro devuelve un `PendingRequest` estándar: timeouts, `throw()` y
  `Http::fake()` funcionan igual en tests.

## 9. Canje de token (opcional)

`POST /api/auth/token` canjea un token de la Central por un token local para
clientes móviles o de escritorio del satélite. Está **desactivado por
defecto**; actívalo con `SSO_TOKEN_EXCHANGE_ENABLED=true` solo si el modelo
`User` usa `HasApiTokens` (Sanctum) y tiene columnas de token.
