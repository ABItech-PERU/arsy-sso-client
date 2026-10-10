<?php

namespace Arsy\SSOClient\Listeners;

use Arsy\SSOClient\Services\SsoSessionIndex;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;

/**
 * Mantiene SsoSessionIndex al día. `Authenticated` se dispara al iniciar
 * sesión y en cada petición autenticada: registra el id vigente (también
 * tras regenerarlo) y renueva la caducidad del índice.
 */
class SessionIndexSubscriber
{
    public function __construct(private readonly SsoSessionIndex $index) {}

    public function handleAuthenticated(Authenticated $event): void
    {
        if (! $this->index->enabled() || ! request()->hasSession()) {
            return;
        }

        $session = request()->session();

        $this->index->track(
            $event->user->getAuthIdentifier(),
            $session->getId(),
            $session->get('sso_user_session_id'),
        );
    }

    public function handleLogout(Logout $event): void
    {
        if (! $event->user || ! $this->index->enabled() || ! request()->hasSession()) {
            return;
        }

        $this->index->forget($event->user->getAuthIdentifier(), request()->session()->getId());
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Authenticated::class => 'handleAuthenticated',
            Logout::class => 'handleLogout',
        ];
    }
}
