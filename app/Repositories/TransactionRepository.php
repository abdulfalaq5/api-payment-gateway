<?php

namespace App\Repositories;

use App\Models\DepositModel;
use App\Models\TransactionModel;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionRepository
{
    protected $deposit;
    protected $transaction;

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
} 