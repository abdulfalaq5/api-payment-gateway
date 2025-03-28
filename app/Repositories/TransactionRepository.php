<?php

namespace App\Repositories;

use App\Models\DepositModel;
use App\Models\TransactionModel;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TransactionRepository
{
    protected $deposit;
    protected $transaction;

    private const VALID_TYPES = ['deposit', 'withdrawal'];
    private const VALID_FILTERS = ['day', 'month', 'year'];

    /**
     * Columns to select from transactions table
     */
    private const TRANSACTION_COLUMNS = [
        'type_transaction',
        'amount',
        'transaction_date',
        'status_transaction_id',
        'description',
        'created_at'
    ];

    public function __construct(DepositModel $deposit, TransactionModel $transaction)
    {
        $this->deposit = $deposit;
        $this->transaction = $transaction;
    }

    const STATUS_SUCCESS = 2;
    const STATUS_PENDING = 1;
    const TYPE_DEPOSIT = 1;
    const TYPE_WITHDRAW = 2;

    /**
     * Generate unique order ID
     * Format: INV/YYYYMMDD/XXXX
     * 
     * @return string
     */
    public function generateOrderId()
    {
        try {
            $prefix = 'INV';
            $date = now()->format('Ymdhis');
            $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $orderId = "{$prefix}-{$date}-{$random}";

            // Check if order ID already exists, if yes, generate new one
            while ($this->isOrderIdExists($orderId)) {
                $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $orderId = "{$prefix}-{$date}-{$random}";
            }

            return $orderId;
        } catch (\Exception $e) {
            Log::error('Error in generateOrderId: ' . $e->getMessage());
            throw new \Exception('Failed to generate order ID');
        }
    }

    /**
     * Check if order ID already exists
     * 
     * @param string $orderId
     * @return bool
     */
    private function isOrderIdExists($orderId)
    {
        return TransactionModel::where('order_id', $orderId)->exists();
    }

    public function addDeposit($request, $snapToken = null)
    {
        try {
            // Simpan transaksi ke database
            $model_transaction = new TransactionModel();
            $model_transaction->order_id = $request->order_id;
            $model_transaction->amount = $request->amount;
            $model_transaction->description = 'Deposit transaction';
            $model_transaction->transaction_date = $request->timestamp;
            $model_transaction->type_transaction = self::TYPE_DEPOSIT;
            if ($snapToken && config('midtrans.use')) {
                $model_transaction->snap_token = $snapToken;
                $model_transaction->payment_status = 'pending';
                $model_transaction->status_transaction_id = self::STATUS_PENDING;
            } else {
                $model_transaction->snap_token = null;
                $model_transaction->payment_status = 'success';
                $model_transaction->status_transaction_id = self::STATUS_SUCCESS;
                $deposit = $this->deposit->first() ?? new DepositModel();
                $oldAmount = $deposit->amount ?? 0;
                $deposit->amount = $oldAmount + $request->amount;
                $deposit->save();
            }
            $model_transaction->save();

            return [
                'success' => true,
                'data' => [
                    'order_id' => $model_transaction->order_id,
                    'amount' => format_decimal($model_transaction->amount),
                    'status' => $model_transaction->payment_status
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error in TransactionRepository@addDeposit: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create transaction'
            ];
        }
    }

    public function getAmount()
    {
        $deposit = $this->deposit->first();
        return [
            'amount' => $deposit ? format_decimal($deposit->amount) : '0.00',
            'currency' => 'IDR'
        ];
    }

    public function withdrawalAmount($request)
    {
        DB::beginTransaction();
        try {
            // Validate if balance is sufficient
            validate_balance($this->getAmount()['amount'], $request->amount);
            // Get or create deposit record
            $deposit = $this->deposit->first() ?? new DepositModel();
            $oldAmount = format_decimal($deposit->amount) ?? '0.00';
            $deposit->amount = format_decimal($oldAmount - $request->amount);
            $deposit->save();

            // Create transaction record
            $transaction = $this->transaction->create([
                'amount' => $request->amount,
                'type_transaction' => self::TYPE_WITHDRAW,
                'status_transaction_id' => self::STATUS_SUCCESS,
                'order_id' => 'Withdrawal-' . date('YmdHis'),
                'description' => 'Withdrawal transaction',
                'transaction_date' => date('Y-m-d H:i:s')
            ]);

            DB::commit();
            return [
                'success' => true,
                'data' => [
                    'amount' => $request->amount,
                    'new_amount' => $deposit->amount,
                    'status' => self::STATUS_SUCCESS
                ]
            ];
        } catch (\Exception $e) {
            DB::rollback();
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function updateTransactionStatus($orderId, $status)
    {
        try {
            $transaction = TransactionModel::where('order_id', $orderId)->firstOrFail();
            $transaction->payment_status = $status;
            $transaction->status_transaction_id = $status === 'success' ? self::STATUS_SUCCESS : self::STATUS_PENDING;
            // Jika status pembayaran sukses, update saldo user
            if ($status === 'success') {
                $deposit = $this->deposit->first() ?? new DepositModel();
                $oldAmount = $deposit->amount ?? 0;
                $deposit->amount = $oldAmount + $transaction->amount;
                $deposit->save();
            }
            
            $transaction->save();
            
            return [
                'success' => true,
                'message' => 'Transaction status updated successfully'
            ];
        } catch (\Exception $e) {
            Log::error('Error in TransactionRepository@updateTransactionStatus: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to update transaction status'
            ];
        }
    }

    /**
     * Get transaction history with optional type and time filter
     *
     * @param string|null $type Transaction type (deposit/withdrawal)
     * @param string|null $filter Time filter (day/month/year)
     * @param int|null $perPage Number of items per page
     * @param int|null $page Current page number
     * @return array{success: bool, data?: array, pagination?: array, message?: string}
     */
    public function getTransactionHistory(
        ?string $type = null,
        ?string $filter = null,
        ?int $perPage = 15,
        ?int $page = 1
    ): array {
        try {
            // Validate type
            if ($type && !in_array($type, self::VALID_TYPES)) {
                return [
                    'success' => false,
                    'message' => 'Invalid transaction type'
                ];
            }

            // Validate filter
            if ($filter && !in_array($filter, self::VALID_FILTERS)) {
                return [
                    'success' => false,
                    'message' => 'Invalid filter type'
                ];
            }

            $query = TransactionModel::select(self::TRANSACTION_COLUMNS)
                ->with(['statusTransaction' => function($query) {
                    $query->select('id', 'name'); // Select hanya kolom yang diperlukan dari relasi
                }]);

            // Filter berdasarkan tipe transaksi
            if ($type) {
                $type = $type == 'deposit' ? self::TYPE_DEPOSIT : self::TYPE_WITHDRAW;
                $query->where('type_transaction', $type);
            }

            // Filter berdasarkan waktu
            if ($filter) {
                $this->applyTimeFilter($query, $filter);
            }

            $transactions = $query->orderBy('transaction_date', 'desc')
                                ->paginate($perPage, ['*'], 'page', $page);

            return [
                'success' => true,
                'data' => $transactions->through(function ($transaction) {
                    return [
                        'transaction_date' => $transaction->transaction_date,
                        'type' => $transaction->type_transaction == self::TYPE_DEPOSIT ? 'Deposit' : 'Withdrawal',
                        'amount' => $transaction->amount,
                        'status' => $transaction->statusTransaction->name,
                        'description' => $transaction->description
                    ];
                }),
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                    'last_page' => $transactions->lastPage()
                ]
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to fetch transaction history: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Apply time filter to the query
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $filter
     * @return void
     */
    private function applyTimeFilter($query, string $filter): void
    {
        switch ($filter) {
            case 'day':
                $query->whereDate('transaction_date', Carbon::today());
                break;
            case 'month':
                $query->whereMonth('transaction_date', Carbon::now()->month)
                      ->whereYear('transaction_date', Carbon::now()->year);
                break;
            case 'year':
                $query->whereYear('transaction_date', Carbon::now()->year);
                break;
        }
    }
} 