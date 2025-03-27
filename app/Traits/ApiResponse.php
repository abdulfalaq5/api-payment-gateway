<?php

namespace App\Traits;

trait ApiResponse
{
    /**
     * Success Response
     */
    protected function successResponse($data, $message = null, $code = 200)
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], $code);
    }

    /**
     * Error Response
     */
    protected function errorResponse($message, $code = 400)
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
        ], $code);
    }

    /**
     * Response with token
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => config('jwt.ttl') * 60
            ]
        ], 200);
    }
} 