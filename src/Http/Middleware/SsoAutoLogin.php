<?php

namespace Arsy\SSOClient\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Arsy\SSOClient\Services\SsoAuthenticationService;

class SsoAutoLogin
{
    protected SsoAuthenticationService $authService;

    public function __construct(SsoAuthenticationService $authService)
    {
        $this->authService = $authService;
    }

    public function handle(Request $request, Closure $next)
    {
        // Usuario autenticado localmente, continuar
        if (Auth::check()) {
            return $next($request);
        }

        $method = config('arsy-sso.auto_login.method', 'cookie');

        // Método none, continuar
        if ($method === 'none') {
            return $next($request);
        }

        if ($method === 'oauth') {
            $cookieName = config('arsy-sso.cookie.name', 'ssotoken');
            
            if ($request->hasCookie($cookieName)) {
                if (!$request->session()->has('sso_silent_attempted')) {
                    $request->session()->put('sso_silent_attempted', true);
                    return redirect()->to('/auth/silent');
                }
            } else {
                $request->session()->forget('sso_silent_attempted');
            }
            
            return $next($request);
        }

        // Método cookie
        if ($method === 'cookie') {
            $cookieName = config('arsy-sso.cookie.name', 'ssotoken');
            $cookieValue = $request->cookie($cookieName);

            if ($cookieValue) {
                // Formato: payload_base64.firma
                $parts = explode('.', $cookieValue);
                if (count($parts) === 2) {
                    $payloadBase64 = $parts[0];
                    $signature = $parts[1];
                    $secret = config('arsy-sso.shared_secret');

                    $expectedSignature = hash_hmac('sha256', $payloadBase64, $secret);

                    if (hash_equals($expectedSignature, $signature)) {
                        $payload = json_decode(base64_decode($payloadBase64), true);

                        // Verificar expiración
                        if (isset($payload['exp']) && $payload['exp'] > time()) {
                            try {
                                $this->authService->resolveCookieSession($payload);
                                // Usuario logueado en la request actual
                            } catch (Exception $e) {
                                // Fallo silencioso, continuar como visitante
                                Log::warning('[SSO AutoLogin] Fallo al resolver cookie: ' . $e->getMessage());
                            }
                        }
                    }
                }
            }
        }

        return $next($request);
    }
}
