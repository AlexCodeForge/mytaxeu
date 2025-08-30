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
        Route::get('/', AdminIndexPage::class)->name('index');
        Route::get('/users', AdminUsersIndexPage::class)->name('users.index');
        Route::get('/stripe-config', \App\Livewire\Admin\StripeConfiguration::class)->name('stripe.config');
        Route::get('/credit-analytics', \App\Livewire\Admin\CreditAnalytics::class)->name('credit.analytics');
        Route::get('/upload-limits', \App\Livewire\Admin\UserUploadManager::class)->name('upload.limits');
        Route::get('/job-monitor', \App\Livewire\Pages\Admin\JobMonitor::class)->name('job.monitor');
    });
require __DIR__.'/auth.php';
