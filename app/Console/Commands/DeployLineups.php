<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeployLineups extends Command
{
    protected $signature = 'lineups:deploy';
    protected $description = 'Import all OOTP data, lineups, and pitching staff from deploy SQL files';

    public function handle(): int
    {
        // Import bulk data files (from Install All)
        $dataDir = base_path('database/migrations/data');
        if (is_dir($dataDir)) {
            $dataFiles = glob($dataDir . '/*.sql') ?: [];
            foreach ($dataFiles as $path) {
                $name = basename($path);
                try {
                    DB::unprepared(file_get_contents($path));
                    $table = str_replace('.mysql.sql', '', $name);
                    $table = str_replace('.sql', '', $table);
                    $count = DB::table($table)->count();
                    $this->info("Imported {$table}: {$count} rows");
                } catch (\Throwable $e) {
                    $this->warn("Error importing {$name}: " . substr($e->getMessage(), 0, 200));
                }
            }
        }

        // Import lineup/staff files
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
