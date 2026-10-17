<?php

namespace Arsy\SSOClient\Tests\Feature\Http\Controllers;

use Arsy\SSOClient\Tests\TestCase;
use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;

class SsoLoginControllerTest extends TestCase
{
    public function test_callback_failure_shows_a_fixed_message_and_logs_the_exception(): void
    {
        Log::spy();
        $failure = new RuntimeException('SQLSTATE[HY000] detalle interno');
        Socialite::shouldReceive('driver->user')->andThrow($failure);

        $this->get('/auth/callback?code=codigo&state=estado')
            ->assertRedirect('/')
            ->assertSessionHas(
                'error',
                'No se pudo iniciar sesión con la cuenta Arsy. Inténtalo de nuevo.',
            );

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context = []) => ($context['exception'] ?? null) === $failure)
            ->once();
    }

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
