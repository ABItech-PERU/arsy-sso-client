<?php

namespace Arsy\SSOClient\Tests\Feature\Services;

use Arsy\SSOClient\Services\SsoAuthenticationService;
use Arsy\SSOClient\Tests\Concerns\UsesUsersTable;
use Arsy\SSOClient\Tests\Fixtures\TokenUser;
use Arsy\SSOClient\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TokenExchangeContractTest extends TestCase
{
    use UsesUsersTable;

    private const SSO_ID = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'arsy-sso.oauth_url' => 'https://account.arsy.test',
            'arsy-sso.user_model' => TokenUser::class,
        ]);
        Http::preventStrayRequests();
    }

    public function test_the_v1_payload_creates_the_user_with_its_id_as_sso_id(): void
    {
        $this->fakeUserInfo(['id' => self::SSO_ID, 'email' => 'ana@example.com', 'first_name' => 'ANA', 'last_name' => 'QUISPE']);

        $result = app(SsoAuthenticationService::class)->exchangeToken('token-de-account');

        $user = TokenUser::query()->sole();
        $this->assertSame(self::SSO_ID, $user->sso_id);
        $this->assertSame('ANA QUISPE', $user->name);
        $this->assertSame('token-local', $result['token']);
    }

    public function test_a_payload_wrapped_in_data_is_rejected(): void
    {
        $this->fakeUserInfo(['data' => ['id' => self::SSO_ID, 'email' => 'ana@example.com', 'first_name' => 'ANA']]);

        $this->assertThrows(
            fn () => app(SsoAuthenticationService::class)->exchangeToken('token-de-account'),
            RuntimeException::class,
        );

        $this->assertSame(0, TokenUser::query()->count());
    }

    public function test_a_payload_with_sso_id_instead_of_id_is_rejected(): void
    {
        $this->fakeUserInfo(['sso_id' => self::SSO_ID, 'email' => 'ana@example.com', 'first_name' => 'ANA']);

        $this->assertThrows(
            fn () => app(SsoAuthenticationService::class)->exchangeToken('token-de-account'),
            RuntimeException::class,
            'El servidor SSO no devolvió el id del usuario.',
        );

        $this->assertSame(0, TokenUser::query()->count());
    }

    public function test_the_name_comes_from_first_name_and_never_from_name(): void
    {
        $this->fakeUserInfo(['id' => self::SSO_ID, 'email' => 'ana@example.com', 'name' => 'OTRO NOMBRE', 'last_name' => 'QUISPE']);

        app(SsoAuthenticationService::class)->exchangeToken('token-de-account');

        $this->assertSame('QUISPE', TokenUser::query()->sole()->name);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fakeUserInfo(array $payload): void
    {
        Http::fake(['https://account.arsy.test/api/user' => Http::response($payload)]);
    }
}
