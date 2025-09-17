<?php

declare(strict_types=1);

use App\Http\Controllers\Api\UsageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Usage API endpoints (protected by auth:sanctum middleware in controller)
Route::prefix('usage')->name('api.usage.')->group(function () {
    Route::get('current', [UsageController::class, 'current'])->name('current');
    Route::get('history', [UsageController::class, 'history'])->name('history');
    Route::get('trends', [UsageController::class, 'trends'])->name('trends');
    Route::get('export', [UsageController::class, 'export'])->name('export');
});

// Admin Usage API endpoints (protected by auth:sanctum and admin middleware in controller)
Route::prefix('admin/usage')->name('api.admin.usage.')->group(function () {
    Route::get('overview', [\App\Http\Controllers\Api\AdminUsageController::class, 'overview'])->name('overview');
    Route::get('export', [\App\Http\Controllers\Api\AdminUsageController::class, 'export'])->name('export');
});

// Pricing API endpoints (public access with caching)
Route::prefix('pricing')->name('api.pricing.')->group(function () {
    Route::get('plans', [\App\Http\Controllers\Api\PricingController::class, 'index'])->name('plans');
    Route::get('plans/{slug}', [\App\Http\Controllers\Api\PricingController::class, 'show'])->name('plan');
    Route::get('comparison', [\App\Http\Controllers\Api\PricingController::class, 'comparison'])->name('comparison');
    Route::get('frequency/{frequency}', [\App\Http\Controllers\Api\PricingController::class, 'frequency'])->name('frequency');
    Route::get('features', [\App\Http\Controllers\Api\PricingController::class, 'features'])->name('features');
    Route::get('stats', [\App\Http\Controllers\Api\PricingController::class, 'stats'])->name('stats');
    Route::get('search', [\App\Http\Controllers\Api\PricingController::class, 'search'])->name('search');
});
