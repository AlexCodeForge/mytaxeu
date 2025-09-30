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

// Thank You Page (after successful payment) - No authentication required for redirect from Stripe
Route::get('thank-you', [\App\Http\Controllers\ThankYouController::class, 'show'])->name('thank-you');

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
    Route::get('billing', \App\Livewire\Billing\BillingPage::class)->name('billing');
    Route::post('billing/portal-redirect', [\App\Http\Controllers\Billing\BillingPortalController::class, 'redirect'])->name('billing.portal.redirect');
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
        // Route::get('/users', AdminUsersIndexPage::class)->name('users.index'); // Commented out - using enhanced version instead
        Route::get('/users/enhanced', \App\Livewire\Admin\EnhancedUserManagement::class)->name('users.enhanced');
        Route::get('/stripe-config', \App\Livewire\Admin\StripeConfiguration::class)->name('stripe.config');
        Route::get('/credit-analytics', \App\Livewire\Admin\CreditAnalytics::class)->name('credit.analytics');
        Route::get('/financial-dashboard', \App\Livewire\Admin\FinancialDashboard::class)->name('financial.dashboard');
        Route::get('/upload-limits', \App\Livewire\Admin\UserUploadManager::class)->name('upload.limits');
        Route::get('/uploads', \App\Livewire\Admin\UploadManager::class)->name('uploads');
        Route::get('/job-monitor', \App\Livewire\Pages\Admin\JobMonitor::class)->name('job.monitor');

        // Email Settings routes (Livewire)
        Route::prefix('email-settings')->name('email-settings.')->group(function () {
            Route::get('/', \App\Livewire\Admin\EmailSettingsIndex::class)->name('index');
        });

        // Customer Email Management routes
        Route::prefix('customer-emails')->name('customer-emails.')->group(function () {
            Route::get('/', \App\Livewire\Admin\CustomerEmails\Index::class)->name('index');
            Route::get('/{conversation}', \App\Livewire\Admin\CustomerEmails\Show::class)->name('show');
        });

        // Rate Management
        Route::get('/rate-management', \App\Livewire\Admin\RateManagement::class)->name('rate.management');

        // Subscription Plans Management
        Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
            Route::get('/plans', \App\Livewire\Admin\Subscriptions\PlanManagement::class)->name('plans');
            Route::get('/discount-codes', \App\Livewire\Admin\Subscriptions\DiscountCodeManagement::class)->name('discount-codes');
        });

        // Export routes
        Route::prefix('exports')->name('exports.')->group(function () {
            Route::post('/uploads', [\App\Http\Controllers\Admin\ExportController::class, 'uploadsExport'])->name('uploads');
            Route::post('/users', [\App\Http\Controllers\Admin\ExportController::class, 'usersExport'])->name('users');
            Route::post('/system-metrics', [\App\Http\Controllers\Admin\ExportController::class, 'systemMetricsExport'])->name('system.metrics');
            Route::post('/upload-metrics', [\App\Http\Controllers\Admin\ExportController::class, 'uploadMetricsExport'])->name('upload.metrics');
            Route::post('/financial-data', [\App\Http\Controllers\Admin\ExportController::class, 'financialDataExport'])->name('financial.data');
            Route::get('/instant/uploads', [\App\Http\Controllers\Admin\ExportController::class, 'instantUploadsExport'])->name('instant.uploads');
            Route::get('/instant/users', [\App\Http\Controllers\Admin\ExportController::class, 'instantUsersExport'])->name('instant.users');
            Route::get('/download/{filename}', [\App\Http\Controllers\Admin\ExportController::class, 'downloadExport'])->name('download');
            Route::delete('/cleanup', [\App\Http\Controllers\Admin\ExportController::class, 'cleanupOldExports'])->name('cleanup');
        });
    });
require __DIR__.'/auth.php';
