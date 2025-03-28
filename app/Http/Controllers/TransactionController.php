<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Repositories\TransactionRepository;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="4. Transaction",
 *     description="API Endpoints for Transaction Management"
 * )
 */
class TransactionController extends Controller
{
    use ApiResponse;

    protected $TransactionRepository;

    public function __construct(TransactionRepository $TransactionRepository)
    {
        $this->TransactionRepository = $TransactionRepository;
    }

    /**
     * @OA\Get(
     *     path="/api/transaction",
     *     tags={"Transaction"},
     *     summary="Get transaction history",
     *     description="Get deposit and withdrawal transaction history with time filter",
     *     operationId="transactionHistory",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Transaction type (deposit/withdrawal)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"deposit", "withdrawal"})
     *     ),
     *     @OA\Parameter(
     *         name="filter",
     *         in="query",
     *         description="Time filter (day/month/year)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"day", "month", "year"})
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success get transaction history",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="type", type="string"),
     *                 @OA\Property(property="amount", type="number"),
     *                 @OA\Property(property="transaction_date", type="string", format="datetime"),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(property="description", type="string")
     *             )),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="last_page", type="integer")
     *             )
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
    public function index(Request $request)
    {
        try {
            $request->validate([
                'type' => 'nullable|in:deposit,withdrawal',
                'filter' => 'nullable|in:day,month,year',
                'per_page' => 'nullable|integer|min:1|max:100',
                'page' => 'nullable|integer|min:1'
            ]);

            $result = $this->TransactionRepository->getTransactionHistory(
                $request->type,
                $request->filter,
                $request->per_page,
                $request->page
            );
            
            if (!$result['success']) {
                return $this->errorResponse($result['message'], 422);
            }

            return $this->successResponse(
                $result['data'],
                'Transaction history retrieved successfully',
                200,
                $result['pagination'] ?? null
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->validator->errors()->first(), 422);
        } catch (\Exception $e) {
            Log::error('Error getting transaction history: ' . $e->getMessage());
            return $this->errorResponse('Failed to get transaction history', 500);
        }
    }
}
