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
     * Procesa el webhook recibido del servidor central.
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

        return true;
    }

    /**
     * Destruye las sesiones locales para el usuario especificado.
     */
    protected function handleSessionRevoked($userId, $idpSessionId = null): void
    {
        if (! $userId) {
            return;
        }

        $userModelClass = config('arsy-sso.user_model', '\\App\\Models\\User');
        $user = $userModelClass::where('sso_id', $userId)->first();

        if ($user) {
            // Disparamos un evento genérico siempre. Así, si la app no usa BD para sesiones,
            // puede escuchar este evento y destruir sus sesiones (ej. Redis) manualmente.
            event(new SsoUserLoggedOutViaWebhook($user, $idpSessionId));

            if (! config('arsy-sso.auto_revoke_sessions', true)) {
                Log::info("[SSO] Webhook de logout recibido. 'auto_revoke_sessions' está desactivado. Delegando al evento.");
                return;
            }

            if (config('session.driver') === 'database') {
                if ($idpSessionId) {
                    // Buscamos y destruimos solo la sesión asociada al session ID del IDP
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
                    // Si no hay ID de sesión, cerramos todas las sesiones del usuario como medida cautelar
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                    Log::info("[SSO] Se destruyeron TODAS las sesiones para el usuario ID: {$user->id} (no se envió IDP session ID)");
                }
            } else {
                Log::warning("[SSO] Webhook de logout recibido, pero el driver de sesión no es 'database' (" . config('session.driver') . "). Se disparó el evento SsoUserLoggedOutViaWebhook para manejo manual.");
            }
        }
    }

    /**
     * Actualiza la base de datos local cuando el usuario cambia sus datos en la cuenta central.
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
            
            // Disparar evento para que la aplicación satélite capture campos extra (ej: avatar)
            event(new SsoWebhookUserUpdated($user, $data));
        }
    }

    /**
     * Elimina lógicamente (Soft Delete) al usuario y destruye sus sesiones.
     */
    protected function handleUserDeleted(array $data): void
    {
        if (empty($data['id'])) {
            return;
        }

        $userModelClass = config('arsy-sso.user_model', '\\App\\Models\\User');
        $user = $userModelClass::where('sso_id', $data['id'])->first();

        if ($user) {
            // Reutilizamos la función para destruir todas sus sesiones (forzamos $idpSessionId = null para borrar todo)
            $this->handleSessionRevoked($user->sso_id, null);

            // Realizamos Soft Delete si el modelo lo soporta, o Delete físico en su defecto
            if (method_exists($user, 'delete')) {
                $user->delete();
                Log::info("[SSO] El usuario con ID {$user->id} fue eliminado por instrucción de la Central.");
            }

            event(new SsoWebhookUserDeleted($user));
        }
    }

    /**
     * Destruye las sesiones del usuario cuando su cuenta es suspendida.
     */
    protected function handleUserSuspended(array $data): void
    {
        if (empty($data['id'])) {
            return;
        }

        $userModelClass = config('arsy-sso.user_model', '\\App\\Models\\User');
        $user = $userModelClass::where('sso_id', $data['id'])->first();

        if ($user) {
            // Destruimos todas sus sesiones
            $this->handleSessionRevoked($user->sso_id, null);
            
            Log::info("[SSO] La cuenta del usuario ID {$user->id} fue suspendida. Se cerraron todas sus sesiones.");

            event(new SsoWebhookUserSuspended($user));
        }
    }

    /**
     * Decodifica el payload de la sesión, soportando tanto JSON como PHP serializado.
     *
     * Laravel 13+ almacena las sesiones como base64(JSON), mientras que versiones anteriores usan base64(PHP serializado).
     */
    protected function decodeSessionPayload(string $encodedPayload): ?array
    {
        $decoded = base64_decode($encodedPayload);
        // Intentar primero como JSON
        $payload = json_decode($decoded, true);
        if (is_array($payload)) {
            return $payload;
        }
        // Fallback: PHP serializado
        $payload = @unserialize($decoded);
        if (is_array($payload)) {
            return $payload;
        }
        Log::warning("[SSO] No se pudo decodificar el payload de la sesión. Formato no reconocido.");
        return null;
    }
}
