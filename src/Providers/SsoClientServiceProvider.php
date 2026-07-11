<?php

namespace Arsy\SSOClient\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Routing\Router;
use SocialiteProviders\LaravelPassport\LaravelPassportExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Arsy\SSOClient\Http\Middleware\SsoAutoLogin;

class SsoClientServiceProvider extends ServiceProvider
{
    /**
     * Registra cualquier servicio de la aplicación.
     */
    public function register()
    {
        // Mezclar configuración del paquete con la de la aplicación
        $this->mergeConfigFrom(
            __DIR__.'/../../config/arsy-sso.php', 'arsy-sso'
        );

        // Inyectar dinámicamente las credenciales en la configuración 'services' de Laravel
        // para que Socialite las lea automáticamente sin que el desarrollador tenga que modificar 'config/services.php'
        config([
            'services.arsy_account.webhook_secret' => config('arsy-sso.webhooks.sso'),
            'services.laravelpassport' => [
                'client_id' => config('arsy-sso.client_id'),
                'client_secret' => config('arsy-sso.client_secret'),
                'redirect' => env('APP_URL') . '/auth/callback',
                'host' => config('arsy-sso.oauth_url'),
            ],
        ]);
    }

    /**
     * Inicializa cualquier servicio de la aplicación.
     */
    public function boot(Router $router)
    {
        // 1. Cargar Rutas
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');

        // 2. Cargar Migraciones
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // 3. Registrar el Provider de Socialite
        Event::listen(
            SocialiteWasCalled::class,
            [LaravelPassportExtendSocialite::class, 'handle']
        );

        // 4. Registrar Middleware
        $router->aliasMiddleware('sso.auto_login', SsoAutoLogin::class);

        // 5. Configurar Publicaciones (vendor:publish)
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/arsy-sso.php' => config_path('arsy-sso.php'),
            ], 'arsy-sso-config');

            $this->publishes([
                __DIR__.'/../../database/migrations/' => database_path('migrations'),
            ], 'arsy-sso-migrations');
        }
    }
}
