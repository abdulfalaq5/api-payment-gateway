<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DepositModel extends Model
{
    use SoftDeletes;
    protected $fillable = ['amount'];
    protected $table = 'deposit';
    
}
