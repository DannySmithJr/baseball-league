<?php

namespace App\Http\Controllers\Adm;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SqlController extends Controller
{
    private function importPath(): string
    {
        return rtrim(env('SQL_IMPORT_PATH', storage_path('app/sql-import')), '/\\');
    }

    public function meta()
    {
        $path  = $this->importPath();
        $files = glob($path . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        $meta  = [];

        foreach ($files as $f) {
            $name       = basename($f);
            $exportDate = null;
            $gameDate   = null;

            $fp = fopen($f, 'r');
            for ($i = 0; $i < 10; $i++) {
                $line = fgets($fp);
                if ($line === false) break;
                $line = trim($line);

                // # Wednesday, March 18th , 2026 - OOTP Baseball ...
                if (!$exportDate && str_starts_with($line, '#') && preg_match('/(\w+,\s+\w+\s+\d+\w*\s*,?\s*\d{4})/', $line, $m)) {
                    $exportDate = trim($m[1]);
                }

                // # Game date: 1970-05-25
                if (!$gameDate && preg_match('/Game date:\s*(\d{4}-\d{2}-\d{2})/i', $line, $m)) {
                    $gameDate = $m[1];
                }

                if ($exportDate && $gameDate) break;
            }
            fclose($fp);

            $meta[$name] = [
                'export_date' => $exportDate,
                'game_date'   => $gameDate,
                'modified'    => filemtime($f),
            ];
        }

        return response()->json($meta);
    }

    public function index()
    {
        $path = $this->importPath();
        $files = [];

        if (is_dir($path)) {
            $files = collect(glob($path . DIRECTORY_SEPARATOR . '*.sql'))
                ->map(fn($f) => [
                    'name' => basename($f),
                    'size' => filesize($f),
                    'modified' => filemtime($f),
                ])
                ->sort(function($a, $b) {
                    // Extract base name and split number for proper ordering
                    // e.g. "players_at_bat_batting_stats_3.mysql.sql" -> base="players_at_bat_batting_stats", num=3
                    $parse = function($name) {
                        $name = str_replace('.mysql.sql', '', $name);
                        if (preg_match('/^(.+?)_(\d+)$/', $name, $m)) {
                            return [$m[1], (int)$m[2]];
                        }
                        return [$name, -1]; // base file sorts first
                    };
                    [$baseA, $numA] = $parse($a['name']);
                    [$baseB, $numB] = $parse($b['name']);
                    $cmp = strcmp($baseA, $baseB);
                    return $cmp !== 0 ? $cmp : $numA <=> $numB;
                })
                ->values()
                ->all();
        }

        $lastImport = \App\Models\Setting::instance()->ootp_last_import ?? null;

        // Detect OOTP almanac directory for lineups — use season year from DB
        $almanacDir = null;
        $almanacTeamCount = 0;
        $ootpBase = 'C:/Users/danny/Documents/Out of the Park Developments/OOTP Baseball 27/saved_games/sMLB.lg/news';
        $almanacYear = app(\App\Services\OotpService::class)->seasonYear();
        if ($almanacYear && is_dir($ootpBase)) {
            $almanacDir = $ootpBase . '/almanac_' . $almanacYear . '/teams';
            if (is_dir($almanacDir)) {
                $almanacTeamCount = count(array_filter(
                    glob($almanacDir . '/team_[0-9]*.html'),
                    fn($f) => preg_match('/team_\d+\.html$/', basename($f))
                ));
            } else {
                $almanacDir = null;
            }
        }

        return view('adm.sql', compact('files', 'path', 'lastImport', 'almanacDir', 'almanacYear', 'almanacTeamCount'));
    }

    public function process(Request $request)
    {
        $request->validate([
            'file'   => ['required', 'string', 'regex:/^[\w\-. ]+\.sql$/i'],
            'offset' => ['required', 'integer', 'min:0'],
        ]);

        $path     = $this->importPath();
        $fullPath = $path . DIRECTORY_SEPARATOR . $request->input('file');

        if (!file_exists($fullPath)) {
            return response()->json(['error' => 'File not found.'], 404);
        }

        // Ensure the resolved path is still inside the import directory
        if (!str_starts_with(realpath($fullPath), realpath($path))) {
            return response()->json(['error' => 'Invalid file path.'], 403);
        }

        $fileSize   = filesize($fullPath);
        $byteOffset = (int) $request->input('offset');

        $fp = fopen($fullPath, 'r');
        fseek($fp, $byteOffset);

        $lines     = [];
        $lineCount = 0;
        while (!feof($fp) && $lineCount < 1000) {
            $line = fgets($fp);
            if ($line !== false) {
                $lines[] = $line;
                $lineCount++;
            }
        }

        $newOffset = ftell($fp);
        $done      = feof($fp);
        fclose($fp);

        // Execute complete SQL statements
        $buffer = '';
        $errors = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Skip blank lines and comments
            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '#')) {
                continue;
            }

            $buffer .= $line;

