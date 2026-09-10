<?php

use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\ItemCategoryController;
use App\Http\Controllers\Api\MarketPaymentSettingController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProblemReportController;
use App\Http\Controllers\Api\SellerManagementController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\StallBookingController;
use App\Http\Controllers\Api\StallController;
use App\Http\Controllers\Api\MarketMapController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\FollowShopController;
use App\Http\Controllers\Api\ShopReviewController;
use App\Http\Controllers\Api\ReviewReportController;
use App\Http\Controllers\Api\PasswordResetController;
use Illuminate\Support\Facades\Route;

$serveImage = function ($filename) {
    $cleanFilename = basename($filename);
    $path = storage_path('images/' . $cleanFilename);
    if (file_exists($path)) {
        return response()->file($path);
    }
    $pathPublic = storage_path('app/public/' . $cleanFilename);
    if (file_exists($pathPublic)) {
        return response()->file($pathPublic);
    }
    $pathApp = storage_path('app/' . $cleanFilename);
    if (file_exists($pathApp)) {
        return response()->file($pathApp);
    }
    abort(404);
};

Route::get('images/{filename}', $serveImage)->where('filename', '.*');

Route::prefix('admin')->group(function () {
    Route::get('sellers', [SellerManagementController::class, 'index']);
    Route::get('sellers/pending', [SellerManagementController::class, 'pending']);
    Route::put('sellers/{id}/approve', [SellerManagementController::class, 'approve']);
    Route::put('sellers/{id}/reject', [SellerManagementController::class, 'reject']);
    Route::get('market-payment-settings', [MarketPaymentSettingController::class, 'index']);
    Route::post('market-payment-settings', [MarketPaymentSettingController::class, 'store']);
    Route::match(['put', 'post'], 'market-payment-settings/{id}', [MarketPaymentSettingController::class, 'update']);
    Route::delete('market-payment-settings/{id}', [MarketPaymentSettingController::class, 'destroy']);
    Route::patch('market-payment-settings/{id}/toggle-active', [MarketPaymentSettingController::class, 'toggleActive']);

    Route::get('announcements', [AnnouncementController::class, 'index']);
    Route::post('announcements', [AnnouncementController::class, 'store']);
    Route::match(['put', 'post'], 'announcements/{id}', [AnnouncementController::class, 'update']);
    Route::delete('announcements/{id}', [AnnouncementController::class, 'destroy']);
    Route::patch('announcements/{id}/toggle-status', [AnnouncementController::class, 'toggleStatus']);
});

