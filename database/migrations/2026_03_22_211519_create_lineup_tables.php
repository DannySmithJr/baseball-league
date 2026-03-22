<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Try multiple locations — migrations dir deploys reliably
        $names = ['team_starting_lineups.sql', 'team_pitching_staff.sql'];
        foreach ($names as $name) {
            $paths = [
                __DIR__ . '/' . $name,
                database_path('ootpimport/' . str_replace('.sql', '.install.sql', $name)),
            ];
            foreach ($paths as $path) {
                if (file_exists($path)) {
                    DB::unprepared(file_get_contents($path));
                    break;
                }
            }
        }
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS team_starting_lineups');
        DB::statement('DROP TABLE IF EXISTS team_pitching_staff');
    }
};
