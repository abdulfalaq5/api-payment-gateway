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
    protected $midtrans;

    public function __construct(TransactionRepository $TransactionRepository)
    {
        $this->TransactionRepository = $TransactionRepository;
        
        if (config('midtrans.use')) {
            \Midtrans\Config::$serverKey = config('midtrans.server_key');
            \Midtrans\Config::$isProduction = config('midtrans.is_production');
            \Midtrans\Config::$isSanitized = config('midtrans.is_sanitized');
            \Midtrans\Config::$is3ds = config('midtrans.is_3ds');
        }
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
                'order_id' => 'required|string|max:255|regex:/^[a-zA-Z0-9\-_]+$/|unique:transactions,order_id',
                'amount' => 'required|numeric|min:0',
                'timestamp' => 'required|date_format:Y-m-d H:i:s'
            ]);
            $snapToken = null;
            if (config('midtrans.use')) {
                // Persiapkan parameter untuk Midtrans
                $params = $this->prepareMidtransParameters($request);

                // Buat transaksi Snap Midtrans
                $snapToken = \Midtrans\Snap::createTransaction($params)->token;
            }

            // Simpan data transaksi
            $result = $this->TransactionRepository->addDeposit($request, $snapToken);
            
            if (!$result['success']) {
                return $this->errorResponse($result['message'], 500);
            }

            if (config('midtrans.use')) {
                $response = [
                    'order_id' => $result['data']['order_id'],
                    'amount' => $result['data']['amount'],
                    'status' => $result['data']['status'],
                    'snap_token' => $snapToken,
                    'redirect_url' => config('midtrans.is_production') ? config('midtrans.url') . $snapToken : config('midtrans.sandbox_url') . $snapToken
                ];
            } else {
                $response = [
                    'order_id' => $result['data']['order_id'],
                    'amount' => $result['data']['amount'],
                    'status' => $result['data']['status']
                ];
            }

            return $this->successResponse($response, 'Payment link generated successfully', 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->validator->errors()->first(), 422);
        } catch (\Exception $e) {
            Log::error('Error creating payment: ' . $e->getMessage());
            return $this->errorResponse('Failed to create payment', 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/deposit/callback",
     *     tags={"Deposit"},
     *     summary="Midtrans payment callback",
     *     description="Handle Midtrans payment notification",
     *     @OA\RequestBody(
     *         required=true,
     *         description="Midtrans notification payload"
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Callback processed successfully"
     *     )
     * )
     */
    public function callback(Request $request)
    {
        try {
            if (!config('midtrans.use')) {
                return $this->errorResponse('Midtrans is not enabled', 404);
            }

            // Verifikasi signature
            $notification = new \Midtrans\Notification();
            
            // Tambahkan validasi signature
            $signatureKey = $notification->signature_key;
            $orderId = $notification->order_id;
            $statusCode = $notification->status_code;
            $grossAmount = $notification->gross_amount;
            $serverKey = config('midtrans.server_key');
            
            $validSignatureKey = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);
            
            if ($signatureKey !== $validSignatureKey) {
                return $this->errorResponse('Invalid signature', 400);
            }

            $status = $this->determineTransactionStatus(
                $notification->transaction_status,
                $notification->payment_type,
                $notification->fraud_status
            );

            $result = $this->TransactionRepository->updateTransactionStatus($orderId, $status);
            
            if (!$result['success']) {
                throw new \Exception($result['message']);
            }

            return $this->successResponse(null, 'Callback processed successfully', 200);
        } catch (\Exception $e) {
            Log::error('Callback error: ' . $e->getMessage());
            return $this->errorResponse('Failed to process callback', 500);
        }
    }

    private function prepareMidtransParameters(Request $request)
    {
        return [
            'transaction_details' => [
                'order_id' => $request->order_id,
                'gross_amount' => (int) $request->amount, // Pastikan integer
            ],
            'customer_details' => [
                'first_name' => config('app.token_name'),
                'email' => config('app.email'),
            ],
            'expiry' => [
                'start_time' => $request->timestamp,
                'duration' => 24,
                'unit' => 'hours',
            ],
        ];
    }

    private function determineTransactionStatus($transaction, $type, $fraud)
    {
        if ($transaction == 'capture' && $type == 'credit_card') {
            return $fraud == 'challenge' ? 'challenge' : 'success';
        }
        
        $statusMap = [
            'settlement' => 'success',
            'pending' => 'pending',
            'deny' => 'denied',
            'expire' => 'expired',
            'cancel' => 'cancelled'
        ];
        
        return $statusMap[$transaction] ?? 'unknown';
    }
}
