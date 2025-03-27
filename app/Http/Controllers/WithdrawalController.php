<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Repositories\TransactionRepository;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="3. Withdrawal",
 *     description="API Endpoints for Withdrawal Management"
 * )
 */
class WithdrawalController extends Controller
{
    use ApiResponse;

    protected $TransactionRepository;

    public function __construct(TransactionRepository $TransactionRepository)
    {
        $this->TransactionRepository = $TransactionRepository;
    }

    /**
     * @OA\Post(
     *     path="/api/withdrawal",
     *     tags={"Withdrawal"},
     *     summary="Withdrawal amount",
     *     description="Withdrawal amount from the deposit",
     *     operationId="withdrawalAmount",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount"},
     *             @OA\Property(property="amount", type="number", example=1000.00)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Amount added successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Amount added successfully"),
     *             @OA\Property(property="amount", type="number", example=2000.00),
     *             @OA\Property(property="new_amount", type="number", example=1000.00),
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
                'amount' => 'required|numeric|min:0'
            ]);

            $result = $this->TransactionRepository->withdrawalAmount($request);
            
            if (!$result['success']) {
                return $this->errorResponse($result['message'], 500);
            }

            return $this->successResponse([
                'amount' => $result['data']['amount'],
                'new_amount' => $result['data']['new_amount'],
                'status' => $result['data']['status']
            ], 'Withdrawal successfully', 200); 
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->validator->errors()->first(), 422);
        } catch (\Exception $e) {
            Log::error('Error withdrawal amount: ' . $e->getMessage());
            return $this->errorResponse('Failed to withdrawal amount', 500);
        }
    }
}
