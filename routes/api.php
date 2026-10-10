<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminLeadController;
use App\Http\Controllers\EngagementTrackingController;
use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

// Notification routes (public, but throttled)
Route::post('/notify/invoice-request', [NotificationController::class, 'sendInvoiceRequest'])
    ->middleware('throttle:10,1'); // 10 attempts per minute
Route::post('/notify/price-request', [NotificationController::class, 'sendPriceRequest'])
    ->middleware('throttle:10,1'); // 10 attempts per minute

// Engagement tracking: серверный гейт клик-целей вовлечения (cta_click_*).
// Выдача nonce ограничена по IP (30/час) — бот не плодит тысячи сессий;
// engagement — грубый IP-кап (60/мин), тонкие per-session/per-IP лимиты
// дополнительно проверяются внутри контроллера.
Route::post('/track/nonce', [EngagementTrackingController::class, 'issueNonce'])
    ->middleware('throttle:30,60'); // 30 nonce в час с IP
Route::post('/track/engagement', [EngagementTrackingController::class, 'track'])
    ->middleware('throttle:60,1'); // 60 запросов/мин с IP

// Админка: JWT выдаётся только пользователям из ADMIN_EMAILS. Защищённые
// методы доступны серверной части mp-front по Bearer-токену.
Route::post('/admin/login', [AdminAuthController::class, 'login'])
    ->middleware('throttle:5,1');

Route::prefix('admin')->middleware(['admin.jwt', 'throttle:120,1'])->group(function () {
    Route::get('/leads', [AdminLeadController::class, 'index']);
    Route::get('/leads/offline/export', [AdminLeadController::class, 'exportOffline'])
        ->middleware('throttle:20,1');
    Route::post('/leads/offline', [AdminLeadController::class, 'storeOffline'])
        ->middleware('throttle:30,1');
});
