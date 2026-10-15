<?php

namespace Arsy\SSOClient\Tests\Feature\Http\Controllers;

use Arsy\SSOClient\Tests\TestCase;
use Illuminate\Auth\GenericUser;

class SsoLoginControllerTest extends TestCase
{
    public function test_logout_destroys_the_session_and_its_csrf_token(): void
    {
        $this->withSession(['sso_user_session_id' => 'sesion-central']);
        $session = $this->app['session.store'];
        $previousId = $session->getId();
        $previousToken = $session->token();

        $this->actingAs(new GenericUser(['id' => 7, 'remember_token' => null]))
            ->withCookie(config('session.cookie'), $previousId)
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertSame('', $session->getHandler()->read($previousId));
        $this->assertNotSame($previousToken, $session->token());
    }
}
