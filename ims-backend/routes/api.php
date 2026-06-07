<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OwnerController;
use App\Http\Controllers\ManagerController;
use App\Http\Controllers\WorkerController;
use App\Http\Controllers\SharedController;

// Authentication routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:3,15');
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Owner routes
Route::prefix('owner')->middleware('owner')->group(function () {
    // Account Management
    Route::post('/users/create', [OwnerController::class, 'createUser']);
    Route::get('/users', [OwnerController::class, 'getAllUsers']);
    Route::get('/users/{id}', [OwnerController::class, 'getUser']);
    Route::put('/users/{id}', [OwnerController::class, 'updateUser']);
    Route::put('/users/{id}/deactivate', [OwnerController::class, 'deactivateUser']);
    Route::put('/users/{id}/reactivate', [OwnerController::class, 'reactivateUser']);
    Route::patch('/users/{id}/reset-password', [OwnerController::class, 'resetUserPassword']);
    Route::delete('/users/{id}', [OwnerController::class, 'deleteUser']);

    // Worker Flags
    Route::get('/flags', [OwnerController::class, 'getAllFlags']);
    Route::put('/flags/{id}/dismiss', [OwnerController::class, 'dismissFlag']);
    Route::put('/flags/{id}/warn', [OwnerController::class, 'warnWorker']);

    // Stock and Order Oversight
    Route::get('/stock', [OwnerController::class, 'getAllStock']);
    Route::get('/orders', [OwnerController::class, 'getAllOrders']);

    // Reports
    Route::get('/reports/financial', [OwnerController::class, 'financialReport']);
    Route::get('/reports/audit', [OwnerController::class, 'auditReport']);

    // Settings
    Route::get('/settings', [OwnerController::class, 'getSettings']);
    Route::put('/settings', [OwnerController::class, 'updateSettings']);
});

// Manager routes
Route::prefix('manager')->middleware('manager')->group(function () {
    // User Management
    Route::post('/users/create', [ManagerController::class, 'createWorker']);
    Route::get('/users', [ManagerController::class, 'getAllWorkers']);
    Route::get('/workers/status', [ManagerController::class, 'getWorkersStatus']);

    // Warehouses
    Route::post('/warehouses', [ManagerController::class, 'createWarehouse']);
    Route::get('/warehouses', [ManagerController::class, 'getAllWarehouses']);
    Route::get('/warehouses/{id}', [ManagerController::class, 'getWarehouse']);
    Route::patch('/warehouses/{id}', [ManagerController::class, 'updateWarehouse']);

    // Products
    Route::post('/products', [ManagerController::class, 'createProduct']);
    Route::get('/products', [ManagerController::class, 'getAllProducts']);
    Route::get('/products/{id}', [ManagerController::class, 'getProduct']);
    Route::patch('/products/{id}', [ManagerController::class, 'updateProduct']);

    // Stock
    Route::get('/stock', [ManagerController::class, 'getAllStock']);
    Route::patch('/stock/{id}', [ManagerController::class, 'updateStock']);
    Route::get('/stock/low', [ManagerController::class, 'getLowStock']);

    // Orders
    Route::post('/orders', [ManagerController::class, 'createOrder']);
    Route::get('/orders', [ManagerController::class, 'getAllOrders']);
    Route::get('/orders/{id}', [ManagerController::class, 'getOrder']);
    Route::patch('/orders/{id}/assign', [ManagerController::class, 'assignOrder']);
    Route::patch('/orders/{id}/flag', [ManagerController::class, 'flagOrder']);
    Route::patch('/orders/{id}/resolve', [ManagerController::class, 'resolveOrder']);

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
    Route::get('/orders', [WorkerController::class, 'getAllOrders']);
    Route::get('/orders/assigned', [WorkerController::class, 'getAssignedOrders']);
    Route::get('/orders/{id}', [WorkerController::class, 'getMyOrder']);
    Route::patch('/orders/{id}/deliver', [WorkerController::class, 'markDelivered']);
    Route::patch('/orders/{id}/flag', [WorkerController::class, 'flagOrder']);
    Route::get('/stock', [WorkerController::class, 'getAllStock']);
});

// Shared routes - all authenticated roles
Route::prefix('shared')->middleware('auth:api')->group(function () {
    Route::get('/warehouses', [SharedController::class, 'getAllWarehouses']);
    Route::get('/warehouses/{id}', [SharedController::class, 'getWarehouse']);
    Route::get('/products', [SharedController::class, 'getAllProducts']);
    Route::get('/products/{id}', [SharedController::class, 'getProduct']);
});