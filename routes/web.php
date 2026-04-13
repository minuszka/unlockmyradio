<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RadioCodeController;

Route::get('/', [RadioCodeController::class, 'index'])->name('home');
Route::post('/search', [RadioCodeController::class, 'search'])->name('search');
Route::post('/search/select', [RadioCodeController::class, 'selectModel'])->name('search.select');
Route::post('/checkout', [RadioCodeController::class, 'checkout'])->name('checkout');
Route::get('/payment/success', [RadioCodeController::class, 'success'])->name('payment.success');

