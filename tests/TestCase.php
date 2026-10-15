<?php

namespace Arsy\SSOClient\Tests;

use Arsy\SSOClient\Providers\SsoClientServiceProvider;
use Arsy\SSOClient\Tests\Fixtures\User;
use Laravel\Socialite\SocialiteServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [SocialiteServiceProvider::class, SsoClientServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        // Clave por ejecución para cifrar cookies sin fijar una en el repo.
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));

        // La tabla `users` de Testbench no tiene columna de avatar.
        $app['config']->set('arsy-sso.user_model', User::class);
        $app['config']->set('arsy-sso.user_avatar_column', null);
    }
}
