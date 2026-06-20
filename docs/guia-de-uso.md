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
          "url": "https://github.com/tu-usuario/arsy-sso-client"
      }
  ]
  ```
  Luego instala indicando la rama (`dev-` + nombre):
  ```bash
  composer require arsy/sso-client:dev-main
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
*(Opcional: puedes abrir `config/arsy-sso.php` para revisar los ajustes avanzados, como apagar la revocación automática de sesiones).*

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

## 6. Personalización (Eventos)

Si tu aplicación satélite necesita guardar datos adicionales (como el avatar, el género o apellidos separados) al momento de que el usuario inicia sesión o se actualiza mediante un webhook, simplemente crea un **Listener** en tu aplicación y suscríbete a nuestros eventos.

Ejemplo en tu `EventServiceProvider`:

```php
use Arsy\SSOClient\Events\SsoUserAuthenticated;
use App\Listeners\GuardarAvatarYRoles;

protected $listen = [
    SsoUserAuthenticated::class => [
        GuardarAvatarYRoles::class,
    ],
];
```

Dentro de ese listener tendrás acceso a `$event->user` (tu modelo local) y `$event->idpUser` (los datos puros en formato JSON que vinieron desde la Central).
