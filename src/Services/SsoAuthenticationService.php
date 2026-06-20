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

        // Obtener el nombre del usuario
        $name = $idpUser->getName() ?? trim(($idpUser->user['first_name'] ?? '').' '.($idpUser->user['last_name'] ?? ''));
        if (empty($name)) {
            $name = $idpUser->user['username'] ?? $idpUser->getEmail();
        }

        // Modelo de usuario configurado dinámicamente o por defecto a \App\Models\User
        $userModelClass = config('arsy-sso.user_model', '\\App\\Models\\User');

        // Buscar primero por idp_sub (el ID inmutable del SSO)
        $user = $userModelClass::where('idp_sub', (string) $idpUser->getId())->first();

        // Si no se encuentra, buscar por email para vincular cuentas existentes creadas previamente
        if (! $user) {
            $user = $userModelClass::where('email', $idpUser->getEmail())->first();
        }

        $data = [
            'idp_sub' => (string) $idpUser->getId(),
            'name' => $name,
            'email' => $idpUser->getEmail(),
            'avatar' => $idpUser->getAvatar(),
            'access_token' => 'session_stored',
            'refresh_token' => 'session_stored',
            'token_expires_at' => now()->addSeconds($idpUser->expiresIn ?? 86400),
            'last_login_at' => now(),
        ];

        if ($user) {
            $user->update($data);
        } else {
            $user = $userModelClass::create($data);
        }

        // Guardar tokens y session_id en la sesión web del navegador
        session([
            'sso_access_token' => $idpUser->token,
            'sso_refresh_token' => $idpUser->refreshToken ?? '',
            'sso_token_expires_at' => now()->addSeconds($idpUser->expiresIn ?? 86400)->toIso8601String(),
            'sso_user_session_id' => $idpUser->user['user_session_id'] ?? null,
        ]);

        Auth::login($user);

        // Disparar evento para extensibilidad sin sobrescribir clase
        event(new SsoUserAuthenticated($user));

        return $user;
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
                Log::error('Error calling IDP logout: '.$e->getMessage());
            }
        }

        Auth::logout();
    }
}
