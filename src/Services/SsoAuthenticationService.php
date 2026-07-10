<?php

namespace Arsy\SSOClient\Services;

use Arsy\SSOClient\Events\SsoUserAuthenticated;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;

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
     * Intent de autenticación silenciosa. Si el usuario ya tiene sesión en el IDP,
     * se autentica automáticamente sin mostrar ninguna pantalla de login.
     * Si no tiene sesión, redirige de vuelta sin error visible.
     */
    public function silentLogin(): ?RedirectResponse
    {
        session(['sso_silent' => true]);

        $redirectResponse = Socialite::driver('laravelpassport')
            ->with(['prompt' => 'none'])
            ->redirect();

        return $redirectResponse;
    }

    /**
     * Procesa la respuesta (callback) del servidor SSO.
     */
    public function handleCallback()
    {
        $isSilent = session('sso_silent', false);
        session()->forget('sso_silent');

        try {
            $idpUser = Socialite::driver('laravelpassport')->user();

            $user = $this->findOrCreateUser($idpUser);

            $this->storeSessionData($idpUser);

            Auth::login($user);

            event(new SsoUserAuthenticated($user, $idpUser));

            return $user;
        } catch (\Exception $e) {
            if ($isSilent) {
                return null;
            }

            Log::error('[SSO] Callback Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            throw $e;
        }
    }

    /**
     * Verifica si el usuario tiene sesion activa en el IDP.
     * En produccion con .arsy.test, lee la cookie compartida.
     * En desarrollo, intenta un HTTP HEAD al IDP.
     */
    public function hasIdpSession(): bool
    {
        if (isset($_COOKIE['arsy_logged_in']) && $_COOKIE['arsy_logged_in'] === '1') {
            return true;
        }

        return false;
    }

    /**
     * Intercambia un access token SSO por un token local (Sanctum).
     * Para apps moviles, SPAs, y herramientas de testing como Bruno/Postman.
     */
    public function exchangeToken(string $accessToken): array
    {
        $ssoUrl = config('arsy-sso.oauth_url');

        try {
            $response = Http::withToken($accessToken)
                ->accept('application/json')
                ->timeout(10)
                ->get(rtrim($ssoUrl, '/') . '/api/user');
        } catch (\Exception $e) {
            Log::error('[SSO] Token exchange error: ' . $e->getMessage());
            throw new RuntimeException('No se pudo conectar con el servidor de autenticacion.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Token SSO invalido o expirado.', 401);
        }

        $payload = $response->json('data') ?? $response->json();
        $user = $this->findOrCreateUserFromPayload($payload);

        $this->persistTokens($user, $payload);

        $token = $user->createToken('api')->plainTextToken;

        return [
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ];
    }

    /**
     * Persiste los tokens SSO en el usuario local.
     */
    private function persistTokens($user, array $payload): void
    {
        $data = ['sso_last_login_at' => now()];

        if (isset($payload['access_token'])) {
            $data['access_token'] = $payload['access_token'];
        }
        if (isset($payload['refresh_token'])) {
            $data['refresh_token'] = $payload['refresh_token'];
        }
        if (isset($payload['expires_in'])) {
            $data['token_expires_at'] = now()->addSeconds((int) $payload['expires_in']);
        }

        $user->forceFill($data)->save();
    }

    /**
     * Busca o crea el usuario local basado en el IDP (Socialite).
     */
    private function findOrCreateUser($idpUser)
    {
        $userModelClass = config('arsy-sso.user_model', '\\App\\Models\\User');

        $user = $userModelClass::where('sso_id', (string) $idpUser->getId())->first();

        if (! $user) {
            $user = $userModelClass::where('email', $idpUser->getEmail())->first();
        }

        $data = $this->mapUserData($idpUser);

        if ($user) {
            $user->forceFill($data)->save();
        } else {
            $user = $userModelClass::forceCreate($data);
        }

        return $user;
    }

    /**
     * Busca o crea el usuario local desde la respuesta JSON de /api/user.
     */
    private function findOrCreateUserFromPayload(array $payload)
    {
        $userModelClass = config('arsy-sso.user_model', '\\App\\Models\\User');
        $nameColumn = config('arsy-sso.user_name_column', 'name');
        $lastNameColumn = config('arsy-sso.user_lastname_column');
        $avatarColumn = config('arsy-sso.user_avatar_column');

        $ssoId = $payload['id'] ?? $payload['sso_id'] ?? null;
        $email = $payload['email'] ?? null;

        if (! $email) {
            throw new RuntimeException('El servidor SSO no devolvio un email de usuario.');
        }

        $user = null;
        if ($ssoId) {
            $user = $userModelClass::where('sso_id', (string) $ssoId)->first();
        }
        if (! $user) {
            $user = $userModelClass::where('email', $email)->first();
        }

        $data = [
            'sso_id' => $ssoId ? (string) $ssoId : ($user->sso_id ?? null),
            'email' => $email,
            'sso_last_login_at' => now(),
        ];

        if ($nameColumn && $lastNameColumn) {
            $data[$nameColumn] = trim($payload['first_name'] ?? $payload['name'] ?? 'Usuario');
            $data[$lastNameColumn] = trim($payload['last_name'] ?? '');
        } elseif ($nameColumn) {
            $firstName = $payload['first_name'] ?? $payload['name'] ?? 'Usuario';
            $lastName = $payload['last_name'] ?? '';
            $data[$nameColumn] = trim($firstName . ' ' . $lastName);
        }

        if ($avatarColumn && ($payload['avatar'] ?? null)) {
            $data[$avatarColumn] = $payload['avatar'];
        }

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

        $firstName = $rawUser['first_name'] ?? $idpUser->getName() ?? 'Usuario Default';
        $lastName = $rawUser['last_name'] ?? '';

        $avatarUrl = $rawUser['avatar'] ?? $idpUser->getAvatar() ?? null;

        $data = [
            'sso_id' => (string) $idpUser->getId(),
            'email' => $idpUser->getEmail(),
            'sso_last_login_at' => now(),
        ];

        if ($nameColumn && $lastNameColumn) {
            $data[$nameColumn] = trim($firstName);
            $data[$lastNameColumn] = trim($lastName);
        } elseif ($nameColumn) {
            $data[$nameColumn] = trim($firstName . ' ' . $lastName);
        }

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
