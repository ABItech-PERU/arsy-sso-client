<?php

namespace Arsy\SSOClient\Tests\Feature\Routes;

use Arsy\SSOClient\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class DisabledAuthRoutesTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('arsy-sso.routes.auth', false);
    }

    public function test_login_and_logout_are_not_registered(): void
    {
        $this->assertFalse(Route::has('login'));
        $this->assertFalse(Route::has('logout'));
        $this->get('/login')->assertNotFound();
        $this->post('/logout')->assertNotFound();
    }

    public function test_the_silent_login_and_the_oauth_callback_stay_registered(): void
    {
        $this->assertTrue(Route::has('sso.silent'));
        $this->get('/auth/callback')->assertRedirect('/');
    }
}
