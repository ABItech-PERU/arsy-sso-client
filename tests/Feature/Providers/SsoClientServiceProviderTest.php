<?php

namespace Arsy\SSOClient\Tests\Feature\Providers;

use Arsy\SSOClient\Providers\SsoClientServiceProvider;
use Arsy\SSOClient\Tests\TestCase;

class SsoClientServiceProviderTest extends TestCase
{
    public function test_oauth_redirect_uses_the_configured_app_url(): void
    {
        // Sin APP_URL en el entorno, como con `config:cache`.
        config(['app.url' => 'https://academy.arsy.test']);

        $this->app->register(SsoClientServiceProvider::class, force: true);

        $this->assertSame(
            'https://academy.arsy.test/auth/callback',
            config('services.laravelpassport.redirect'),
        );
    }
}
