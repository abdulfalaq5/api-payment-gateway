<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TransactionModel extends Model
{
    use SoftDeletes;
    protected $fillable = ['order_id', 'amount', 'status_transaction_id', 'description', 'type_transaction', 'transaction_date'];
    protected $table = 'transactions';
    
    public function statusTransaction()
    {
        return $this->belongsTo(StatusTransactionModel::class);
    }
}
