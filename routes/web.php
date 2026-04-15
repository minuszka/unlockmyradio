<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RadioCodeController;

Route::get('/', [RadioCodeController::class, 'index'])->name('home');
Route::post('/search', [RadioCodeController::class, 'search'])->name('search');
Route::get('/search', [RadioCodeController::class, 'index']);
Route::get('/serial/classify', [RadioCodeController::class, 'classify'])->name('serial.classify');
Route::post('/search/select', [RadioCodeController::class, 'selectModel'])->name('search.select');
Route::get('/search/select', [RadioCodeController::class, 'index']);
Route::post('/checkout', [RadioCodeController::class, 'checkout'])->name('checkout');
Route::get('/checkout', [RadioCodeController::class, 'index']);
Route::get('/payment/success', [RadioCodeController::class, 'success'])->name('payment.success');
