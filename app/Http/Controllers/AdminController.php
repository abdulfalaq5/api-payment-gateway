<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\TransactionModel;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\JWTException;

/**
 * @OA\Tag(
 *     name="1. Admin",
 *     description="API Endpoints for Admin Management"
 * )
 */
class AdminController extends Controller
{
    use ApiResponse;

    public function __construct()
    {
        $this->middleware('jwt.auth')->except(['login']);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/login",
     *     tags={"Admin"},
     *     summary="Admin login",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "password"},
     *             @OA\Property(property="email", type="string"),
     *             @OA\Property(property="password", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful"
     *     )
     * )
     */
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required'
            ]);

            $credentials = $request->only('email', 'password');

            if (!$token = JWTAuth::attempt($credentials)) {
                return $this->errorResponse('Invalid credentials', 401);
            }

            $user = JWTAuth::user();
            
            if (!$user->is_admin) {
                return $this->errorResponse('Unauthorized access', 403);
            }

            return $this->respondWithToken($token);

        } catch (\Exception $e) {
            Log::error('Admin login error: ' . $e->getMessage());
            return $this->errorResponse('Login failed', 500);
        }
    }

    /**
     * Get the token array structure.
     */
    protected function respondWithToken($token)
    {
        return $this->successResponse([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'status' => 1
        ], 'Login successful', 200);
    }

    /**
     * @OA\Post(
     *     path="/api/admin/logout",
     *     tags={"Admin"},
     *     summary="Admin logout",
     *     description="Logout admin user and invalidate token",
     *     operationId="adminLogout",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successfully logged out",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Successfully logged out"),
     *             @OA\Property(property="status", type="number", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated"),
     *             @OA\Property(property="status", type="number", example=0)
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Logout failed"),
     *             @OA\Property(property="status", type="number", example=0)
     *         )
     *     )
     * )
     */
    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            JWTAuth::setToken(null);
            
            return $this->successResponse(null, 'Successfully logged out', 200);
        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            return $this->errorResponse('Logout failed', 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/admin/dashboard/transactions",
     *     tags={"Admin"},
     *     summary="Get transaction data",
     *     description="Get all transaction data for admin dashboard with pagination",
     *     operationId="getTransactions",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=10)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by transaction type or amount",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success"
     *     )
     * )
     */
    public function getTransactions(Request $request)
    {
        try {
            try {
                // Verifikasi token
                if (!$token = JWTAuth::getToken()) {
                    return $this->errorResponse([
                        'message' => 'Token not provided',
                        'status' => 0,
                        'error_code' => 'TOKEN_NOT_PROVIDED'
                    ], 401);
                }

                $user = JWTAuth::authenticate($token);
                
                if (!$user) {
                    return $this->errorResponse([
                        'message' => 'User not found',
                        'status' => 0,
                        'error_code' => 'USER_NOT_FOUND'
                    ], 401);
                }

                if (!$user->is_admin) {
                    return $this->errorResponse([
                        'message' => 'Unauthorized access. Admin only',
                        'status' => 0,
                        'error_code' => 'NOT_ADMIN'
                    ], 403);
                }

            } catch (TokenExpiredException $e) {
                return $this->errorResponse([
                    'message' => 'Token has expired',
                    'status' => 0,
                    'error_code' => 'TOKEN_EXPIRED'
                ], 401);

            } catch (TokenInvalidException $e) {
                return $this->errorResponse([
                    'message' => 'Token is invalid',
                    'status' => 0,
                    'error_code' => 'TOKEN_INVALID'
                ], 401);

            } catch (JWTException $e) {
                return $this->errorResponse([
                    'message' => 'Token error: ' . $e->getMessage(),
                    'status' => 0,
                    'error_code' => 'TOKEN_ERROR'
                ], 401);
            }

            // Jika autentikasi berhasil, lanjutkan dengan query transaksi
            $perPage = $request->input('per_page', 10);
            $search = $request->input('search');

            $query = TransactionModel::with('statusTransaction')
                ->when($search, function ($query) use ($search) {
                    return $query->where(function ($q) use ($search) {
                        $q->where('type_transaction', 'like', "%{$search}%")
                          ->orWhere('amount', 'like', "%{$search}%");
                    });
                })
                ->orderBy('created_at', 'desc');

            $transactions = $query->paginate($perPage);

            // Transform data tanpa relasi user
            $formattedTransactions = $transactions->through(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'type' => $transaction->type_transaction == 1 ? 'Deposit' : 'Withdrawal',
                    'amount' => format_decimal($transaction->amount),
                    'description' => $transaction->description,
                    'status' => $transaction->statusTransaction->name,
                    'date' => $transaction->transaction_date
                ];
            });

            return $this->successResponse([
                'transactions' => [
                    'current_page' => $transactions->currentPage(),
                    'data' => $formattedTransactions,
                    'first_page_url' => $transactions->url(1),
                    'from' => $transactions->firstItem(),
                    'last_page' => $transactions->lastPage(),
                    'last_page_url' => $transactions->url($transactions->lastPage()),
                    'next_page_url' => $transactions->nextPageUrl(),
                    'per_page' => $transactions->perPage(),
                    'prev_page_url' => $transactions->previousPageUrl(),
                    'to' => $transactions->lastItem(),
                    'total' => $transactions->total(),
                ],
                'status' => 1
            ], 'Success', 200);

        } catch (\Exception $e) {
            Log::error('Error in getTransactions: ' . $e->getMessage());
            return $this->errorResponse([
                'message' => 'An error occurred while processing your request',
                'status' => 0,
                'error_code' => 'SERVER_ERROR'
            ], 500);
        }
    }
} 