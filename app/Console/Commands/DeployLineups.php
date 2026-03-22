<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeployLineups extends Command
{
    protected $signature = 'lineups:deploy';
    protected $description = 'Import starting lineups and pitching staff from SQL files in database/migrations/';

    public function handle(): int
    {
        $files = [
            'team_starting_lineups.sql',
            'team_pitching_staff.sql',
        ];

        foreach ($files as $name) {
            $path = base_path("database/migrations/{$name}");

            if (!file_exists($path)) {
                $this->warn("Skipped: {$name} (not found)");
                continue;
            }

            DB::unprepared(file_get_contents($path));
            $table = str_replace('.sql', '', $name);
            $count = DB::table($table)->count();
            $this->info("Imported {$table}: {$count} rows");
        }

        return 0;
    }
}
