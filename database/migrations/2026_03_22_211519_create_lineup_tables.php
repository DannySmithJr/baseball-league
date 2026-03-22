<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $files = [
            database_path('ootpimport/team_starting_lineups.install.sql'),
            database_path('ootpimport/team_pitching_staff.install.sql'),
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                DB::unprepared(file_get_contents($file));
            }
        }
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS team_starting_lineups');
        DB::statement('DROP TABLE IF EXISTS team_pitching_staff');
    }
};
