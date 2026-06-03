<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\WorkerController;

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
    Route::post('/users', [OwnerController::class, 'createUser']);
    Route::get('/users', [OwnerController::class, 'getAllUsers']);
    Route::get('/users/{id}', [OwnerController::class, 'getUser']);
    Route::patch('/users/{id}/deactivate', [OwnerController::class, 'deactivateUser']);
    Route::patch('/users/{id}/reactivate', [OwnerController::class, 'reactivateUser']);
    Route::patch('/users/{id}/reset-password', [OwnerController::class, 'resetUserPassword']);

});

// Manager routes
Route::prefix('manager')->middleware('manager')->group(function () {
    // endpoints coming in later phases
    Route::post('/warehouses', [ManagerController::class, 'createWarehouse']);
    Route::get('/warehouses', [ManagerController::class, 'getAllWarehouses']);
    Route::get('/warehouses/{id}', [ManagerController::class, 'getWarehouse']);
    Route::patch('/warehouses/{id}', [ManagerController::class, 'updateWarehouse']);

    // Products
    Route::post('/products', [ManagerController::class, 'createProduct']);
    Route::get('/products', [ManagerController::class, 'getAllProducts']);
    Route::get('/products/{id}', [ManagerController::class, 'getProduct']);
    Route::patch('/products/{id}', [ManagerController::class, 'updateProduct']);

    // Orders
    Route::post('/orders', [ManagerController::class, 'createOrder']);
    Route::get('/orders', [ManagerController::class, 'getAllOrders']);
    Route::get('/orders/{id}', [ManagerController::class, 'getOrder']);
    Route::patch('/orders/{id}/assign', [ManagerController::class, 'assignOrder']);

    // Purchase Orders
    Route::post('/purchase-orders', [ManagerController::class, 'createPurchaseOrder']);
    Route::get('/purchase-orders', [ManagerController::class, 'getAllPurchaseOrders']);
    Route::get('/purchase-orders/{id}', [ManagerController::class, 'getPurchaseOrder']);
    Route::patch('/purchase-orders/{id}/status', [ManagerController::class, 'updatePurchaseOrderStatus']);

    // Worker Flags
    Route::post('/flags', [ManagerController::class, 'flagWorker']);
    Route::get('/flags', [ManagerController::class, 'getAllFlags']);
});


// Worker routes
Route::prefix('worker')->middleware('worker')->group(function () {
    // endpoints coming in later phases
    Route::get('/orders', [WorkerController::class, 'getMyOrders']);
    Route::get('/orders/{id}', [WorkerController::class, 'getMyOrder']);
    Route::patch('/orders/{id}/deliver', [WorkerController::class, 'markDelivered']);
    Route::patch('/orders/{id}/flag', [WorkerController::class, 'flagOrder']);
});


// Shared routes - all authenticated roles
Route::prefix('shared')->group(function () {
    // endpoints coming in later phases
});