<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Repositories\TransactionRepository;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="2. Deposit",
 *     description="API Endpoints for Deposit Management"
 * )
 */
class DepositController extends Controller
{
    use ApiResponse;

    protected $TransactionRepository;

    public function __construct(TransactionRepository $TransactionRepository)
    {
        $this->TransactionRepository = $TransactionRepository;
    }

    /**
     * @OA\Get(
     *     path="/api/deposit",
     *     tags={"Deposit"},
     *     summary="Get current amount",
     *     description="Retrieve the current deposit amount",
     *     operationId="getAmount",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Amount retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="amount", type="number", example=1000.00),
     *             @OA\Property(property="currency", type="string", example="IDR")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     */
    public function index()
    {
        try {
            $result = $this->TransactionRepository->getAmount();
            return $this->successResponse($result, 'Amount retrieved successfully', 200);
        } catch (\Exception $e) {
            Log::error('Error getting amount: ' . $e->getMessage());
            return $this->errorResponse('Failed to get amount', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/deposit",
     *     tags={"Deposit"},
     *     summary="Add amount",
     *     description="Add money to the deposit amount",
     *     operationId="addAmount",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"order_id", "amount", "timestamp"},
     *             @OA\Property(property="order_id", type="string", example="1234567890"),
     *             @OA\Property(property="amount", type="number", example=1000.00),
     *             @OA\Property(property="timestamp", type="string", example="2025-03-26 10:00:00")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Amount added successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Amount added successfully"),
     *             @OA\Property(property="amount", type="number", example=2000.00),
     *             @OA\Property(property="order_id", type="string", example="1234567890"),
     *             @OA\Property(property="status", type="number", example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'order_id' => 'required|string|max:255|regex:/^[a-zA-Z0-9\-_]+$/',
                'amount' => 'required|numeric|min:0',
                'timestamp' => 'required|date_format:Y-m-d H:i:s'
            ]);

            $result = $this->TransactionRepository->addDeposit($request);
            
            if (!$result['success']) {
                return $this->errorResponse($result['message'], 500);
            }

            return $this->successResponse([
                'order_id' => $result['data']['order_id'],
                'amount' => $result['data']['amount'],
                'status' => $result['data']['status']
            ], 'Amount added successfully', 200); 
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->validator->errors()->first(), 422);
        } catch (\Exception $e) {
            Log::error('Error adding amount: ' . $e->getMessage());
            return $this->errorResponse('Failed to add amount', 500);
        }
    }
}
