<?php

namespace Arsy\SSOClient\Providers;

use Arsy\SSOClient\Http\Middleware\SsoAutoLogin;
use Arsy\SSOClient\Services\AccountServiceToken;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\LaravelPassport\LaravelPassportExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

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

        // 5. Cliente HTTP servidor-a-servidor hacia la Central
        $this->registerAccountHttpMacro();

        // 6. Configurar Publicaciones (vendor:publish)
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/arsy-sso.php' => config_path('arsy-sso.php'),
            ], 'arsy-sso-config');

            $this->publishes([
                __DIR__.'/../../database/migrations/' => database_path('migrations'),
            ], 'arsy-sso-migrations');
        }
    }

    /**
     * Http::arsyAccount(['billing:checkout'])->post('/api/v1/...') autentica
     * al satélite con client_credentials; ante un 401 renueva el token y
     * reintenta una vez (token revocado o expirado antes de tiempo).
     */
    private function registerAccountHttpMacro(): void
    {
        Http::macro('arsyAccount', function (array $scopes = []): PendingRequest {
            /** @var Factory $this */
            $tokens = app(AccountServiceToken::class);

            return $this->baseUrl(rtrim((string) config('arsy-sso.oauth_url'), '/'))
                ->acceptJson()
                ->timeout((int) config('arsy-sso.service_client.timeout', 15))
                ->withToken($tokens->get($scopes))
                ->retry(2, 0, function (Throwable $exception, PendingRequest $request) use ($tokens, $scopes): bool {
                    if (! $exception instanceof RequestException || $exception->response->status() !== Response::HTTP_UNAUTHORIZED) {
                        return false;
                    }

                    $tokens->forget($scopes);
                    $request->withToken($tokens->get($scopes));

                    return true;
                }, throw: false);
        });
    }
}
