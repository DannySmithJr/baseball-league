<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

DB::table('team_starting_lineups')->truncate();
DB::table('team_pitching_staff')->truncate();

DB::unprepared(file_get_contents(__DIR__ . '/team_starting_lineups.sql'));
DB::unprepared(file_get_contents(__DIR__ . '/team_pitching_staff.sql'));

echo "Done: " . DB::table('team_starting_lineups')->count() . " lineups, "
     . DB::table('team_pitching_staff')->count() . " staff\n";
