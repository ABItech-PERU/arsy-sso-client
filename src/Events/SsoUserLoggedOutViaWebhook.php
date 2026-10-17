<?php

namespace Arsy\SSOClient\Events;

use Illuminate\Queue\SerializesModels;

/**
 * Evento disparado cuando el servidor central envía un Webhook informando
 * que el usuario cerró sesión en otra aplicación o su sesión fue revocada.
 * 
 * Útil para: efectos propios del satélite o cierre manual si
 * 'auto_revoke_sessions' está desactivado o el driver de sesión no es Redis.
 */
class SsoUserLoggedOutViaWebhook
{
    use SerializesModels;

    public $user;
    public $idpSessionId;

    /**
     * @param mixed $user El modelo de usuario local (App\Models\User).
     * @param string|null $idpSessionId El ID de sesión específico del IDP que fue revocado.
     */
    public function __construct($user, $idpSessionId = null)
    {
        $this->user = $user;
        $this->idpSessionId = $idpSessionId;
    }
}
