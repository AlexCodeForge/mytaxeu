<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LandingPageController;
use App\Livewire\Pages\Dashboard as DashboardPage;
use App\Livewire\Pages\Admin\Index as AdminIndexPage;

Route::get('/', [LandingPageController::class, 'index'])->name('landing');

Route::get('dashboard', DashboardPage::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware(['auth', 'verified', 'ensure.admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', AdminIndexPage::class)->name('index');
    });
require __DIR__.'/auth.php';
