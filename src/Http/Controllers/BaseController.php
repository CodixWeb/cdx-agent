<?php

declare(strict_types=1);

namespace Codix\CdxAgent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

abstract class BaseController extends Controller
{
    /**
     * Return a success response.
     */
    protected function success(string $message, array $data = []): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'message' => $message,
            'data' => $data,
        ]);
    }

    /**
     * Return an error response.
     */
    protected function error(string $message, int $status = 400, ?string $error = null): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => $message,
            'error' => $error ?? $message,
        ], $status);
    }
}
