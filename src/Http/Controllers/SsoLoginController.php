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

    public function silentLogin()
    {
        return $this->ssoAuthService->silentLogin();
    }

    public function callback()
    {
        $isSilent = session('sso_silent', false);

        try {
            $user = $this->ssoAuthService->handleCallback();

            if (! $user && $isSilent) {
                return redirect()->intended(config('arsy-sso.redirect_after_login', '/'));
            }

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
