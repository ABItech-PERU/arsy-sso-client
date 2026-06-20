<?php

namespace Arsy\SSOClient\Services;

use Arsy\SSOClient\Events\SsoUserAuthenticated;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;

class SsoAuthenticationService
{
    /**
     * Redirige a la página de autorización del SSO.
     */
    public function redirect()
    {
        $redirectResponse = Socialite::driver('laravelpassport')->redirect();

        if (request()->hasHeader('X-Inertia')) {
            return Inertia::location($redirectResponse->getTargetUrl());
        }

        return $redirectResponse;
    }

    /**
     * Procesa la respuesta (callback) del servidor SSO.
     */
    public function handleCallback()
    {
        // Socialite maneja automáticamente PKCE y el intercambio de tokens.
        $idpUser = Socialite::driver('laravelpassport')->user();

        $user = $this->findOrCreateUser($idpUser);

        $this->storeSessionData($idpUser);

        Auth::login($user);

        // Disparar evento para extensibilidad enviando el usuario local y el payload original de Socialite
        event(new SsoUserAuthenticated($user, $idpUser));

        return $user;
    }

    /**
     * Busca o crea el usuario local basado en el IDP.
     */
    private function findOrCreateUser($idpUser)
    {
        $userModelClass = config('arsy-sso.user_model', '\\App\\Models\\User');

        // Buscar primero por sso_id (el ID inmutable del SSO)
        $user = $userModelClass::where('sso_id', (string) $idpUser->getId())->first();

        // Si no se encuentra, buscar por email para vincular cuentas existentes creadas previamente
        if (! $user) {
            $user = $userModelClass::where('email', $idpUser->getEmail())->first();
        }

        $data = $this->mapUserData($idpUser);

        // Se usa forceFill y forceCreate para no depender de que $fillable contenga los campos en la app destino
        if ($user) {
            $user->forceFill($data)->save();
        } else {
            $user = $userModelClass::forceCreate($data);
        }

        return $user;
    }

    /**
     * Mapea los datos del usuario del IDP a la estructura local.
     */
    private function mapUserData($idpUser): array
    {
        $rawUser = $idpUser->user ?? [];
        $nameColumn = config('arsy-sso.user_name_column');
        $lastNameColumn = config('arsy-sso.user_lastname_column');
        $avatarColumn = config('arsy-sso.user_avatar_column');

        // Nombres desde el IDP
        $firstName = $rawUser['first_name'] ?? $idpUser->getName() ?? 'Usuario Default';
        $lastName = $rawUser['last_name'] ?? '';
        
        // Avatar desde el IDP
        $avatarUrl = $rawUser['avatar'] ?? $idpUser->getAvatar() ?? null;

        $data = [
            'sso_id' => (string) $idpUser->getId(),
            'email' => $idpUser->getEmail(),
            'sso_last_login_at' => now(),
        ];

        // Mapeo dinámico de nombres según config
        if ($nameColumn && $lastNameColumn) {
            $data[$nameColumn] = trim($firstName);
            $data[$lastNameColumn] = trim($lastName);
        } elseif ($nameColumn) {
            $data[$nameColumn] = trim($firstName . ' ' . $lastName);
        }

        // Mapeo dinámico de avatar según config
        if ($avatarColumn && $avatarUrl) {
            $data[$avatarColumn] = $avatarUrl;
        }

        return $data;
    }

    /**
     * Guarda los tokens y session_id en la sesión web del navegador.
     */
    private function storeSessionData($idpUser): void
    {
        session([
            'sso_access_token' => $idpUser->token,
            'sso_refresh_token' => $idpUser->refreshToken ?? '',
            'sso_token_expires_at' => now()->addSeconds($idpUser->expiresIn ?? 86400)->toIso8601String(),
            'sso_user_session_id' => $idpUser->user['user_session_id'] ?? null,
        ]);
    }

    /**
     * Cierra sesión localmente y en el servidor central.
     */
    public function logout($user)
    {
        $accessToken = session('sso_access_token');

        if ($accessToken) {
            try {
                Http::withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer $accessToken",
                ])->get(config('services.laravelpassport.host').'/api/logout');
            } catch (\Exception $e) {
                Log::error('[SSO] Error llamando al logout del IDP: '.$e->getMessage());
            }
        }

        Auth::logout();
    }
}
