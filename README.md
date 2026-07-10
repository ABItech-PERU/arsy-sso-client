# Arsy SSO Client

Un paquete de Laravel diseñado para integrar aplicaciones satélite al sistema centralizado de autenticación de Arsy (Account Arsy). Este paquete maneja automáticamente el flujo de OAuth2 con Laravel Passport (Socialite), sincroniza las sesiones en tiempo real y protege tus aplicaciones de manera escalable.

## Características principales

- **Autenticación sin esfuerzo**: Configuración automática de las rutas de login, callback y logout.
- **Sincronización en tiempo real**: Escucha webhooks del servidor central para actualizar datos de usuarios, cerrar sesiones remotas o bloquear cuentas suspendidas/eliminadas de inmediato.
- **Altamente desacoplado**: Diseñado para no interferir con la base de datos de tu aplicación, inyectando únicamente los campos vitales (`sso_id`, `sso_last_login_at`, y `email`).
- **Sistema de eventos**: Permite a tu aplicación satélite escuchar eventos puros (como `SsoUserAuthenticated` o `SsoWebhookUserUpdated`) para que guardes datos personalizados (nombres, avatares, roles) con total libertad.

## Documentación

Toda la documentación detallada se encuentra en la carpeta `docs/`.

- [Guía de Uso e Instalación](docs/guia-de-uso.md): Instrucciones paso a paso para instalar este paquete en una nueva aplicación satélite.
- [Proceso de Construcción (Arquitectura)](docs/construccion.md): Documentación técnica sobre cómo y por qué se construyó el paquete con su arquitectura actual.

## Requisitos

- PHP 8.1 o superior.
- Laravel 10.0, 11.0, 12.0 o 13.0

## Estructura de Eventos

El paquete dispara eventos clave a los que puedes suscribirte en tu aplicación para ejecutar lógica personalizada:

- `Arsy\SSOClient\Events\SsoUserAuthenticated`: Cuando un usuario inicia sesión.
- `Arsy\SSOClient\Events\SsoWebhookUserUpdated`: Cuando el usuario cambia sus datos en la Central.
- `Arsy\SSOClient\Events\SsoUserLoggedOutViaWebhook`: Cuando la Central revoca la sesión.
- `Arsy\SSOClient\Events\SsoWebhookUserSuspended`: Cuando la Central suspende al usuario.
- `Arsy\SSOClient\Events\SsoWebhookUserDeleted`: Cuando la Central elimina al usuario.

---
*Desarrollado con altos estándares de Clean Architecture y PSR-12.*
