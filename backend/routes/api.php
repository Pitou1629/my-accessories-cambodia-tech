<?php

use App\Http\Controllers\ApiController;
use App\Http\Middleware\AuthenticateToken;
use Illuminate\Support\Facades\Route;

Route::get('/health', [ApiController::class, 'health']);
Route::get('/products', [ApiController::class, 'products']);
Route::post('/auth/register', [ApiController::class, 'register']);
Route::post('/auth/login', [ApiController::class, 'login']);
Route::get('/feedback', [ApiController::class, 'feedback']);
Route::post('/feedback', [ApiController::class, 'submitFeedback']);

Route::middleware(AuthenticateToken::class)->group(function (): void {
    Route::get('/admin/summary', [ApiController::class, 'adminSummary']);
    Route::post('/admin/products', [ApiController::class, 'createProduct']);
    Route::patch('/admin/products/{id}/stock', [ApiController::class, 'updateStock']);
    Route::get('/chat', [ApiController::class, 'messages']);
    Route::post('/chat', [ApiController::class, 'sendMessage']);
    Route::post('/orders', [ApiController::class, 'createOrder']);
    Route::get('/customer/orders', [ApiController::class, 'customerOrders']);
});