Route::prefix('v1')->group(function () use ($serveImage) {
    Route::get('images/{filename}', $serveImage)->where('filename', '.*');
    Route::post('forgot-password', [PasswordResetController::class, 'sendResetCode']);
    Route::post('verify-reset-code', [PasswordResetController::class, 'verifyResetCode']);
    Route::post('reset-password', [PasswordResetController::class, 'resetPassword']);
    Route::get('admin/market-payment-settings', [MarketPaymentSettingController::class, 'index']);
    Route::post('admin/market-payment-settings', [MarketPaymentSettingController::class, 'store']);
    Route::match(['put', 'post'], 'admin/market-payment-settings/{id}', [MarketPaymentSettingController::class, 'update']);
    Route::delete('admin/market-payment-settings/{id}', [MarketPaymentSettingController::class, 'destroy']);
    Route::patch('admin/market-payment-settings/{id}/toggle-active', [MarketPaymentSettingController::class, 'toggleActive']);
    Route::get('followed-shops', [FollowShopController::class, 'index']);
    Route::post('followed-shops/toggle', [FollowShopController::class, 'toggle']);
    Route::get('followed-shops/check', [FollowShopController::class, 'check']);
    Route::get('dashboard/overview', [DashboardController::class, 'overview']);
    Route::get('dashboard/badge-counts', [DashboardController::class, 'badgeCounts']);
    Route::get('categories', [DashboardController::class, 'getCategories']);
    Route::post('categories', [DashboardController::class, 'storeCategory']);
    Route::put('categories/{id}', [DashboardController::class, 'updateCategory']);
    Route::delete('categories/{id}', [DashboardController::class, 'destroyCategory']);
    Route::get('item-categories', [ItemCategoryController::class, 'index']);
    Route::post('item-categories', [ItemCategoryController::class, 'store']);
    Route::put('item-categories/{id}', [ItemCategoryController::class, 'update']);
    Route::delete('item-categories/{id}', [ItemCategoryController::class, 'destroy']);
    Route::post('item-categories/{id}/assign-items', [ItemCategoryController::class, 'assignItems']);
    Route::post('item-categories/{id}/remove-items', [ItemCategoryController::class, 'removeItems']);
    Route::get('user-interests', [DashboardController::class, 'getUserInterests']);
    Route::post('user-interests', [DashboardController::class, 'storeUserInterest']);
    Route::put('user-interests/{id}', [DashboardController::class, 'updateUserInterest']);
    Route::delete('user-interests/{id}', [DashboardController::class, 'destroyUserInterest']);
    Route::get('maps/{id}', [MarketMapController::class, 'show']);
    Route::put('maps/{id}/items', [MarketMapController::class, 'saveItems']);
    Route::apiResource('users', UserController::class);
    Route::apiResource('shops', ShopController::class);
    Route::apiResource('items', ItemController::class);
    Route::match(['put', 'post'], 'stalls/{id}', [StallController::class, 'update']);
    Route::apiResource('stalls', StallController::class);
    Route::apiResource('stall-bookings', StallBookingController::class);
    Route::apiResource('payments', PaymentController::class);
    Route::get('shop-reviews', [ShopReviewController::class, 'index']);
    Route::post('shop-reviews', [ShopReviewController::class, 'store']);
    Route::post('shop-reviews/reaction', [ShopReviewController::class, 'react']);
    Route::post('review-reports', [ReviewReportController::class, 'store']);
    Route::post('problem-reports', [ProblemReportController::class, 'store']);
    Route::get('problem-reports', [ProblemReportController::class, 'index']);

    Route::get('bookings', [BookingController::class, 'index']);
    Route::get('bookings/{booking_id}', [BookingController::class, 'show']);
    Route::post('bookings', [BookingController::class, 'store']);
    Route::put('bookings/{booking_id}', [BookingController::class, 'update']);
    Route::delete('bookings/{booking_id}', [BookingController::class, 'destroy']);
    Route::put('bookings/{booking_id}/approve', [BookingController::class, 'approve']);
    Route::put('bookings/{booking_id}/pending', [BookingController::class, 'pending']);
    Route::put('bookings/{booking_id}/hold', [BookingController::class, 'hold']);
    Route::put('bookings/{booking_id}/reject', [BookingController::class, 'reject']);
    Route::put('bookings/{booking_id}/request-refund', [BookingController::class, 'requestRefund']);
    Route::match(['put', 'post'], 'bookings/{booking_id}/approve-refund', [BookingController::class, 'approveRefund']);

    Route::get('admin/sellers', [SellerManagementController::class, 'index']);
    Route::get('admin/sellers/pending', [SellerManagementController::class, 'pending']);
    Route::put('admin/sellers/{id}/approve', [SellerManagementController::class, 'approve']);
    Route::put('admin/sellers/{id}/reject', [SellerManagementController::class, 'reject']);
    Route::get('admin/problem-reports', [ProblemReportController::class, 'index']);
    Route::put('admin/problem-reports/{id}', [ProblemReportController::class, 'update']);
    Route::get('admin/review-reports', [ReviewReportController::class, 'index']);
    Route::put('admin/review-reports/{id}', [ReviewReportController::class, 'update']);
    Route::post('admin/review-reports/{id}/toggle-review', [ReviewReportController::class, 'toggleReview']);
    Route::delete('admin/review-reports/{id}', [ReviewReportController::class, 'destroy']);

    Route::get('admin/announcements', [AnnouncementController::class, 'index']);
    Route::post('admin/announcements', [AnnouncementController::class, 'store']);
    Route::match(['put', 'post'], 'admin/announcements/{id}', [AnnouncementController::class, 'update']);
    Route::delete('admin/announcements/{id}', [AnnouncementController::class, 'destroy']);
    Route::patch('admin/announcements/{id}/toggle-status', [AnnouncementController::class, 'toggleStatus']);
});
