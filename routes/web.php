<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LandingPageController;
use App\Livewire\Pages\Dashboard as DashboardPage;
use App\Livewire\Pages\Admin\Index as AdminIndexPage;
use App\Livewire\Admin\Users\Index as AdminUsersIndexPage;

Route::get('/', [LandingPageController::class, 'index'])->name('landing');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardPage::class)->name('dashboard');
    Route::get('uploads', \App\Livewire\Uploads\Index::class)->name('uploads.index');
    Route::get('uploads/new', \App\Livewire\Uploads\UploadCsv::class)->name('uploads.create');
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
    });
require __DIR__.'/auth.php';
