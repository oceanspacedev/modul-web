<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

/**
 * Format API responses with a consistent envelope.
 *
 * Responses are built fresh on every call so no state leaks between
 * requests served by the same PHP process (tests, Octane, queue workers).
 */
class ResponseFormatter
{
    /**
     * Give success response.
     */
    public static function success($data = null, $message = null): JsonResponse
    {
        return static::respond('success', 200, $message, $data);
    }

    /**
     * Give error response.
     */
    public static function error($data = null, $message = null, $code = 400): JsonResponse
    {
        return static::respond('error', $code, $message, $data);
    }

    protected static function respond(string $status, int $code, $message, $data): JsonResponse
    {
        return response()->json([
            'meta' => [
                'code' => $code,
                'status' => $status,
                'message' => $message,
            ],
            'data' => $data,
        ], $code);
    }
}
