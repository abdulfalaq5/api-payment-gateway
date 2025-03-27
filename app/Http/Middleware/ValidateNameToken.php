<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ValidateNameToken
{
    public function handle(Request $request, Closure $next)
    {
        try {
            $token = $request->bearerToken();
            
            if (!$token) {
                return response()->json(['message' => 'Token not provided'], 401);
            }

            // Decode the base64 token
            $decodedToken = base64_decode($token);
            
            // Split the token into parts (falaq_userId_timestamp)
            $parts = explode('_', $decodedToken);
            
            if (count($parts) !== 2 || $parts[0] !== config('app.token_name')) {
                return response()->json(['message' => 'Invalid token format'], 401);
            }

            $timestamp = $parts[1];

            // Check if token is expired (1 hour)
            if (time() - $timestamp > 3600) {
                return response()->json(['message' => 'Token expired'], 401);
            }

            return $next($request);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid token'], 401);
        }
    }
} 