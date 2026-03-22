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

    Route::post('parse-lineups', function () {
        $pdo = \Illuminate\Support\Facades\DB::connection()->getPdo();
        $output = [];

        // Create tables if missing
        $pdo->exec("CREATE TABLE IF NOT EXISTS `team_starting_lineups` (
            `team_id` int DEFAULT NULL, `vs` varchar(3) DEFAULT NULL, `slot` smallint DEFAULT NULL,
            `player_id` int DEFAULT NULL, `bats` varchar(1) DEFAULT NULL, `position` varchar(4) DEFAULT NULL
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS `team_pitching_staff` (
            `team_id` int DEFAULT NULL, `player_id` int DEFAULT NULL, `role` varchar(20) DEFAULT NULL,
            `throws` varchar(1) DEFAULT NULL, `sort_order` smallint DEFAULT NULL
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $output[] = 'Tables created/verified';

        $reportsDir = public_path('reports/teams');
        if (!is_dir($reportsDir)) return back()->with('error', 'reports/teams/ not found');

        $files = array_filter(glob($reportsDir . '/team_[0-9]*.html'), fn($f) => preg_match('/team_\d+\.html$/', basename($f)));
        $output[] = count($files) . ' team files found';

        $lineupRows = [];
        $staffRows = [];

        foreach ($files as $file) {
            if (!preg_match('/team_(\d+)\.html$/', basename($file), $m)) continue;
            $teamId = (int)$m[1];
            $html = file_get_contents($file);

            foreach (['RHP' => 'rhp', 'LHP' => 'lhp'] as $label => $vs) {
                if (!preg_match('/LINEUP VS ' . $label . '<\/th>.*?<\/table>\s*<table[^>]*>(.*?)<\/table>/si', $html, $tm)) continue;
                preg_match_all('/<tr>\s*<td[^>]*>(\d+)<\/td>\s*<td[^>]*>([^<]*)<\/td>\s*<td[^>]*><a href="[^"]*player_(\d*)\.[^"]*">([^<]*)<\/a><\/td>\s*<td[^>]*>([^<]*)<\/td>/si', $tm[1], $matches, PREG_SET_ORDER);
                foreach ($matches as $row) {
                    $pid = (int)$row[3];
                    if ($pid === 0 || trim($row[5]) === '') continue;
                    $lineupRows[] = "({$teamId},'" . addslashes($vs) . "'," . (int)$row[1] . ",{$pid},'" . addslashes(trim($row[2])) . "','" . addslashes(trim($row[5])) . "')";
                }
            }

            if (preg_match('/PITCHING STAFF<\/th>.*?<\/table>\s*<table[^>]*>(.*?)<\/table>/si', $html, $pm)) {
                preg_match_all('/<tr>(.*?)<\/tr>/si', $pm[1], $rows);
                $order = 0;
                foreach ($rows[1] as $rowHtml) {
                    if (!preg_match('/<td[^>]*>([^<]+)<\/td>\s*<td[^>]*>([^<]*)<\/td>\s*<td[^>]*><a href="[^"]*player_(\d+)\.[^"]*">/si', $rowHtml, $rm)) continue;
                    $order++;
                    $staffRows[] = "({$teamId}," . (int)$rm[3] . ",'" . addslashes(trim($rm[1])) . "','" . addslashes(trim($rm[2])) . "',{$order})";
                }
            }
        }

        $pdo->exec("TRUNCATE TABLE `team_starting_lineups`");
        $pdo->exec("TRUNCATE TABLE `team_pitching_staff`");

        if ($lineupRows) {
            $pdo->exec("INSERT INTO `team_starting_lineups` VALUES " . implode(",", $lineupRows));
            $output[] = count($lineupRows) . ' lineup slots imported';
        }
        if ($staffRows) {
            $pdo->exec("INSERT INTO `team_pitching_staff` VALUES " . implode(",", $staffRows));
            $output[] = count($staffRows) . ' pitching staff imported';
        }

        return back()->with('status', implode(' | ', $output));
    })->name('parse-lineups');
});

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

require __DIR__.'/auth.php';
