<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StatusTransactionModel extends Model
{
    use SoftDeletes;
    protected $fillable = ['name'];
    protected $table = 'status_transactions';
}
