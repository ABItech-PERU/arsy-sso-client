<?php

namespace Arsy\SSOClient\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento disparado cuando el servidor central envía un Webhook informando
 * que la cuenta del usuario ha sido eliminada permanentemente.
 * 
 * Útil para: Manejar limpiezas de datos locales en cascada o notificar
 * al administrador del satélite si es necesario.
 */
class SsoWebhookUserDeleted
{
    use Dispatchable, SerializesModels;

    public $user;

    /**
     * @param mixed $user El modelo de usuario local (App\Models\User) que acaba de ser eliminado (Soft Delete).
     */
    public function __construct($user)
    {
        $this->user = $user;
    }
}
