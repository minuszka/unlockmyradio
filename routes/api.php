<?php

use App\Http\Controllers\Api\RadioCodeApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::post('/search', [RadioCodeApiController::class, 'search']);
    Route::post('/checkout', [RadioCodeApiController::class, 'checkout']);
    Route::get('/payment/success', [RadioCodeApiController::class, 'paymentSuccess']);
});

