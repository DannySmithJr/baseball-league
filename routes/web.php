<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

// User Control Panel
Route::view('cp', 'cp.index')
    ->middleware(['auth', 'verified'])
    ->name('cp');

// Admin Control Panel
Route::view('adm', 'adm.index')
    ->middleware(['auth', 'verified', 'admin'])
    ->name('adm');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
