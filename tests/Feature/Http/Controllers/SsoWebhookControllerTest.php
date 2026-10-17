<?php

namespace Arsy\SSOClient\Tests\Feature\Http\Controllers;

use Arsy\SSOClient\Events\SsoWebhookUserUpdated;
use Arsy\SSOClient\Tests\Concerns\UsesUsersTable;
use Arsy\SSOClient\Tests\Fixtures\User;
use Arsy\SSOClient\Tests\TestCase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

class SsoWebhookControllerTest extends TestCase
{
    use UsesUsersTable;

    private const SECRET = 'secreto-del-webhook';

    private const SSO_ID = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

    public function test_user_updated_syncs_the_email_of_the_configured_user_model(): void
    {
        Event::fake([SsoWebhookUserUpdated::class]);
        config(['arsy-sso.webhooks.sso' => self::SECRET]);
        $user = $this->createLocalUser(['sso_id' => self::SSO_ID]);

        $this->postSigned([
            'event_type' => 'user.updated',
            'data' => ['id' => self::SSO_ID, 'email' => 'ana.nueva@arsy.test'],
        ])->assertOk();

        $this->assertSame('ana.nueva@arsy.test', $user->fresh()->email);
        Event::assertDispatched(
            SsoWebhookUserUpdated::class,
            fn (SsoWebhookUserUpdated $event) => $event->user instanceof User && $event->user->is($user),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSigned(array $payload): TestResponse
    {
        $body = json_encode($payload);

        return $this->call('POST', '/api/sso/webhook', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_ARSY_SIGNATURE' => hash_hmac('sha256', $body, self::SECRET),
        ], content: $body);
    }
}
