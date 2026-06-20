<?php

namespace Arsy\SSOClient\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento disparado inmediatamente después de que un usuario inicia sesión
 * exitosamente a través del servidor central (SSO).
 * 
 * Útil para: Asignar roles locales, sincronizar datos adicionales (como el avatar),
 * o registrar logs de acceso específicos de la aplicación satélite.
 */
class SsoUserAuthenticated
{
    use Dispatchable, SerializesModels;

    public $user;
    public $idpUser;

    /**
     * @param mixed $user El modelo de usuario local de la base de datos (ej. App\Models\User).
     * @param \Laravel\Socialite\Contracts\User $idpUser Los datos puros obtenidos desde el servidor central (contiene avatar, tokens, etc.).
     */
    public function __construct($user, $idpUser)
    {
        $this->user = $user;
        $this->idpUser = $idpUser;
    }
}
