<?php

use App\Http\Controllers\Api\RadioCodeApiController;
use App\Http\Controllers\Api\ResellerApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/classify-serial', [RadioCodeApiController::class, 'classifySerial']);
    Route::post('/search', [RadioCodeApiController::class, 'search']);
    Route::post('/checkout', [RadioCodeApiController::class, 'checkout']);
    Route::get('/payment/success', [RadioCodeApiController::class, 'paymentSuccess']);

    Route::prefix('reseller')->group(function (): void {
        Route::get('/balance', [ResellerApiController::class, 'balance']);
        Route::post('/decode', [ResellerApiController::class, 'decode']);
    });
});
