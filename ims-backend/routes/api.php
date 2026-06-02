<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

// Authentication routes - accessible to all, no middleware
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Owner routes
Route::prefix('owner')->middleware('owner')->group(function () {
    // endpoints coming in later phases
});

// Manager routes
Route::prefix('manager')->middleware('manager')->group(function () {
    // endpoints coming in later phases
});

// Worker routes
Route::prefix('worker')->middleware('worker')->group(function () {
    // endpoints coming in later phases
});

// Shared routes - all authenticated roles
Route::prefix('shared')->group(function () {
    // endpoints coming in later phases
});