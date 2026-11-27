<?php

namespace Abitech\Sso\Tests\Feature\Http\Middleware;

use Abitech\Sso\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class SsoAutoLoginTest extends TestCase
{
    public function test_the_auto_login_method_defaults_to_oauth(): void
    {
        $this->assertSame('oauth', config('sso.auto_login.method'));
    }

    public function test_a_published_config_without_method_jumps_to_the_silent_login(): void
    {
        config(['sso.auto_login' => []]);
        Route::middleware(['web', 'sso.auto_login'])->get('/panel', fn () => 'panel');

        $this->withCookie('ssotoken', 'radar')
            ->get('/panel')
            ->assertRedirect('/auth/silent');
    }
}
