<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LandingPageController;
use App\Livewire\Pages\Dashboard as DashboardPage;
use App\Livewire\Pages\Admin\Index as AdminIndexPage;
use App\Livewire\Admin\Users\Index as AdminUsersIndexPage;

Route::get('/', [LandingPageController::class, 'index'])->name('landing');

// Stripe Webhook - No authentication required
Route::post('/stripe/webhook', [App\Http\Controllers\StripeWebhookController::class, 'handleWebhook'])
    ->name('stripe.webhook');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardPage::class)->name('dashboard');
    Route::get('uploads', \App\Livewire\Uploads\Index::class)->name('uploads.index');
    Route::get('uploads/new', \App\Livewire\Uploads\UploadCsv::class)->name('uploads.create');

    // Usage Dashboard routes
    Route::prefix('usage')->name('usage.')->group(function () {
        Route::get('dashboard', \App\Livewire\Dashboard\UsageDashboard::class)->name('dashboard');
        Route::get('history', \App\Livewire\Dashboard\UsageHistory::class)->name('history');
        Route::get('stats', \App\Livewire\Dashboard\UsageStats::class)->name('stats');
    });

    // Download routes
    Route::get('download/upload/{upload}', [\App\Http\Controllers\DownloadController::class, 'downloadUpload'])
        ->name('download.upload');

    // Billing routes
    Route::get('billing/subscriptions', \App\Livewire\Billing\SubscriptionManager::class)->name('billing.subscriptions');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth', 'verified', 'ensure.admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', \App\Livewire\Admin\Dashboard::class)->name('index');
        Route::get('/users', AdminUsersIndexPage::class)->name('users.index');
        Route::get('/users/enhanced', \App\Livewire\Admin\EnhancedUserManagement::class)->name('users.enhanced');
        Route::get('/usage-analytics', \App\Livewire\Admin\UsageAnalytics::class)->name('usage.analytics');
        Route::get('/stripe-config', \App\Livewire\Admin\StripeConfiguration::class)->name('stripe.config');
        Route::get('/credit-analytics', \App\Livewire\Admin\CreditAnalytics::class)->name('credit.analytics');
        Route::get('/financial-dashboard', \App\Livewire\Admin\FinancialDashboard::class)->name('financial.dashboard');
        Route::get('/upload-limits', \App\Livewire\Admin\UserUploadManager::class)->name('upload.limits');
        Route::get('/uploads', \App\Livewire\Admin\UploadManager::class)->name('uploads');
        Route::get('/job-monitor', \App\Livewire\Pages\Admin\JobMonitor::class)->name('job.monitor');

        // Export routes
        Route::prefix('exports')->name('exports.')->group(function () {
            Route::post('/uploads', [\App\Http\Controllers\Admin\ExportController::class, 'uploadsExport'])->name('uploads');
            Route::post('/users', [\App\Http\Controllers\Admin\ExportController::class, 'usersExport'])->name('users');
            Route::post('/system-metrics', [\App\Http\Controllers\Admin\ExportController::class, 'systemMetricsExport'])->name('system.metrics');
            Route::post('/upload-metrics', [\App\Http\Controllers\Admin\ExportController::class, 'uploadMetricsExport'])->name('upload.metrics');
            Route::get('/instant/uploads', [\App\Http\Controllers\Admin\ExportController::class, 'instantUploadsExport'])->name('instant.uploads');
            Route::get('/instant/users', [\App\Http\Controllers\Admin\ExportController::class, 'instantUsersExport'])->name('instant.users');
            Route::get('/download/{filename}', [\App\Http\Controllers\Admin\ExportController::class, 'downloadExport'])->name('download');
            Route::delete('/cleanup', [\App\Http\Controllers\Admin\ExportController::class, 'cleanupOldExports'])->name('cleanup');
        });
    });
require __DIR__.'/auth.php';