            if (str_ends_with(rtrim($trimmed), ';')) {
                try {
                    DB::unprepared($buffer);
                } catch (\Throwable $e) {
                    $errors[] = substr($e->getMessage(), 0, 300);
                }
                $buffer = '';
            }
        }

        $deleted = false;
        if ($done && empty($errors)) {
            // Copy to deploy directory for live server
            $deployDir = database_path('migrations/data');
            if (!is_dir($deployDir)) @mkdir($deployDir, 0755, true);
            @copy($fullPath, $deployDir . '/' . $request->input('file'));

            if (app()->isProduction()) {
                @unlink($fullPath);
                $deleted = true;
            }
            \App\Models\Setting::instance()->update([
                'ootp_last_import' => now()->format('Y-m-d H:i:s'),
            ]);
        }

        return response()->json([
            'offset'    => $newOffset,
            'file_size' => $fileSize,
            'done'      => $done,
            'errors'    => $errors,
            'deleted'   => $deleted,
        ]);
    }

    /**
     * Parse lineups from OOTP almanac HTML, import into DB, and dump to migrations.
     */
    public function parseLineups()
    {
        $output = [];
        $errors = [];

        // Find OOTP almanac directory using season year from DB
        $reportsDir = null;
        $ootpBase = 'C:/Users/danny/Documents/Out of the Park Developments/OOTP Baseball 27/saved_games/sMLB.lg/news';
        $seasonYear = app(\App\Services\OotpService::class)->seasonYear();
        if ($seasonYear && is_dir($ootpBase)) {
            $reportsDir = $ootpBase . '/almanac_' . $seasonYear . '/teams';
            if (is_dir($reportsDir)) {
                $output[] = 'Source: almanac_' . $seasonYear;
            } else {
                $reportsDir = null;
            }
        }
        if (!$reportsDir || !is_dir($reportsDir)) {
            $reportsDir = public_path('reports/teams');
        }
        if (!is_dir($reportsDir)) {
            return response()->json(['output' => [], 'errors' => ['Reports directory not found']]);
        }

        $files = array_filter(glob($reportsDir . '/team_[0-9]*.html'), fn($f) => preg_match('/team_\d+\.html$/', basename($f)));
        $output[] = count($files) . ' team files found';

        // Create tables if missing
        DB::statement("CREATE TABLE IF NOT EXISTS `team_starting_lineups` (
            `team_id` int DEFAULT NULL, `vs` varchar(3) DEFAULT NULL, `slot` smallint DEFAULT NULL,
            `player_id` int DEFAULT NULL, `bats` varchar(1) DEFAULT NULL, `position` varchar(4) DEFAULT NULL
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        DB::statement("CREATE TABLE IF NOT EXISTS `team_pitching_staff` (
            `team_id` int DEFAULT NULL, `player_id` int DEFAULT NULL, `role` varchar(20) DEFAULT NULL,
            `throws` varchar(1) DEFAULT NULL, `sort_order` smallint DEFAULT NULL
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Parse
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

        // Import
        DB::table('team_starting_lineups')->truncate();
        DB::table('team_pitching_staff')->truncate();

        if ($lineupRows) {
            DB::unprepared("INSERT INTO `team_starting_lineups` VALUES " . implode(",", $lineupRows));
            $output[] = count($lineupRows) . ' lineup slots imported';
        }
        if ($staffRows) {
            DB::unprepared("INSERT INTO `team_pitching_staff` VALUES " . implode(",", $staffRows));
            $output[] = count($staffRows) . ' pitching staff imported';
        }

        // Dump to migrations dir for deploy
        $migDir = database_path('migrations');

        $vals = [];
        foreach (DB::table('team_starting_lineups')->get() as $r) {
            $vals[] = "({$r->team_id},'" . addslashes($r->vs) . "',{$r->slot},{$r->player_id},'" . addslashes($r->bats) . "','" . addslashes($r->position) . "')";
        }
        $sql = "DROP TABLE IF EXISTS `team_starting_lineups`;\nCREATE TABLE `team_starting_lineups` (`team_id` int DEFAULT NULL, `vs` varchar(3) DEFAULT NULL, `slot` smallint DEFAULT NULL, `player_id` int DEFAULT NULL, `bats` varchar(1) DEFAULT NULL, `position` varchar(4) DEFAULT NULL) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n";
        if ($vals) $sql .= "INSERT INTO `team_starting_lineups` VALUES " . implode(",", $vals) . ";\n";
        file_put_contents($migDir . '/team_starting_lineups.sql', $sql);

        $vals = [];
        foreach (DB::table('team_pitching_staff')->get() as $r) {
            $vals[] = "({$r->team_id},{$r->player_id},'" . addslashes($r->role) . "','" . addslashes($r->throws) . "',{$r->sort_order})";
        }
        $sql = "DROP TABLE IF EXISTS `team_pitching_staff`;\nCREATE TABLE `team_pitching_staff` (`team_id` int DEFAULT NULL, `player_id` int DEFAULT NULL, `role` varchar(20) DEFAULT NULL, `throws` varchar(1) DEFAULT NULL, `sort_order` smallint DEFAULT NULL) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n";
        if ($vals) $sql .= "INSERT INTO `team_pitching_staff` VALUES " . implode(",", $vals) . ";\n";
        file_put_contents($migDir . '/team_pitching_staff.sql', $sql);
        $output[] = 'Deploy SQL written to database/migrations/';

        return response()->json(['output' => $output, 'errors' => $errors]);
    }
}
