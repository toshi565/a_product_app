<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// ホーム（公開）
Volt::route('/', 'home.index')->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');

    // 決済情報（説明用）
    Volt::route('payment', 'payment.edit')->name('payment.edit');
    Volt::route('payment/confirm', 'payment.confirm')->name('payment.confirm');
});

require __DIR__ . '/auth.php';
