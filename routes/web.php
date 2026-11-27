<?php

use Illuminate\Support\Facades\Route;
use Abitech\Sso\Http\Controllers\SsoLoginController;
use Abitech\Sso\Http\Controllers\SsoTokenController;
use Abitech\Sso\Http\Controllers\SsoWebhookController;

Route::middleware(['web'])->group(function () {
    if (config('sso.routes.auth', true)) {
        Route::get('/login', [SsoLoginController::class, 'login'])->name('login');
        Route::post('/logout', [SsoLoginController::class, 'logout'])->name('logout');
    }

    Route::get('/auth/silent', [SsoLoginController::class, 'silentLogin'])->name('sso.silent');
    Route::get('/auth/callback', [SsoLoginController::class, 'callback']);
});

Route::middleware(['api'])->prefix('api')->group(function () {
    Route::post('/sso/webhook', SsoWebhookController::class)->name('sso.webhook');

    // Opt-in: exige Sanctum (HasApiTokens) y columnas de token en users.
    if (config('sso.routes.token_exchange')) {
        Route::post('/auth/token', SsoTokenController::class)->name('sso.token');
    }
});
