<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TransactionController;

// Public login route
Route::post('/login', [AuthController::class, 'login'])->name('login');

// All routes below require Sanctum token
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/user', [AuthController::class, 'me']);

    // Logout
    Route::post('/logout', [AuthController::class, 'logout']);

      // Admin only
    Route::post('/transactions', [TransactionController::class, 'store']);
    Route::post('/transactions/{id}/payments', [TransactionController::class, 'addPayment']);

    // Admin + Customer
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);

    Route::get('/reports/monthly', [TransactionController::class, 'monthlyReport']);

});