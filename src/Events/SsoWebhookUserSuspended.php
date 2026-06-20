<?php

namespace Arsy\SSOClient\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento disparado cuando el servidor central envía un Webhook informando
 * que la cuenta del usuario ha sido suspendida/bloqueada.
 * 
 * Útil para: Registrar auditorías de seguridad, o cambiar un estado
 * interno "is_active" si la aplicación satélite maneja dicha columna.
 */
class SsoWebhookUserSuspended
{
    use Dispatchable, SerializesModels;

    public $user;

    /**
     * @param mixed $user El modelo de usuario local (App\Models\User).
     */
    public function __construct($user)
    {
        $this->user = $user;
    }
}
