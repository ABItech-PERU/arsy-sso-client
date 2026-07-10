<?php

namespace Arsy\SSOClient\Http\Controllers;

use Arsy\SSOClient\Services\SsoAuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use RuntimeException;

class SsoTokenController extends Controller
{
    public function __construct(
        protected SsoAuthenticationService $auth,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'access_token' => ['required', 'string'],
        ]);

        try {
            $result = $this->auth->exchangeToken(
                $request->input('access_token'),
            );

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Token local generado.',
            ]);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => null,
            ], $e->getCode() ?: 401);
        }
    }
}
