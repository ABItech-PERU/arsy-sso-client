<?php

namespace Arsy\SSOClient\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Arsy\SSOClient\Services\SsoWebhookHandlerService;

class SsoWebhookController extends Controller
{
    protected $webhookService;

    public function __construct(SsoWebhookHandlerService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Recibe los webhooks del servidor central.
     */
    public function __invoke(Request $request)
    {
        Log::info('[SSO] Webhook recibido: '.json_encode($request->all()));
        
        $signature = $request->header('X-Arsy-Signature');
        $secret = config('arsy-sso.webhook_secret');

        // Si no hay firma o no hay secret configurado, retornar error (por seguridad).
        if (!$secret || !$signature) {
            abort(401, '[SSO] Configuración de firma inválida');
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        if (!hash_equals($expected, $signature)) {
            abort(401, '[SSO] Firma inválida');
        }

        $this->webhookService->handle($request->all());

        return response()->json(['status' => 'success']);
    }
}
