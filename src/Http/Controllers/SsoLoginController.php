<?php

namespace Arsy\SSOClient\Http\Controllers;

use Illuminate\Routing\Controller;
use Arsy\SSOClient\Services\SsoAuthenticationService;
use Illuminate\Support\Facades\Log;

class SsoLoginController extends Controller
{
    protected $ssoAuthService;

    public function __construct(SsoAuthenticationService $ssoAuthService)
    {
        $this->ssoAuthService = $ssoAuthService;
    }

    public function login()
    {
        return $this->ssoAuthService->redirect();
    }

    public function callback()
    {
        try {
            $user = $this->ssoAuthService->handleCallback();

            // Usar redirect()->intended() asegura que si el usuario intentaba entrar a /perfil
            // y fue forzado a loguearse, regrese a /perfil. Si entró directo al login, irá al redirectPath.
            $redirectPath = config('arsy-sso.redirect_after_login', '/dashboard');
            return redirect()->intended($redirectPath);
        } catch (\Exception $e) {
            Log::error('[SSO] Callback Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect('/')->with('error', 'Error al autenticar con el servidor central: '.$e->getMessage());
        }
    }

    public function logout()
    {
        $user = auth()->user();

        if ($user) {
            $this->ssoAuthService->logout($user);
        }

        return redirect('/');
    }
}
