<?php

namespace Arsy\SSOClient\Tests;

use Arsy\SSOClient\Providers\SsoClientServiceProvider;
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
    }
}
