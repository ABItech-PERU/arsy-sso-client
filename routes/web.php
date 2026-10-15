<?php

use Illuminate\Support\Facades\Route;
use Arsy\SSOClient\Http\Controllers\SsoLoginController;
use Arsy\SSOClient\Http\Controllers\SsoTokenController;
use Arsy\SSOClient\Http\Controllers\SsoWebhookController;

Route::middleware(['web'])->group(function () {
    if (config('arsy-sso.routes.auth', true)) {
        Route::get('/login', [SsoLoginController::class, 'login'])->name('login');
        Route::post('/logout', [SsoLoginController::class, 'logout'])->name('logout');
    }

    Route::get('/auth/silent', [SsoLoginController::class, 'silentLogin'])->name('sso.silent');
    Route::get('/auth/callback', [SsoLoginController::class, 'callback']);
});

Route::middleware(['api'])->prefix('api')->group(function () {
    Route::post('/sso/webhook', SsoWebhookController::class)->name('sso.webhook');

    // Opt-in: exige Sanctum (HasApiTokens) y columnas de token en users.
    if (config('arsy-sso.routes.token_exchange')) {
        Route::post('/auth/token', SsoTokenController::class)->name('sso.token');
    }
});
