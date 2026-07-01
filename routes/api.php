<?php

use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\MarketPaymentSettingController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProblemReportController;
use App\Http\Controllers\Api\SellerManagementController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\StallBookingController;
use App\Http\Controllers\Api\StallController;
use App\Http\Controllers\Api\MarketMapController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::get('images/{filename}', function ($filename) {
    $path = 'F:/Project-MarketPlace/admin/backend/storage/images/' . $filename;
    if (!file_exists($path)) {
        abort(404);
    }
    return response()->file($path);
});

Route::prefix('admin')->group(function () {
    Route::get('sellers', [SellerManagementController::class, 'index']);
    Route::get('sellers/pending', [SellerManagementController::class, 'pending']);
    Route::put('sellers/{id}/approve', [SellerManagementController::class, 'approve']);
    Route::put('sellers/{id}/reject', [SellerManagementController::class, 'reject']);
    Route::get('market-payment-settings', [MarketPaymentSettingController::class, 'index']);
    Route::post('market-payment-settings', [MarketPaymentSettingController::class, 'update']);

    Route::get('announcements', [AnnouncementController::class, 'index']);
    Route::post('announcements', [AnnouncementController::class, 'store']);
    Route::put('announcements/{id}', [AnnouncementController::class, 'update']);
    Route::delete('announcements/{id}', [AnnouncementController::class, 'destroy']);
    Route::patch('announcements/{id}/toggle-status', [AnnouncementController::class, 'toggleStatus']);
});

Route::prefix('v1')->group(function () {
    Route::get('dashboard/overview', [DashboardController::class, 'overview']);
    Route::get('dashboard/badge-counts', [DashboardController::class, 'badgeCounts']);
    Route::get('maps/{id}', [MarketMapController::class, 'show']);
    Route::put('maps/{id}/items', [MarketMapController::class, 'saveItems']);
    Route::apiResource('users', UserController::class);
    Route::apiResource('shops', ShopController::class);
    Route::apiResource('items', ItemController::class);
    Route::apiResource('stalls', StallController::class);
    Route::apiResource('stall-bookings', StallBookingController::class);
    Route::apiResource('payments', PaymentController::class);

    Route::get('bookings', [BookingController::class, 'index']);
    Route::get('bookings/{booking_id}', [BookingController::class, 'show']);
    Route::post('bookings', [BookingController::class, 'store']);
    Route::put('bookings/{booking_id}', [BookingController::class, 'update']);
    Route::delete('bookings/{booking_id}', [BookingController::class, 'destroy']);
    Route::put('bookings/{booking_id}/approve', [BookingController::class, 'approve']);
    Route::put('bookings/{booking_id}/pending', [BookingController::class, 'pending']);
    Route::put('bookings/{booking_id}/hold', [BookingController::class, 'hold']);
    Route::put('bookings/{booking_id}/reject', [BookingController::class, 'reject']);

    Route::get('admin/sellers', [SellerManagementController::class, 'index']);
    Route::get('admin/sellers/pending', [SellerManagementController::class, 'pending']);
    Route::put('admin/sellers/{id}/approve', [SellerManagementController::class, 'approve']);
    Route::put('admin/sellers/{id}/reject', [SellerManagementController::class, 'reject']);
    Route::get('admin/problem-reports', [ProblemReportController::class, 'index']);
    Route::put('admin/problem-reports/{id}', [ProblemReportController::class, 'update']);

    Route::get('admin/announcements', [AnnouncementController::class, 'index']);
    Route::post('admin/announcements', [AnnouncementController::class, 'store']);
    Route::put('admin/announcements/{id}', [AnnouncementController::class, 'update']);
    Route::delete('admin/announcements/{id}', [AnnouncementController::class, 'destroy']);
    Route::patch('admin/announcements/{id}/toggle-status', [AnnouncementController::class, 'toggleStatus']);
});
