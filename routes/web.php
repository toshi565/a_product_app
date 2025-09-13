<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// ホーム（公開）
Volt::route('/', 'home.index')->name('home');

// アーティスト詳細（公開）
Volt::route('artists/{artist}', 'artist.show')->name('artists.show');

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

    // 管理: 商品編集（現在の商品）
    Volt::route('admin/products', 'admin.products.index')
        ->middleware('can:admin')
        ->name('admin.products.index');

    // 管理: アーティスト編集
    Volt::route('admin/artists', 'admin.artists.index')
        ->middleware('can:admin')
        ->name('admin.artists.index');
});

require __DIR__ . '/auth.php';
