<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

/**
 * @OA\Tag(
 *     name="1. Authentication",
 *     description="API Endpoints for Authentication"
 * )
 */
class AuthController extends Controller
{
    use ApiResponse;

    /**
     * @OA\Get(
     *     path="/api/login",
     *     tags={"Authentication"},
     *     summary="Get Falaq token",
     *     description="Generate a base64 encoded token with nur muhammad abdul falaq prefix",
     *     operationId="getFalaqToken",
     *     @OA\Response(
     *         response=200,
     *         description="Token generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="access_token", type="string", example="WmFsYXFfMTcwOTg3NjU0Mw=="),
     *             @OA\Property(property="token_type", type="string", example="bearer"),
     *             @OA\Property(property="expires_in", type="integer", example=3600)
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Login failed")
     *         )
     *     )
     * )
     */
    public function login()
    {
        try {
            $token = base64_encode(config('app.token_name') . '_' . time());

            return $this->respondWithToken($token);
        } catch (\Exception $e) {
            return $this->errorResponse('Login failed', 500);
        }
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => 3600 // 1 hour in seconds
        ]);
    }
}
