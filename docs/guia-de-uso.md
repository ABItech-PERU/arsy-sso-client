# Guía de Uso e Instalación

Bienvenido a la guía de implementación del paquete `arsy-sso-client`. Esta guía te enseñará, paso a paso, cómo conectar una nueva aplicación "Satélite" al servidor central (Account Arsy).

## 0. Registro en Account Arsy (La Central)

Antes de instalar el paquete, debes registrar tu nueva aplicación satélite en el panel de administrador de **Account Arsy**. Al crear la aplicación, se te pedirán dos URLs clave que este paquete genera automáticamente:

- **URL de Redirección:** `https://tusitio.com/auth/callback`
- **URL del Webhook:** `https://tusitio.com/api/sso/webhook`

Al guardar, Account Arsy te generará el **Client ID**, **Client Secret** y el **Webhook Secret**. ¡Guárdalos para el paso 2!

## 1. Instalación del Paquete

Dependiendo de dónde tengas alojado este paquete, existen 3 formas de instalarlo en tu proyecto satélite:

- **Opción A: Packagist**
  Ejecuta directamente:

  ```bash
  composer require arsy/sso-client
  ```

- **Opción B: GitHub**
  Agrega el repositorio a tu `composer.json`:

  ```json
  "repositories": [
      {
          "type": "vcs",
          "url": "https://github.com/ABItech-PERU/arsy-sso-client"
      }
  ]
  ```

  Luego instala indicando la versión que quieras usar (ej. `^1.0.0` para la versión 1 y sus parches):

  ```bash
  composer require arsy/sso-client:"^1.0.0"
  ```

- **Opción C: Local (Desarrollo)**
  Vincula la ruta local de tu paquete y ejecuta:
  ```bash
  composer config repositories.sso-client path c:/laragon/www/arsy-sso-client
  composer require arsy/sso-client *@dev
  ```

## 2. Variables de Entorno (.env)

Agrega y configura las siguientes variables en el archivo `.env` de tu proyecto:

```env
# URL de la central de cuentas (IDP)
ARSY_OAUTH_URL=https://account.arsy.com

# Credenciales OAuth
ARSY_CLIENT_ID=tu_client_id
ARSY_CLIENT_SECRET=tu_client_secret

# Firma secreta para validar los Webhooks
ARSY_OAUTH_WEBHOOK_SECRET=tu_webhook_secret

# URL a donde enviar al usuario luego del login (opcional, por defecto /dashboard)
SSO_REDIRECT_AFTER_LOGIN=/dashboard
```

## 3. Publicar Configuración y Migrar

El paquete cuenta con un archivo de configuración y una pequeña migración que inyecta las columnas vitales (`sso_id` y `sso_last_login_at`) a tu tabla de usuarios.

Publica el archivo de configuración:

```bash
php artisan vendor:publish --tag=arsy-sso-config
```

_(Opcional: puedes abrir `config/arsy-sso.php` para revisar los ajustes avanzados, como apagar la revocación automática de sesiones)._

Ejecuta las migraciones:

```bash
php artisan migrate
```

## 4. Permitir campos masivos (Mass Assignment)

Asegúrate de agregar los nuevos campos al array `$fillable` en el modelo principal de tu aplicación (`app/Models/User.php`):

```php
protected $fillable = [
    'name',
    'email',
    'password',
    'sso_id',             // Obligatorio para el paquete
    'sso_last_login_at',  // Obligatorio para el paquete
];
```

## 5. ¡Listo! ¿Cómo se usa?

¡No tienes que crear ninguna ruta ni controlador! El paquete inyectó todo por ti.

- **Para iniciar sesión:** Crea un botón en tu vista que envíe al usuario a la ruta nombrada `login`.
  ```html
  <a href="{{ route('login') }}">Iniciar Sesión con Arsy</a>
  ```
- **Para cerrar sesión:** Crea un formulario POST que apunte a la ruta `logout`.
  ```html
  <form method="POST" action="{{ route('logout') }}">
    @csrf
    <button type="submit">Cerrar Sesión</button>
  </form>
  ```
- **El Webhook:** Tu aplicación ya está escuchando silenciosamente en `tusitio.com/api/sso/webhook` para proteger tus sesiones y actualizar datos en tiempo real.

## 6. Mapeo Dinámico (Nombres y Avatar)

Tu paquete puede mapear automáticamente el nombre, apellidos y avatar del usuario hacia tu base de datos local (App Satélite) sin escribir código extra. Simplemente publica la configuración y ajusta estas variables en `config/arsy-sso.php`:

```php
'user_name_column' => 'name',
'user_lastname_column' => 'last_name', // Descomentar si usas columnas separadas
'user_avatar_column' => 'avatar',      // Descomentar si guardas el avatar
```

Si dejas `user_lastname_column` comentado, el paquete juntará automáticamente los nombres y apellidos y los guardará en la columna definida en `user_name_column`.

## 7. Personalización Avanzada (Eventos)

Si tu aplicación necesita guardar datos extra, asignar roles, o ejecutar lógica compleja al iniciar sesión, puedes escuchar nuestros eventos creando un **Listener**.

Para crear el Listener ejecuta:
```bash
php artisan make:listener SincronizarDatosDesdeSSO
```

Dentro de tu listener (`app/Listeners/SincronizarDatosDesdeSSO.php`) recibirás el evento con toda la información:

```php
use Arsy\SSOClient\Events\SsoUserAuthenticated;

public function handle(SsoUserAuthenticated $event): void
{
    $user = $event->user;       // Modelo de usuario local (ya guardado en DB)
    $idpUser = $event->idpUser; // Objeto crudo que viene desde la central (SSO)
    
    // Ejemplo: asignar roles
    // $user->syncRoles($idpUser->user['roles']);
}
```

### ¿Cómo registrar el Listener?

**👉 Si usas Laravel 11 o superior (Recomendado)**
¡No tienes que hacer nada más en archivos de configuración! Gracias al **Event Discovery** automático de Laravel 11, solo debes asegurarte de tipar el parámetro de la función `handle` con nuestro evento `SsoUserAuthenticated` dentro de tu Listener, y Laravel hará el resto de forma mágica:

```php
// app/Listeners/SincronizarDatosDesdeSSO.php
namespace App\Listeners;

use Arsy\SSOClient\Events\SsoUserAuthenticated;

class SincronizarDatosDesdeSSO
{
    // ¡Aquí ocurre la magia del Event Discovery!
    public function handle(SsoUserAuthenticated $event): void
    {
        $user = $event->user;
        $idpUser = $event->idpUser;
        // ...
    }
}
```

**👉 Si usas Laravel 10 o inferior**
Debes registrarlo manualmente en tu archivo `app/Providers/EventServiceProvider.php`:

```php
use Arsy\SSOClient\Events\SsoUserAuthenticated;
use App\Listeners\SincronizarDatosDesdeSSO;

protected $listen = [
    SsoUserAuthenticated::class => [
        SincronizarDatosDesdeSSO::class,
    ],
];
```
