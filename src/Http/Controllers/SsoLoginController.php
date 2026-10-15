<?php

namespace Arsy\SSOClient\Http\Controllers;

use Illuminate\Routing\Controller;
use Arsy\SSOClient\Services\SsoAuthenticationService;
use Illuminate\Support\Facades\Log;

class SsoLoginController extends Controller
{
    protected SsoAuthenticationService $ssoAuthService;

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
            Log::error('[SSO] Falló el callback OAuth.', ['exception' => $e]);

            return redirect('/')->with('error', 'No se pudo iniciar sesión con la cuenta Arsy. Inténtalo de nuevo.');
        }
    }

    public function logout()
    {
        $user = auth()->user();

        if ($user) {
            $this->ssoAuthService->logout($user);
        }

        $cookieName = config('arsy-sso.cookie.name', 'ssotoken');
        $domain = config('arsy-sso.cookie.domain');

        return redirect('/')
            ->withCookie(cookie()->forget($cookieName, '/', $domain))
            ->withCookie(cookie()->forget('arsy_logged_in', '/', $domain));
    }
}
