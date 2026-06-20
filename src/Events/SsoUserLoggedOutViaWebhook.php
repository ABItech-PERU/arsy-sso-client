<?php

namespace Arsy\SSOClient\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento disparado cuando el servidor central envía un Webhook informando
 * que el usuario cerró sesión en otra aplicación o su sesión fue revocada.
 * 
 * Útil para: Manejar el cierre de sesión manual si tu aplicación satélite no
 * usa base de datos para manejar las sesiones (ej. si usas Redis o File).
 */
class SsoUserLoggedOutViaWebhook
{
    use Dispatchable, SerializesModels;

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
