<?php

namespace Arsy\SSOClient\Tests\Feature\Routes;

use Arsy\SSOClient\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class AuthRoutesTest extends TestCase
{
    public function test_login_redirects_to_account_by_default(): void
    {
        config(['services.laravelpassport.host' => 'https://account.arsy.test']);

        $this->assertTrue(Route::has('login'));
        $this->assertTrue(Route::has('logout'));
        $this->get('/login')->assertRedirectContains('https://account.arsy.test/oauth/authorize');
    }
}
