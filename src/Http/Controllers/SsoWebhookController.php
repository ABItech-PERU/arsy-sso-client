<?php

namespace Arsy\SSOClient\Http\Controllers;

use Arsy\SSOClient\Services\SsoWebhookHandlerService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class SsoWebhookController extends Controller
{
    protected SsoWebhookHandlerService $webhookService;

    public function __construct(SsoWebhookHandlerService $webhookService)
    {
        $this->webhookService = $webhookService;
    }

    /**
     * Recibe el webhook de la aplicación central (IDP).
     */
    public function __invoke(Request $request)
    {
        Log::info('Webhook recibido: '.json_encode($request->all()));
        
        $signature = $request->header('X-Arsy-Signature');
        $secret = config('arsy-sso.webhook_secret');
        
        // Si no hay firma o no hay secret configurado, retornar error (por seguridad).
        if (!$secret || !$signature) {
            abort(401, 'Configuración de firma inválida');
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);
        if (! hash_equals($expected, $signature)) {
            abort(401, 'Firma inválida');
        }

        $this->webhookService->handle($request->all());

        return response()->json(['success' => true, 'message' => 'Webhook procesado correctamente']);
    }
}
