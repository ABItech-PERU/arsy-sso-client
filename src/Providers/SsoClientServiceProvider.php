<?php

namespace Abitech\Sso\Providers;

use Abitech\Sso\Console\LinkUserCommand;
use Abitech\Sso\Http\Middleware\SsoAutoLogin;
use Abitech\Sso\Listeners\SessionIndexSubscriber;
use Abitech\Sso\Services\AccountServiceToken;
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
        $this->mergeConfigFrom(
            __DIR__.'/../../config/sso.php', 'sso'
        );

        // Socialite lee esta config; el satélite no toca config/services.php.
        config([
            'services.laravelpassport' => [
                'client_id' => config('sso.client_id'),
                'client_secret' => config('sso.client_secret'),
                'redirect' => config('app.url') . '/auth/callback',
                'host' => config('sso.oauth_url'),
            ],
        ]);
    }

    /**
     * Inicializa cualquier servicio de la aplicación.
     */
    public function boot(Router $router)
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        Event::listen(
            SocialiteWasCalled::class,
            [LaravelPassportExtendSocialite::class, 'handle']
        );

        Event::subscribe(SessionIndexSubscriber::class);

        $router->aliasMiddleware('sso.auto_login', SsoAutoLogin::class);

        $this->registerAccountHttpMacro();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/sso.php' => config_path('sso.php'),
            ], 'sso-config');

            $this->publishes([
                __DIR__.'/../../database/migrations/' => database_path('migrations'),
            ], 'sso-migrations');

            $this->commands([LinkUserCommand::class]);
        }
    }

    /**
     * Http::ssoCentral(['billing:checkout'])->post('/api/v1/...') autentica
     * al satélite con client_credentials; ante un 401 renueva el token y
     * reintenta una vez (token revocado o expirado antes de tiempo).
     */
    private function registerAccountHttpMacro(): void
    {
        Http::macro('ssoCentral', function (array $scopes = []): PendingRequest {
            /** @var Factory $this */
            $tokens = app(AccountServiceToken::class);

            return $this->baseUrl(rtrim((string) config('sso.oauth_url'), '/'))
                ->acceptJson()
                ->timeout((int) config('sso.service_client.timeout', 15))
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
