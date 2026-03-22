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

            // Sort: base files before splits, splits in numeric order
            usort($dataFiles, function ($a, $b) {
                $parse = function ($path) {
                    $name = str_replace('.mysql.sql', '', basename($path));
                    if (preg_match('/^(.+?)_(\d+)$/', $name, $m)) {
                        return [$m[1], (int)$m[2]];
                    }
                    return [$name, -1];
                };
                [$baseA, $numA] = $parse($a);
                [$baseB, $numB] = $parse($b);
                $cmp = strcmp($baseA, $baseB);
                return $cmp !== 0 ? $cmp : $numA <=> $numB;
            });

            foreach ($dataFiles as $path) {
                $name = basename($path);
                try {
                    // Read and execute in chunks to avoid memory exhaustion
                    $fp = fopen($path, 'r');
                    $buffer = '';
                    while (($line = fgets($fp)) !== false) {
                        $trimmed = trim($line);
                        if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
                            continue;
                        }
                        $buffer .= $line;
                        if (str_ends_with(rtrim($trimmed), ';')) {
                            DB::unprepared($buffer);
                            $buffer = '';
                        }
                    }
                    fclose($fp);
                    $this->info("Imported {$name}");
                } catch (\Throwable $e) {
                    $this->warn("Error importing {$name}: " . substr($e->getMessage(), 0, 200));
                }
                // Free memory between files
                DB::connection()->disconnect();
                DB::connection()->reconnect();
                gc_collect_cycles();
            }
        }

        // Rebuild performance indexes (OOTP imports drop/recreate tables)
        $indexes = [
            'ALTER TABLE players_game_batting ADD INDEX idx_pgb_player (player_id)',
            'ALTER TABLE players_game_pitching_stats ADD INDEX idx_pgps_player (player_id)',
            'ALTER TABLE players_scouted_ratings ADD INDEX idx_psr_player (player_id)',
            'ALTER TABLE players_contract ADD INDEX idx_pc_player (player_id)',
            'ALTER TABLE players_career_batting_stats ADD INDEX idx_pcbs_team_split_year (team_id, split_id, year)',
            'ALTER TABLE players_career_pitching_stats ADD INDEX idx_pcps_team_split_year (team_id, split_id, year)',
        ];
        foreach ($indexes as $sql) {
            try { DB::statement($sql); } catch (\Throwable $e) { /* index may already exist */ }
        }
        $this->info('Rebuilt performance indexes');

        // Import lineup/staff files
        foreach (['team_starting_lineups.sql', 'team_pitching_staff.sql'] as $name) {
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
