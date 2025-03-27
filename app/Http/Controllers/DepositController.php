<?php

namespace App\Http\Controllers;

use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use App\Repositories\TransactionRepository;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

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
     *     operationId="handleCallback",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"transaction_status", "order_id", "status_code", "gross_amount", "signature_key"},
     *             @OA\Property(property="transaction_status", type="string", example="settlement"),
     *             @OA\Property(property="order_id", type="string", example="INV/20250327/1234"),
     *             @OA\Property(property="status_code", type="string", example="200"),
     *             @OA\Property(property="gross_amount", type="string", example="100000.00"),
     *             @OA\Property(property="signature_key", type="string", example="fe5f8ff281c4e3e65c1c4a7e2f48052579c3f0d9b64e44c87e2d9f2741149407622d13d1f8f27519500930dff9344367db064b01dacc31c9d0787cd0219c3825"),
     *             @OA\Property(property="payment_type", type="string", example="bank_transfer"),
     *             @OA\Property(property="transaction_time", type="string", example="2025-03-27 08:19:32"),
     *             @OA\Property(property="transaction_id", type="string", example="9aed5972-5b6a-401d-950c-05f39184e8d3"),
     *             @OA\Property(property="status_message", type="string", example="Success"),
     *             @OA\Property(property="merchant_id", type="string", example="G12345678"),
     *             @OA\Property(
     *                 property="fraud_status",
     *                 type="string",
     *                 example="accept",
     *                 enum={"accept", "deny", "challenge"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification processed successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 example="success"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Payment notification processed"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Invalid signature key",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 example="error"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Invalid signature key"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 example="error"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Failed to process payment notification"
     *             )
     *         )
     *     )
     * )
     */
    public function callback(Request $request)
    {
        try {
            if (!config('midtrans.use')) {
                throw new \Exception('Midtrans is not enabled');
            }

            // Ambil data dari request body
            $orderId = $request->order_id;
            $statusCode = $request->status_code;
            $grossAmount = $request->gross_amount;
            $serverKey = config('midtrans.server_key');
            $signatureKey = $request->signature_key;

            // Generate signature key
            $mySignatureKey = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

            // Validasi signature key
            if ($signatureKey !== $mySignatureKey) {
                Log::warning('Invalid signature key', [
                    'order_id' => $orderId,
                    'received_signature' => $signatureKey,
                    'calculated_signature' => $mySignatureKey
                ]);
                return $this->errorResponse('Invalid signature key', 403);
            }

            // Proses notifikasi
            $notification = new \Midtrans\Notification();
            
            $transactionStatus = $notification->transaction_status;
            $paymentType = $notification->payment_type;
            $fraudStatus = $notification->fraud_status;

            // Map status transaksi
            $status = $this->mapTransactionStatus(
                $transactionStatus,
                $paymentType,
                $fraudStatus
            );

            // Log notifikasi
            Log::info('Midtrans Notification', [
                'order_id' => $orderId,
                'status' => $status,
                'payment_type' => $paymentType,
                'transaction_status' => $transactionStatus,
                'signature_verified' => true
            ]);

            // Update status transaksi
            $result = $this->TransactionRepository->updateTransactionStatus($orderId, $status);
            
            if (!$result['success']) {
                throw new \Exception($result['message']);
            }

            return $this->successResponse(
                null,
                'Payment notification processed',
                200
            );

        } catch (\Exception $e) {
            Log::error('Callback error: ' . $e->getMessage(), [
                'payload' => $request->all()
            ]);
            
            return $this->errorResponse(
                'Failed to process payment notification: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Map transaction status from Midtrans to internal status
     * 
     * @param string $transactionStatus
     * @param string $paymentType
     * @param string|null $fraudStatus
     * @return string
     */
    private function mapTransactionStatus($transactionStatus, $paymentType, $fraudStatus = null)
    {
        $statusMap = [
            'capture' => function() use ($paymentType, $fraudStatus) {
                if ($paymentType == 'credit_card') {
                    return $fraudStatus == 'challenge' ? 'challenge' : 'success';
                }
                return 'success';
            },
            'settlement' => 'success',
            'pending' => 'pending',
            'deny' => 'denied',
            'expire' => 'expired',
            'cancel' => 'cancelled',
            'refund' => 'refunded',
            'partial_refund' => 'partially_refunded',
            'authorize' => 'authorized'
        ];

        if (isset($statusMap[$transactionStatus])) {
            return is_callable($statusMap[$transactionStatus]) 
                ? $statusMap[$transactionStatus]() 
                : $statusMap[$transactionStatus];
        }

        return 'unknown';
    }

    private function prepareMidtransParameters(Request $request)
    {
        $timestamp = Carbon::parse($request->timestamp)
                ->timezone('Asia/Jakarta')
                ->format('Y-m-d H:i:s O');  // akan menghasilkan format: 2020-06-09 15:07:00 +0700
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
                'start_time' => $timestamp,
                'duration' => 24,
                'unit' => 'hours',
            ],
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/deposit/generate-order-id",
     *     tags={"Deposit"},
     *     summary="Generate order ID",
     *     description="Generate a unique order ID",
     *     operationId="generateOrderId",
     *     @OA\Response(
     *         response=200,
     *         description="Order ID generated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 example="success"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Order ID generated successfully"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="order_id",
     *                     type="string",
     *                     example="INV/20250327/1234"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 example="error"
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Failed to generate order ID"
     *             )
     *         )
     *     )
     * )
     */
    public function generateOrderId()
    {
        try {
            $orderId = $this->TransactionRepository->generateOrderId();
            
            return $this->successResponse([
                'order_id' => $orderId
            ], 'Order ID generated successfully', 200);
            
        } catch (\Exception $e) {
            Log::error('Error generating order ID: ' . $e->getMessage());
            return $this->errorResponse('Failed to generate order ID', 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/deposit/transaction-status/{order_id}",
     *     tags={"Deposit"},
     *     summary="Check transaction status",
     *     description="Check transaction status from Midtrans",
     *     operationId="getTransactionStatus",
     *     @OA\Parameter(
     *         name="order_id",
     *         in="path",
     *         required=true,
     *         description="Order ID",
     *         @OA\Schema(
     *             type="string",
     *             example="INV-20250327081932-5894"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transaction status retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Transaction status retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="transaction_status", type="string", example="settlement"),
     *                 @OA\Property(property="payment_type", type="string", example="bank_transfer"),
     *                 @OA\Property(property="order_id", type="string", example="INV-20250327081932-5894"),
     *                 @OA\Property(property="gross_amount", type="string", example="100000.00"),
     *                 @OA\Property(property="transaction_time", type="string", example="2025-03-27 08:19:32")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Transaction not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     */
    public function getTransactionStatus(Request $request, $orderId)
    {
        try {
            if (!config('midtrans.use')) {
                throw new \Exception('Midtrans is not enabled');
            }

            // Set server key Midtrans
            \Midtrans\Config::$serverKey = config('midtrans.server_key');
            \Midtrans\Config::$isProduction = config('midtrans.is_production');

            // Get status dari Midtrans
            $status = \Midtrans\Transaction::status($orderId);

            // Log response
            Log::info('Midtrans Status Check', [
                'order_id' => $orderId,
                'response' => $status
            ]);

            return $this->successResponse($status, 'Transaction status retrieved successfully', 200);

        } catch (\Midtrans\ApiException $e) {
            Log::error('Midtrans API Error: ' . $e->getMessage(), [
                'order_id' => $orderId
            ]);
            
            if ($e->getHttpStatusCode() == 404) {
                return $this->errorResponse('Transaction not found', 404);
            }
            
            return $this->errorResponse('Failed to check transaction status', 500);
        } catch (\Exception $e) {
            Log::error('Error checking transaction status: ' . $e->getMessage(), [
                'order_id' => $orderId
            ]);
            return $this->errorResponse('Failed to check transaction status', 500);
        }
    }
}
