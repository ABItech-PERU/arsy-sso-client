<?php

namespace Arsy\SSOClient\Tests\Feature\Console;

use Arsy\SSOClient\Tests\Concerns\UsesUsersTable;
use Arsy\SSOClient\Tests\TestCase;
use Illuminate\Support\Facades\Log;

class LinkUserCommandTest extends TestCase
{
    use UsesUsersTable;

    private const SSO_ID = '9b1d6f3e-2c4a-4f8e-9a7b-1c2d3e4f5a6b';

    public function test_links_an_unlinked_local_account_and_logs_it(): void
    {
        Log::spy();
        $user = $this->createLocalUser(['sso_id' => null]);

        $this->artisan('arsy-sso:link-user', ['email' => 'ana@arsy.test', 'sso_id' => self::SSO_ID])
            ->assertSuccessful();

        $this->assertSame(self::SSO_ID, $user->fresh()->sso_id);
        Log::shouldHaveReceived('notice')
            ->withArgs(fn (string $message, array $context = []) => $context === [
                'user_id' => $user->id,
                'sso_id' => self::SSO_ID,
            ])
            ->once();
    }

    public function test_refuses_an_account_already_linked(): void
    {
        $user = $this->createLocalUser(['sso_id' => '0f0e0d0c-0b0a-4908-8706-050403020100']);

        $this->artisan('arsy-sso:link-user', ['email' => 'ana@arsy.test', 'sso_id' => self::SSO_ID])
            ->expectsOutputToContain('ya está vinculada')
            ->assertFailed();

        $this->assertSame('0f0e0d0c-0b0a-4908-8706-050403020100', $user->fresh()->sso_id);
    }

    public function test_refuses_an_sso_id_used_by_another_account(): void
    {
        $this->createLocalUser(['email' => 'otra@arsy.test', 'sso_id' => self::SSO_ID]);
        $user = $this->createLocalUser(['sso_id' => null]);

        $this->artisan('arsy-sso:link-user', ['email' => 'ana@arsy.test', 'sso_id' => self::SSO_ID])
            ->expectsOutputToContain('ya pertenece a otra cuenta')
            ->assertFailed();

        $this->assertNull($user->fresh()->sso_id);
    }

    public function test_refuses_an_sso_id_that_is_not_a_uuid(): void
    {
        $user = $this->createLocalUser(['sso_id' => null]);

        $this->artisan('arsy-sso:link-user', ['email' => 'ana@arsy.test', 'sso_id' => '42'])
            ->expectsOutputToContain('UUID')
            ->assertFailed();

        $this->assertNull($user->fresh()->sso_id);
    }

    public function test_fails_when_no_local_account_has_the_email(): void
    {
        $this->artisan('arsy-sso:link-user', ['email' => 'nadie@arsy.test', 'sso_id' => self::SSO_ID])
            ->expectsOutputToContain('No hay una cuenta local')
            ->assertFailed();
    }
}
