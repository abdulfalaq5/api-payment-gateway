<?php

namespace App\Exceptions;

use Exception;

class InsufficientBalanceException extends Exception
{
    public function __construct($message = "Insufficient balance", $code = 422)
    {
        parent::__construct($message, $code);
    }
} 