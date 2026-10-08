<?php

use App\Http\Controllers\Api\V1\ApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [ApiController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'guard.app', 'throttle:120,1'])->group(function () {
        Route::get('/me', [ApiController::class, 'me']);
        Route::post('/auth/logout', [ApiController::class, 'logout']);
        Route::get('/lookups', [ApiController::class, 'lookups']);
        Route::get('/dashboard', [ApiController::class, 'dashboard']);
        Route::post('/calculator', [ApiController::class, 'calculator']);

        Route::get('/customers', [ApiController::class, 'customers']);
        Route::post('/customers', [ApiController::class, 'storeCustomer']);
        Route::get('/customers/{customer}', [ApiController::class, 'showCustomer']);
        Route::put('/customers/{customer}', [ApiController::class, 'updateCustomer']);
        Route::get('/customers/{customer}/deals', [ApiController::class, 'deals']);
        Route::post('/customers/{customer}/deals', [ApiController::class, 'storeDeal']);

        Route::get('/followups', [ApiController::class, 'followups']);
        Route::post('/followups', [ApiController::class, 'storeFollowup']);
        Route::post('/followups/{followup}/complete', [ApiController::class, 'completeFollowup']);

        Route::middleware('role:admin')->group(function () {
            Route::get('/users', [ApiController::class, 'users']);
            Route::get('/reports/{key}', [ApiController::class, 'report']);
        });
    });
});
