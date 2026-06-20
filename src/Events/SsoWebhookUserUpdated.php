<?php

namespace Arsy\SSOClient\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento disparado cuando el servidor central envía un Webhook informando
 * que el usuario ha modificado su perfil (ej. cambió su nombre, correo o foto).
 * 
 * Útil para: Actualizar columnas personalizadas en la base de datos local
 * (ej. descargar la nueva foto de perfil y guardarla localmente).
 */
class SsoWebhookUserUpdated
{
    use Dispatchable, SerializesModels;

    public $user;
    public $payload;

    /**
     * @param mixed $user El modelo de usuario local actualizado (App\Models\User).
     * @param array $payload Los datos completos enviados por el Webhook (puede incluir avatar, etc.).
     */
    public function __construct($user, array $payload)
    {
        $this->user = $user;
        $this->payload = $payload;
    }
}
