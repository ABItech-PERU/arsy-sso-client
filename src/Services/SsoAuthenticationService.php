<?php

namespace Arsy\SSOClient\Services;

use Arsy\SSOClient\Events\SsoUserAuthenticated;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use RuntimeException;
use Exception;

class SsoAuthenticationService
{
    /**
     * Redirige al proveedor de identidad (IDP) para iniciar el flujo OAuth.
     */
    public function redirect(): mixed
    {
        $redirectResponse = Socialite::driver('laravelpassport')->redirect();

        if (class_exists(Inertia::class) && request()->hasHeader('X-Inertia')) {
            return Inertia::location($redirectResponse->getTargetUrl());
        }

        return $redirectResponse;
    }

    /**
     * Inicia la autenticación silenciosa mediante OAuth (prompt=none).
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
     * Procesa el callback de OAuth y autentica al usuario localmente.
     */
    public function handleCallback(): mixed
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
        } catch (Exception $e) {
            Log::error('[SSO] Socialite Exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            if ($isSilent) {
                return null;
            }

            throw $e;
        }
    }

    /**
     * Verifica si existe una sesión activa leyendo la cookie compartida del IDP.
     */
    public function hasIdpSession(): bool
    {
        return request()->cookie('arsy_logged_in') === '1';
    }

    /**
     * Intercambia un token de acceso del IDP por un token local (API/Sanctum).
     */
    public function exchangeToken(string $accessToken): array
    {
        if (! method_exists(config('arsy-sso.user_model'), 'createToken')) {
            throw new RuntimeException('El canje de token exige que el modelo de usuario use HasApiTokens de Sanctum.', 500);
        }

        $ssoUrl = config('arsy-sso.oauth_url');

        try {
            $response = Http::withToken($accessToken)
                ->accept('application/json')
                ->timeout(10)
                ->get(rtrim($ssoUrl, '/') . '/api/user');
        } catch (Exception $e) {
            Log::error('[SSO] Token exchange error: ' . $e->getMessage());
            throw new RuntimeException('No se pudo conectar con el servidor de autenticacion.');
        }

        if (! $response->successful()) {
            throw new RuntimeException('Token SSO invalido o expirado.', 401);
        }

        $payload = $response->json();
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
     * Actualiza los tokens de acceso y refresco en el modelo de usuario.
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
     * Autentica o registra a un usuario a partir del payload de la cookie HMAC.
     */
    public function resolveCookieSession(array $payload): Authenticatable
    {
        $userModelClass = config('arsy-sso.user_model');
        $ssoId = $payload['sub'] ?? null;
        $email = $payload['email'] ?? null;

        if (! $ssoId || ! $email) {
            throw new RuntimeException('Payload de cookie inválido.');
        }

        $user = $userModelClass::where('sso_id', (string) $ssoId)->first();

        $data = [
            'sso_id' => (string) $ssoId,
            'email' => $email,
            'sso_last_login_at' => now(),
        ];

        $nameColumn = config('arsy-sso.user_name_column');
        if ($nameColumn && isset($payload['name'])) {
            $data[$nameColumn] = trim($payload['name']);
        }

        if ($user) {
            $user->forceFill($data)->save();
        } else {
            $this->rejectTakenEmail($userModelClass, $email);
            $user = $userModelClass::forceCreate($data);
        }

        Auth::login($user);

        return $user;
    }

    /**
     * Busca o registra un usuario local usando los datos de Socialite.
     */
    private function findOrCreateUser($idpUser): Authenticatable
    {
        $userModelClass = config('arsy-sso.user_model');

        $user = $userModelClass::where('sso_id', (string) $idpUser->getId())->first();

        $data = $this->mapUserData($idpUser);

        if ($user) {
            $user->forceFill($data)->save();
        } else {
            $this->rejectTakenEmail($userModelClass, $idpUser->getEmail());
            $user = $userModelClass::forceCreate($data);
        }

        return $user;
    }

    /**
     * Busca o registra un usuario local usando el payload JSON de la API del IDP.
     */
    private function findOrCreateUserFromPayload(array $payload): Authenticatable
    {
        $userModelClass = config('arsy-sso.user_model');
        $nameColumn = config('arsy-sso.user_name_column', 'name');
        $lastNameColumn = config('arsy-sso.user_lastname_column');
        $avatarColumn = config('arsy-sso.user_avatar_column');

        $ssoId = $payload['id'] ?? null;
        $email = $payload['email'] ?? null;

        if (! $email) {
            throw new RuntimeException('El servidor SSO no devolvio un email de usuario.');
        }

        if (! $ssoId) {
            throw new RuntimeException('El servidor SSO no devolvió el id del usuario.');
        }

        $user = $userModelClass::where('sso_id', (string) $ssoId)->first();

        $data = [
            'sso_id' => (string) $ssoId,
            'email' => $email,
            'sso_last_login_at' => now(),
        ];

        $firstName = $payload['first_name'] ?? '';
        $lastName = $payload['last_name'] ?? '';

        if ($nameColumn && $lastNameColumn) {
            $data[$nameColumn] = trim($firstName);
            $data[$lastNameColumn] = trim($lastName);
        } elseif ($nameColumn) {
            $data[$nameColumn] = trim($firstName . ' ' . $lastName);
        }

        if ($avatarColumn && ($payload['avatar'] ?? null)) {
            $data[$avatarColumn] = $payload['avatar'];
        }

        if ($user) {
            $user->forceFill($data)->save();
        } else {
            $this->rejectTakenEmail($userModelClass, $email);
            $user = $userModelClass::forceCreate($data);
        }

        return $user;
    }

    /**
     * El login identifica solo por `sso_id`. Una cuenta local con el mismo
     * correo se vincula a mano con `arsy-sso:link-user`, nunca aquí.
     */
    private function rejectTakenEmail(string $userModelClass, ?string $email): void
    {
        if ($email !== null && $userModelClass::where('email', $email)->exists()) {
            throw new RuntimeException('El correo ya lo usa otra cuenta local; se vincula con php artisan arsy-sso:link-user.');
        }
    }

    /**
     * Adapta los datos del usuario de Socialite al esquema de base de datos local.
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
     * Almacena los tokens y el ID de sesión en la sesión web actual.
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
     * Cierra la sesión del usuario localmente y notifica al IDP.
     */
    public function logout($user): void
    {
        $accessToken = session('sso_access_token');

        if ($accessToken) {
            try {
                Http::withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer $accessToken",
                ])->post(config('services.laravelpassport.host').'/api/logout');
            } catch (Exception $e) {
                Log::error('[SSO] Error llamando al logout del IDP: '.$e->getMessage());
            }
        }

        Auth::logout();

        session()->invalidate();
        session()->regenerateToken();
    }
}
