<?php

namespace Arsy\SSOClient\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Arsy\SSOClient\Events\SsoUserLoggedOutViaWebhook;
use Arsy\SSOClient\Events\SsoWebhookUserUpdated;
use Arsy\SSOClient\Events\SsoWebhookUserDeleted;
use Arsy\SSOClient\Events\SsoWebhookUserSuspended;

class SsoWebhookHandlerService
{
    /**
     * Procesa el webhook recibido del proveedor de identidad (IDP).
     */
    public function handle(array $payload): void
    {
        $eventType = $payload['event_type'] ?? null;
        $data = $payload['data'] ?? [];

        switch ($eventType) {
            case 'app.revoked':
            case 'session.revoked':
            case 'session.ended':
            case 'user.logout':
                $this->handleSessionRevoked($data['user_id'] ?? null, $data['session_id'] ?? null);
                break;
            case 'user.updated':
                $this->handleUserUpdated($data);
                break;
            case 'user.deleted':
                $this->handleUserDeleted($data);
                break;
            case 'user.suspended':
                $this->handleUserSuspended($data);
                break;
        }
    }

    /**
     * Destruye las sesiones locales del usuario especificado.
     */
    protected function handleSessionRevoked($userId, $idpSessionId = null): void
    {
        if (! $userId) {
            return;
        }

        $userModelClass = config('arsy-sso.user_model', '\\App\\Models\\User');
        $user = $userModelClass::where('sso_id', $userId)->first();

        if ($user) {
            // Dispara evento genérico para drivers de sesión externos (ej. Redis)
            event(new SsoUserLoggedOutViaWebhook($user, $idpSessionId));

            if (! config('arsy-sso.auto_revoke_sessions', true)) {
                Log::info("[SSO] Webhook de logout recibido. 'auto_revoke_sessions' está desactivado. Delegando al evento.");
                return;
            }

            if (config('session.driver') === 'database') {
                if ($idpSessionId) {
                    // Destruye únicamente la sesión asociada al IDP
                    $sessions = DB::table('sessions')->where('user_id', $user->id)->get();
                    $destroyedCount = 0;

                    foreach ($sessions as $session) {
                            $payload = $this->decodeSessionPayload($session->payload);
                            if (is_array($payload) && isset($payload['sso_user_session_id']) && $payload['sso_user_session_id'] === $idpSessionId) {
                            DB::table('sessions')->where('id', $session->id)->delete();
                            $destroyedCount++;
                        }
                    }

                    Log::info("[SSO] Se destruyeron {$destroyedCount} sesión(es) para el usuario ID: {$user->id} con el IDP session ID: {$idpSessionId}");
                } else {
                    // Destruye todas las sesiones por seguridad (sin session ID)
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                    Log::info("[SSO] Se destruyeron TODAS las sesiones para el usuario ID: {$user->id} (no se envió IDP session ID)");
                }
            } else {
                Log::warning("[SSO] Webhook de logout recibido, pero el driver de sesión no es 'database' (" . config('session.driver') . "). Se disparó el evento SsoUserLoggedOutViaWebhook para manejo manual.");
            }
        }
    }

    /**
     * Actualiza los datos locales del usuario según los cambios en el IDP.
     */
    protected function handleUserUpdated(array $data): void
    {
        if (empty($data['id'])) {
            return;
        }

        $userModelClass = config('arsy-sso.user_model', '\\App\\Models\\User');
        $user = $userModelClass::where('sso_id', $data['id'])->first();

        if ($user) {
            $user->forceFill([
                'email' => $data['email'] ?? $user->email,
            ])->save();
            
            // Dispara evento para sincronizar campos adicionales (ej. avatar)
            event(new SsoWebhookUserUpdated($user, $data));
        }
    }

    /**
     * Elimina al usuario localmente y destruye sus sesiones activas.
     */
    protected function handleUserDeleted(array $data): void
    {
        if (empty($data['id'])) {
            return;
        }

        $userModelClass = config('arsy-sso.user_model', '\\App\\Models\\User');
        $user = $userModelClass::where('sso_id', $data['id'])->first();

        if ($user) {
            // Destruye todas las sesiones activas del usuario
            $this->handleSessionRevoked($user->sso_id, null);

            // Aplica Soft Delete si es compatible, de lo contrario Hard Delete
            if (method_exists($user, 'delete')) {
                $user->delete();
                Log::info("[SSO] El usuario con ID {$user->id} fue eliminado por instrucción de la Central.");
            }

            event(new SsoWebhookUserDeleted($user));
        }
    }

    /**
     * Destruye las sesiones del usuario tras recibir una suspensión.
     */
    protected function handleUserSuspended(array $data): void
    {
        if (empty($data['id'])) {
            return;
        }

        $userModelClass = config('arsy-sso.user_model', '\\App\\Models\\User');
        $user = $userModelClass::where('sso_id', $data['id'])->first();

        if ($user) {
            // Destruye todas las sesiones activas del usuario
            $this->handleSessionRevoked($user->sso_id, null);
            
            Log::info("[SSO] La cuenta del usuario ID {$user->id} fue suspendida. Se cerraron todas sus sesiones.");

            event(new SsoWebhookUserSuspended($user));
        }
    }

    /**
     * Decodifica el payload de la sesión (soporta JSON y PHP serializado).
     */
    protected function decodeSessionPayload(string $encodedPayload): ?array
    {
        $decoded = base64_decode($encodedPayload);
        // Intenta decodificar JSON (Laravel 13+)
        $payload = json_decode($decoded, true);
        if (is_array($payload)) {
            return $payload;
        }
        // Fallback a PHP serializado (versiones anteriores)
        $payload = @unserialize($decoded);
        if (is_array($payload)) {
            return $payload;
        }
        Log::warning("[SSO] No se pudo decodificar el payload de la sesión. Formato no reconocido.");
        return null;
    }
}
