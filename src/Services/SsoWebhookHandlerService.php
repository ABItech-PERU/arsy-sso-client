<?php

namespace Arsy\SSOClient\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SsoWebhookHandlerService
{
    /**
     * Procesa el webhook recibido del servidor central.
     */
    public function handle(array $payload)
    {
        Log::info('SSO Webhook received: '.json_encode($payload));
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
                // Otros eventos como user.deleted o user.suspended pueden ser manejados aquí
        }

        return true;
    }

    /**
     * Destruye las sesiones locales para el usuario especificado.
     */
    protected function handleSessionRevoked($userId, $idpSessionId = null)
    {
        if (! $userId) {
            return;
        }

        $userModelClass = config('arsy-sso.user_model', '\\App\\Models\\User');
        $user = $userModelClass::where('idp_sub', $userId)->first();

        if ($user) {
            if ($idpSessionId) {
                // Buscamos y destruimos solo la sesión asociada al session ID del IDP
                $sessions = DB::table('sessions')->where('user_id', $user->id)->get();
                $destroyedCount = 0;

                foreach ($sessions as $session) {
                    $payload = json_decode(base64_decode($session->payload), true);
                    if (is_array($payload) && isset($payload['sso_user_session_id']) && $payload['sso_user_session_id'] === $idpSessionId) {
                        DB::table('sessions')->where('id', $session->id)->delete();
                        $destroyedCount++;
                    }
                }

                Log::info("Se destruyeron {$destroyedCount} sesión(es) para el usuario ID: {$user->id} con el IDP session ID: {$idpSessionId}");
            } else {
                // Si no hay ID de sesión, cerramos todas las sesiones del usuario como medida cautelar
                DB::table('sessions')->where('user_id', $user->id)->delete();
                Log::info("Se destruyeron TODAS las sesiones para el usuario ID: {$user->id} (no se envió IDP session ID)");
            }
        }
    }

    /**
     * Actualiza la base de datos local cuando el usuario cambia sus datos en la cuenta central.
     */
    protected function handleUserUpdated(array $data)
    {
        if (empty($data['id'])) {
            return;
        }

        $userModelClass = config('arsy-sso.user_model', '\\App\\Models\\User');
        $user = $userModelClass::where('idp_sub', $data['id'])->first();

        if ($user) {
            $user->update([
                'name' => $data['name'] ?? $user->name,
                'email' => $data['email'] ?? $user->email,
                'avatar' => $data['avatar'] ?? $user->avatar,
            ]);
        }
    }
}
