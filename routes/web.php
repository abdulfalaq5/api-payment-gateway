<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// API Routes Group
Route::middleware(['web'])->prefix('api')->group(function () {
    // Public routes (no auth required)
    Route::get('/login', [AuthController::class, 'login']);

    // Protected routes
    /**
     * php artisan route:list
     * php artisan route:list --path=api
     * untuk melihat route yang ada
     */
    Route::middleware('name.token')->group(function () {
        Route::apiResource('deposit', DepositController::class);
        Route::apiResource('withdrawal', WithdrawalController::class);
    });
});

