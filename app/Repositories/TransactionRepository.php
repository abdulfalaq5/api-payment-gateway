<?php

namespace App\Repositories;

use App\Models\DepositModel;
use App\Models\TransactionModel;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Helpers\NumberHelper;

class TransactionRepository
{
    protected $deposit;
    protected $transaction;

    public function __construct(DepositModel $deposit, TransactionModel $transaction)
    {
        $this->deposit = $deposit;
        $this->transaction = $transaction;
    }

    const STATUS_SUCCESS = 1;
    const STATUS_PENDING = 2;
    const TYPE_DEPOSIT = 1;
    const TYPE_WITHDRAW = 2;
    public function addDeposit($request)
    {
        DB::beginTransaction();
        try {
            // Get or create deposit record
            $deposit = $this->deposit->first() ?? new DepositModel();
            $oldAmount = $deposit->amount ?? 0;
            $deposit->amount = $oldAmount + $request->amount;
            $deposit->save();

            // Create transaction record
            $transaction = $this->transaction->create([
                'amount' => $request->amount,
                'type_transaction' => self::TYPE_DEPOSIT,
                'status_transaction_id' => self::STATUS_SUCCESS,
                'order_id' => $request->order_id,
                'description' => 'Deposit transaction',
                'transaction_date' => $request->timestamp
            ]);

            DB::commit();
            return [
                'success' => true,
                'data' => [
                    'order_id' => $request->order_id,
                    'amount' => $request->amount,
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
} 