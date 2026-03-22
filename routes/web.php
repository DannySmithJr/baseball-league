<?php

use App\Http\Controllers\Adm\SettingsController;
use App\Http\Controllers\Adm\SqlController;
use App\Http\Controllers\Adm\StreamScheduleController;
use App\Http\Controllers\StandingsController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\GameController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\PreviewController;
use Illuminate\Support\Facades\Route;

Route::get('/', \App\Http\Controllers\HomeController::class)->name('home');

// Public pages
Route::get('standings', StandingsController::class)->name('standings');
Route::get('schedule', ScheduleController::class)->name('schedule');
Route::get('teams', [TeamController::class, 'index'])->name('teams');
Route::get('team/{id}', [TeamController::class, 'show'])->name('team');
Route::get('team/{id}/finances', [TeamController::class, 'finances'])->name('team.finances');
Route::get('team/{id}/history', [TeamController::class, 'history'])->name('team.history');
Route::get('team/{id}/injuries', [TeamController::class, 'injuries'])->name('team.injuries');
Route::get('team/{id}/minors', [TeamController::class, 'minors'])->name('team.minors');
Route::get('player/{id}',         [PlayerController::class, 'show'])->name('player');
Route::get('player/{id}/news',    [PlayerController::class, 'news'])->name('player.news');
Route::get('player/{id}/stats',   [PlayerController::class, 'stats'])->name('player.stats');
Route::get('player/{id}/ratings', [PlayerController::class, 'ratings'])->name('player.ratings');
Route::get('player/{id}/bio',      [PlayerController::class, 'bio'])->name('player.bio');
Route::get('player/{id}/contract',[PlayerController::class, 'contract'])->name('player.contract');
Route::get('player/{id}/gamelog', [PlayerController::class, 'gamelog'])->name('player.gamelog');
Route::get('game/{id}', [GameController::class, 'show'])->name('game');
Route::get('preview', PreviewController::class)->name('preview');

// Legal pages
Route::view('legal/terms',      'legal.terms')->name('legal.terms');
Route::view('legal/privacy',    'legal.privacy')->name('legal.privacy');
Route::view('legal/notices',    'legal.notices')->name('legal.notices');
Route::view('legal/contact',    'legal.contact')->name('legal.contact');
Route::view('legal/do-not-sell','legal.do-not-sell')->name('legal.do-not-sell');
Route::view('legal/cookies',    'legal.cookies')->name('legal.cookies');

// User Control Panel
Route::middleware(['auth', 'verified'])->prefix('cp')->name('cp.')->group(function () {
    Route::get('/', [\App\Http\Controllers\CpController::class, 'index'])->name('index');
    Route::post('/request-gm', [\App\Http\Controllers\CpController::class, 'requestGm'])->name('requestGm');
    Route::patch('/account', [\App\Http\Controllers\CpController::class, 'updateAccount'])->name('updateAccount');
});

// Admin Control Panel
Route::middleware(['auth', 'verified', 'admin'])->prefix('adm')->name('adm.')->group(function () {
    Route::view('/', 'adm.index')->name('index');
    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('sql', [SqlController::class, 'index'])->name('sql.index');
    Route::get('sql/meta', [SqlController::class, 'meta'])->name('sql.meta');
    Route::post('sql/process', [SqlController::class, 'process'])->name('sql.process');
    Route::get('streams', [StreamScheduleController::class, 'index'])->name('streams');
    Route::post('streams', [StreamScheduleController::class, 'store'])->name('streams.store');
    Route::delete('streams/{id}', [StreamScheduleController::class, 'destroy'])->name('streams.destroy');
    Route::get('news-sources', [\App\Http\Controllers\Adm\NewsSourceController::class, 'index'])->name('news-sources');
    Route::post('news-sources', [\App\Http\Controllers\Adm\NewsSourceController::class, 'store'])->name('news-sources.store');
    Route::delete('news-sources/{id}', [\App\Http\Controllers\Adm\NewsSourceController::class, 'destroy'])->name('news-sources.destroy');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
