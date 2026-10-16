<?php

namespace Arsy\SSOClient\Tests\Feature\Services;

use Arsy\SSOClient\Services\SsoAuthenticationService;
use Arsy\SSOClient\Tests\Concerns\UsesUsersTable;
use Arsy\SSOClient\Tests\Fixtures\User;
use Arsy\SSOClient\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use RuntimeException;

class SsoAuthenticationServiceTest extends TestCase
{
    use UsesUsersTable;

    private const NEW_SSO_ID = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

    public function test_callback_creates_an_sso_user_without_a_password(): void
    {
        Socialite::shouldReceive('driver->user')->andReturn(
            (new SocialiteUser)
                ->setRaw(['first_name' => 'Ana', 'last_name' => 'Quispe'])
                ->map(['id' => self::NEW_SSO_ID, 'email' => 'ana@arsy.test', 'name' => 'Ana'])
                ->setToken('token-de-account'),
        );

        $this->get('/auth/callback?code=codigo&state=estado')->assertRedirect('/dashboard');

        $user = User::query()->sole();
        $this->assertSame(self::NEW_SSO_ID, $user->sso_id);
        $this->assertNull($user->password);
        $this->assertAuthenticatedAs($user);
    }

    public function test_callback_with_a_new_sso_id_does_not_take_over_an_account_with_its_email(): void
    {
        $local = $this->createLocalUser(['sso_id' => null]);
        Socialite::shouldReceive('driver->user')->andReturn(
            (new SocialiteUser)
                ->setRaw(['first_name' => 'Ana', 'last_name' => 'Quispe'])
                ->map(['id' => self::NEW_SSO_ID, 'email' => $local->email, 'name' => 'Ana'])
                ->setToken('token-de-account'),
        );

        $response = $this->get('/auth/callback?code=codigo&state=estado');

        $this->assertNull($local->fresh()->sso_id);
        $this->assertGuest();
        $this->assertSame(1, User::count());
        $response->assertRedirect('/');
    }

    public function test_cookie_session_with_a_new_sso_id_does_not_take_over_an_account_with_its_email(): void
    {
        $local = $this->createLocalUser(['sso_id' => null]);

        $this->assertThrows(
            fn () => app(SsoAuthenticationService::class)->resolveCookieSession([
                'sub' => self::NEW_SSO_ID,
                'email' => $local->email,
            ]),
            RuntimeException::class,
        );

        $this->assertNull($local->fresh()->sso_id);
        $this->assertGuest();
    }

    public function test_token_exchange_with_a_new_sso_id_does_not_take_over_an_account_with_its_email(): void
    {
        $local = $this->createLocalUser(['sso_id' => null]);
        config(['arsy-sso.oauth_url' => 'https://account.arsy.test']);
        Http::preventStrayRequests();
        Http::fake(['https://account.arsy.test/api/user' => Http::response([
            'id' => self::NEW_SSO_ID,
            'email' => $local->email,
            'name' => 'Ana',
        ])]);

        $this->assertThrows(
            fn () => app(SsoAuthenticationService::class)->exchangeToken('token-de-account'),
            RuntimeException::class,
        );

        $this->assertNull($local->fresh()->sso_id);
    }
}
