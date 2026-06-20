<?php

namespace Arsy\SSOClient\Http\Controllers;

use Arsy\SSOClient\Services\SsoAuthenticationService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class SsoLoginController extends Controller
{
    protected SsoAuthenticationService $ssoAuthService;

    public function __construct(SsoAuthenticationService $ssoAuthService)
    {
        $this->ssoAuthService = $ssoAuthService;
    }

    public function getLogin()
    {
        return $this->ssoAuthService->redirect();
    }

    public function getCallback()
    {
        try {
            $user = $this->ssoAuthService->handleCallback();

            // Usar la ruta configurada en el paquete
            $redirectPath = config('arsy-sso.redirect_after_login', '/dashboard');
            return redirect($redirectPath);
        } catch (\Exception $e) {
            Log::error('SSO Callback Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect('/')->with('error', 'Error al autenticar con el servidor central: '.$e->getMessage());
        }
    }

    public function getLogout(Request $request)
    {
        $this->ssoAuthService->logout($request->user());

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
