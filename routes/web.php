<?php

use Illuminate\Support\Facades\Route;
use Arsy\SSOClient\Http\Controllers\SsoLoginController;
use Arsy\SSOClient\Http\Controllers\SsoWebhookController;

Route::middleware(['web'])->group(function () {
    Route::get('/login', [SsoLoginController::class, 'getLogin'])->name('login');
    Route::get('/auth/callback', [SsoLoginController::class, 'getCallback']);
    Route::post('/logout', [SsoLoginController::class, 'getLogout'])->name('logout');
});

Route::middleware(['api'])->prefix('api')->group(function () {
    Route::post('/sso/webhook', SsoWebhookController::class)->name('sso.webhook');
});
