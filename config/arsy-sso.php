<?php

return [

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | Aquí se define el modelo de usuario que la aplicación usa. Por defecto es
    | el modelo estándar de Laravel, pero puedes cambiarlo si usas otro.
    |
    */

    'user_model' => \App\Models\User::class,

    /*
    |--------------------------------------------------------------------------
    | User Name Columns Mapping
    |--------------------------------------------------------------------------
    |
    | Define las columnas para almacenar el nombre del usuario localmente.
    | - 'user_name_column': Columna para el nombre (ej. 'name').
    | - 'user_lastname_column': Columna para apellidos (Opcional).
    | - 'user_avatar_column': Columna para la foto de perfil (Opcional).
    | 
    | Si usas una sola columna (estándar), deja 'user_lastname_column' en null.
    | Si tu BD no guarda nombres o avatar, deja las variables comentadas o en null.
    |
    */

    'user_name_column' => 'name',
    // 'user_lastname_column' => 'last_name',
    'user_avatar_column' => 'avatar',

    /*
    |--------------------------------------------------------------------------
    | Server & Credentials Configuration
    |--------------------------------------------------------------------------
    |
    | Aquí se definen las credenciales para la conexión con el servidor 
    | central de SSO (Account Arsy).
    |
    */

    'oauth_url' => env('ARSY_OAUTH_URL'),
    'client_id' => env('ARSY_CLIENT_ID'),
    'client_secret' => env('ARSY_CLIENT_SECRET'),
    'webhook_secret' => env('ARSY_SSO_WEBHOOK_SECRET'),
    'billing_webhook_secret' => env('ARSY_BILLING_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Redirect After Login
    |--------------------------------------------------------------------------
    |
    | Define a qué ruta debe ser redirigido el usuario después de iniciar sesión
    | exitosamente mediante el SSO.
    |
    */

    'redirect_after_login' => env('SSO_REDIRECT_AFTER_LOGIN', '/dashboard'),

    /*
    |--------------------------------------------------------------------------
    | Auto Revoke Sessions
    |--------------------------------------------------------------------------
    |
    | Si está en true, el paquete intentará destruir automáticamente las
    | sesiones locales en la base de datos (si el driver es 'database')
    | cuando reciba un webhook de revocación desde el servidor central.
    | Si usas Redis o prefieres hacerlo manual, ponlo en false y escucha
    | el evento SsoUserLoggedOutViaWebhook.
    |
    */

    'auto_revoke_sessions' => true,

];
