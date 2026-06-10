<?php

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

// Notification routes (public, but throttled)
Route::post('/notify/invoice-request', [NotificationController::class, 'sendInvoiceRequest'])
    ->middleware('throttle:10,1'); // 10 attempts per minute
Route::post('/notify/price-request', [NotificationController::class, 'sendPriceRequest'])
    ->middleware('throttle:10,1'); // 10 attempts per minute
