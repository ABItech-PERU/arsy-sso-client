# Proceso de Construcción (Arquitectura del Paquete)

Este documento detalla el paso a paso de cómo fue diseñado y construido el paquete `arsy/sso-client` y las decisiones arquitectónicas clave que se tomaron para garantizar su escalabilidad.

## 1. El problema inicial
El ecosistema Arsy cuenta con múltiples aplicaciones "satélite" (Aceleradora, Coworker, etc.) y una aplicación central (Account Arsy). Inicialmente, la lógica de autenticación SSO estaba repetida manualmente en cada satélite: los mismos controladores, los mismos servicios y las mismas rutas copiadas y pegadas. Esto generaba deuda técnica y hacía imposible actualizar el código de seguridad de forma global.

## 2. Abstracción a un paquete
Decidimos extraer esta lógica repetitiva a un paquete independiente (`arsy-sso-client`). Los pasos fueron:
- Creación de la estructura del paquete en una carpeta independiente.
- Configuración del archivo `composer.json` definiendo el nombre del paquete, la carga de clases mediante PSR-4 y la compatibilidad con diferentes versiones de PHP y Laravel.

## 3. Limpieza de base de datos (minimalismo)
Originalmente, la lógica SSO obligaba a las aplicaciones satélites a tener múltiples columnas ensuciando su base de datos (`idp_sub`, `avatar`, `access_token`, etc.).
- **Solución:** Redujimos la migración del paquete a lo estrictamente esencial:
  - `sso_id`: El identificador inmutable que vincula al usuario local con la Central.
  - `sso_last_login_at`: Para auditoría.
- Delegamos cualquier campo adicional (como el avatar o el nombre) a los Eventos.

## 4. El corazón: El Service Provider
Creamos el `SsoClientServiceProvider` para registrar todos los componentes del paquete en la aplicación de destino sin que el desarrollador deba hacer casi nada:
- Se cargan automáticamente las rutas desde `routes/web.php` y `routes/api.php` del paquete.
- Se inyectan dinámicamente las variables de configuración en el array global `services` de Laravel, evitando configuraciones repetitivas.
- Se registra el Listener base de Socialite (`LaravelPassportExtendSocialite`).

## 5. Webhooks y Sincronización
Implementamos un controlador de Webhooks protegido por validación de firma criptográfica (`hash_hmac`). El `SsoWebhookHandlerService` procesa las alertas enviadas por Account Arsy en tiempo real:
- **Revocación de sesiones (`app.revoked`, `user.logout`):** Destruye automáticamente la sesión local si se usa base de datos.
- **Actualización de usuario (`user.updated`):** Sincroniza el correo y notifica a la app satélite.
- **Suspensión y eliminación (`user.suspended`, `user.deleted`):** Elimina el acceso bloqueando la sesión y/o aplicando un "Soft Delete" local.

## 6. Sistema de Eventos
Para mantener el paquete 100% desacoplado de las reglas de negocio de cada satélite, creamos 5 eventos clave. En lugar de guardar el avatar de forma rígida, el paquete emite un evento y le pasa los datos crudos a la aplicación satélite, permitiéndole manejar esos datos como lo desee.

## 7. Arquitectura de Auto-Login (SSO Silencioso)
Para evitar fricción en la navegación de aplicaciones satélite (ej. `arsyinnova`), implementamos un sistema de logueo automático (`SsoAutoLogin`).
- **El desafío:** Redirigir a un usuario visitante usando OAuth provocaba errores de CORS y bucles infinitos en aplicaciones Single Page Application (SPA) bajo Inertia.js.
- **La solución:** Habilitamos dos estrategias (`method`):
  1. `cookie` (Recomendada): La central emite una cookie firmada criptográficamente con HMAC (`ssotoken`) válida para todo el dominio `.arsy.test`. El middleware del satélite intercepta peticiones web de visitantes, valida la firma, extrae el payload (email/ID) y autentica silenciosamente en memoria, saltándose el flujo OAuth y evitando conflictos de CORS.
  2. `oauth`: Realiza un redireccionamiento tradicional con `prompt=none` para entornos sin subdominios compartidos.

## 8. Versionamiento Seguro
El `composer.json` se configuró con restricciones (`^10.0|^11.0|^12.0|^13.0` para Laravel y `^8.1|^8.2|^8.3` para PHP) para asegurar la retrocompatibilidad, permitiendo que el paquete se instale sin conflictos de dependencias en cualquier ecosistema moderno.
