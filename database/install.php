<?php
/**
 * sMLB Standalone Database Installer — Browser Based
 *
 * Upload to server: /database/install.php
 * Visit: https://smlb.one/database/install.php?key=smlb2026install
 *
 * Reads ../.env for DB credentials automatically. No terminal needed.
 */

define('INSTALL_KEY', 'smlb2026install');
define('LARAVEL_ROOT', '/home/smlbemgt/baseball-league');
define('ENV_PATH',    LARAVEL_ROOT . '/.env');
define('SCHEMA_PATH', __DIR__ . '/schema.sql');
define('IMPORT_DIR',  __DIR__ . '/ootpimport');

if (($_GET['key'] ?? '') !== INSTALL_KEY) {
    http_response_code(403);
    die('Access denied. Append ?key=YOUR_KEY to the URL.');
}

set_time_limit(600);
ini_set('memory_limit', '512M');

function parseEnv(string $path): array {
    $vars = [];
    if (!file_exists($path)) return $vars;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $vars[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
        }
    }
    return $vars;
}

$env = parseEnv(ENV_PATH);
$dbHost = $env['DB_HOST'] ?? 'localhost';
$dbPort = $env['DB_PORT'] ?? '3306';
$dbName = $env['DB_DATABASE'] ?? '';
$dbUser = $env['DB_USERNAME'] ?? '';
$dbPass = $env['DB_PASSWORD'] ?? '';

if (!$dbName || !$dbUser) {
    die('Could not read DB credentials from .env — check path: ' . (realpath(ENV_PATH) ?: ENV_PATH));
}

$action = $_POST['action'] ?? $_GET['action'] ?? null;
$k = INSTALL_KEY;
?><!DOCTYPE html>
<html><head>
<title>sMLB Database Installer</title>
<style>
body{background:#0f172a;color:#e2e8f0;font-family:'Courier New',monospace;padding:2rem;max-width:900px;margin:0 auto}
h1{color:#ef4444}h2{color:#94a3b8;border-bottom:1px solid #334155;padding-bottom:.5rem}
.ok{color:#4ade80}.err{color:#f87171}.warn{color:#fbbf24}.step{margin:.2rem 0}
.box{background:#1e293b;border:1px solid #334155;border-radius:8px;padding:1.5rem;margin:1rem 0}
a{color:#60a5fa}
button{background:#ef4444;color:#fff;border:none;padding:.6rem 1.5rem;font-size:.9rem;cursor:pointer;border-radius:6px;font-family:inherit;margin:.3rem 0}
button:hover{background:#dc2626}
.btn-blue{background:#2563eb}.btn-blue:hover{background:#1d4ed8}
.btn-green{background:#059669}.btn-green:hover{background:#047857}
.btn-purple{background:#7c3aed}.btn-purple:hover{background:#6d28d9}
.muted{color:#64748b;font-size:.85rem}
</style>
</head><body>
<h1>sMLB Database Installer</h1>
<?php

if (!$action):
    // ── Dashboard ──
    echo "<div class='box'>";
    echo "<p><strong>Database:</strong> {$dbName}</p>";
    echo "<p><strong>User:</strong> {$dbUser} @ {$dbHost}:{$dbPort}</p>";

    try {
        $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName}", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        echo "<p class='ok'>&#10003; Database connection successful</p>";
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>Existing tables: <strong>" . count($tables) . "</strong></p>";
    } catch (PDOException $e) {
        echo "<p class='err'>&#10007; Connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p class='warn'>Fix your .env DB credentials and reload this page.</p>";
        echo "</div></body></html>";
        exit;
    }

    echo "<p><strong>Schema:</strong> " . (file_exists(SCHEMA_PATH) ? "<span class='ok'>found</span> (" . number_format(filesize(SCHEMA_PATH)) . " bytes)" : "<span class='err'>NOT FOUND</span>") . "</p>";

    if (is_dir(IMPORT_DIR)) {
        $files = glob(IMPORT_DIR . '/*.sql');
        $totalSize = array_sum(array_map('filesize', $files));
        echo "<p><strong>Data files:</strong> <span class='ok'>" . count($files) . " files</span> (" . number_format($totalSize / 1024 / 1024, 1) . " MB)</p>";
    } else {
        echo "<p><strong>Data files:</strong> <span class='warn'>ootpimport/ not found</span></p>";
    }
    echo "</div>";

    echo "<h2>Step 1 &mdash; Create Tables</h2>";
    echo "<form method='post' action='?key={$k}'><input type='hidden' name='action' value='schema'>";
    echo "<button>Install Schema</button> <span class='muted'>Creates all tables (DROP + CREATE). Safe to re-run.</span></form>";

    echo "<h2>Step 2 &mdash; Import OOTP Data</h2>";
    echo "<form method='post' action='?key={$k}'><input type='hidden' name='action' value='import'>";
    echo "<button>Import All Data</button> <span class='muted'>Truncates then imports each table from ootpimport/</span></form>";

    echo "<h2>Step 3 &mdash; Performance Indexes</h2>";
    echo "<form method='post' action='?key={$k}'><input type='hidden' name='action' value='indexes'>";
    echo "<button style='background:#d97706'>Add Indexes</button> <span class='muted'>Adds performance indexes. Safe to re-run.</span></form>";

    echo "<h2>Step 4 &mdash; Laravel Setup</h2>";
    echo "<form method='post' action='?key={$k}' style='display:inline'><input type='hidden' name='action' value='migrate'>";
    echo "<button class='btn-blue'>Run Migrations</button></form> ";
    echo "<form method='post' action='?key={$k}' style='display:inline'><input type='hidden' name='action' value='cache'>";
    echo "<button class='btn-green'>Clear Caches</button></form>";

    echo "<h2>Or &mdash; Do Everything At Once</h2>";
    echo "<form method='post' action='?key={$k}' onsubmit=\"return confirm('This will DROP all tables, reimport everything, run migrations, and clear caches. Continue?')\">";
    echo "<input type='hidden' name='action' value='full'>";
    echo "<button class='btn-purple'>Full Install (Schema + Data + Migrate + Cache)</button></form>";

else:
    // ── Run actions ──
    try {
        $pdo = new PDO("mysql:host={$dbHost};port={$dbPort};dbname={$dbName}", $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec("SET NAMES utf8mb4");
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $pdo->exec("SET UNIQUE_CHECKS=0");
        $pdo->exec("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");
    } catch (PDOException $e) {
        die("<p class='err'>Connection failed: " . htmlspecialchars($e->getMessage()) . "</p>");
    }

    $startTime = microtime(true);
    $totalOk = 0;
    $totalFail = 0;

    function runSqlFile(PDO $pdo, string $path, string $label, int &$totalOk, int &$totalFail): void {
        if (!file_exists($path)) {
            echo "<p class='step err'>&#10007; Not found: {$label}</p>";
            $totalFail++;
            return;
        }

        $sizeMB = number_format(filesize($path) / 1024 / 1024, 1);
        $sql = file_get_contents($path);
        if ($sql === false) {
            echo "<p class='step err'>&#10007; Could not read: {$label}</p>";
            $totalFail++;
            return;
        }

        $ok = 0;
        $fail = 0;
        $statements = preg_split('/;\s*\n/', $sql);

        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || str_starts_with($stmt, '--')) continue;

            try {
                $pdo->exec($stmt);
                $ok++;
            } catch (PDOException $e) {
                $fail++;
                if ($fail <= 3) {
                    echo "<p class='step err'>&nbsp;&nbsp;&#10007; " . htmlspecialchars(substr($e->getMessage(), 0, 120)) . "</p>";
                }
            }
        }

        $status = $fail === 0 ? 'ok' : 'warn';
        echo "<p class='step {$status}'>&#10003; {$label} ({$sizeMB} MB, {$ok} ok" . ($fail ? ", {$fail} failed" : "") . ")</p>";
        $totalOk += $ok;
        $totalFail += $fail;
        if (ob_get_level()) ob_flush();
        flush();
    }

    // ── Schema ──
    if (in_array($action, ['schema', 'full'])) {
        echo "<h2>Installing Schema...</h2>";
        if (ob_get_level()) ob_flush(); flush();
        runSqlFile($pdo, SCHEMA_PATH, 'schema.sql', $totalOk, $totalFail);
    }

    // ── Data Import ──
    if (in_array($action, ['import', 'full'])) {
        echo "<h2>Importing Data...</h2>";
        if (ob_get_level()) ob_flush(); flush();

        if (!is_dir(IMPORT_DIR)) {
            echo "<p class='err'>ootpimport/ directory not found at " . IMPORT_DIR . "</p>";
        } else {
            $files = glob(IMPORT_DIR . '/*.sql');
            sort($files);
            $total = count($files);

            foreach ($files as $i => $file) {
                $table = basename($file, '.sql');
                try { $pdo->exec("TRUNCATE TABLE `{$table}`"); } catch (PDOException $e) {}
                $num = $i + 1;
                runSqlFile($pdo, $file, "[{$num}/{$total}] {$table}", $totalOk, $totalFail);
            }
        }
    }

    // ── Indexes ──
    if (in_array($action, ['indexes', 'full'])) {
        echo "<h2>Adding Performance Indexes...</h2>";
        if (ob_get_level()) ob_flush(); flush();

        $indexes = [
            ['game_logs',                     'idx_game_logs_game_id',   'game_id'],
            ['game_logs',                     'idx_gl_game_type',        'game_id, type'],
            ['players_at_bat_batting_stats',  'idx_atbat_game_id',       'game_id'],
            ['players_at_bat_batting_stats',  'idx_atbat_game_team',     'game_id, team_id'],
            ['players_game_batting',          'idx_pgb_game_id',         'game_id'],
            ['players_game_pitching_stats',   'idx_pgp_game_id',         'game_id'],
            ['players_career_fielding_stats', 'idx_pcf_player_year_split','player_id, year, split_id'],
            ['players_career_batting_stats',  'idx_pcbs_player_year_split','player_id, year, split_id'],
            ['games',                         'idx_games_played_date',   'played, date'],
            ['games',                         'idx_games_played_type',   'played, game_type'],
            ['players_game_batting',          'idx_pgb_level_team',      'level_id, team_id'],
            ['players_game_batting',          'idx_pgb_level_player',    'level_id, player_id'],
            ['players_game_batting',          'idx_pgb_team',            'team_id'],
            ['players_game_pitching_stats',   'idx_pgp_level_team',      'level_id, team_id'],
            ['players_game_pitching_stats',   'idx_pgp_level_gs',        'level_id, gs'],
            ['players_game_pitching_stats',   'idx_pgp_team',            'team_id'],
            ['team_roster',                   'idx_tr_team_list',        'team_id, list_id'],
            ['team_roster',                   'idx_tr_player_team',      'player_id, team_id'],
            ['players_career_batting_stats',  'idx_pcbs_split_year_ab',  'split_id, year, ab'],
            ['players_career_batting_stats',  'idx_pcbs_player_split',   'player_id, split_id'],
            ['players_career_pitching_stats', 'idx_pcps_player_split',   'player_id, split_id'],
            ['players_streak',               'idx_ps_streak_ended',      'streak_id, has_ended, value'],
        ];

        $idxOk = 0; $idxSkip = 0;
        foreach ($indexes as [$table, $name, $cols]) {
            try {
                $pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$name}` ({$cols})");
                echo "<p class='step ok'>&#10003; {$table}.{$name}</p>";
                $idxOk++;
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'Duplicate key name')) {
                    $idxSkip++;
                } else {
                    echo "<p class='step err'>&#10007; {$table}.{$name}: " . htmlspecialchars(substr($e->getMessage(), 0, 100)) . "</p>";
                }
            }
        }
        echo "<p class='ok'>&#10003; Indexes: {$idxOk} added, {$idxSkip} already existed</p>";
        if (ob_get_level()) ob_flush(); flush();
    }

    // ── Migrations ──
    if (in_array($action, ['migrate', 'full'])) {
        echo "<h2>Running Migrations...</h2>";
        if (ob_get_level()) ob_flush(); flush();

        $base = LARAVEL_ROOT;
        $output = [];
        exec("cd " . escapeshellarg($base) . " && php artisan migrate --force 2>&1", $output, $code);
        foreach ($output as $line) {
            $cls = str_contains($line, 'DONE') || str_contains($line, 'Nothing') ? 'ok' : 'step';
            echo "<p class='{$cls}'>" . htmlspecialchars($line) . "</p>";
        }
        echo $code === 0
            ? "<p class='ok'>&#10003; Migrations complete</p>"
            : "<p class='err'>&#10007; Migration error (code {$code})</p>";
    }

    // ── Cache ──
    if (in_array($action, ['cache', 'full'])) {
        echo "<h2>Clearing Caches...</h2>";
        if (ob_get_level()) ob_flush(); flush();

        $base = LARAVEL_ROOT;
        foreach (['config:cache', 'route:cache', 'view:cache'] as $cmd) {
            $out = [];
            exec("cd " . escapeshellarg($base) . " && php artisan {$cmd} 2>&1", $out, $code);
            echo "<p class='" . ($code === 0 ? 'ok' : 'err') . "'>" . ($code === 0 ? '&#10003;' : '&#10007;') . " {$cmd}</p>";
        }
    }

    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    $pdo->exec("SET UNIQUE_CHECKS=1");

    $elapsed = round(microtime(true) - $startTime, 1);
    echo "<div class='box'>";
    echo "<p class='ok'><strong>Done!</strong> {$totalOk} statements OK";
    if ($totalFail) echo ", <span class='err'>{$totalFail} failed</span>";
    echo " &mdash; {$elapsed}s</p>";
    echo "<p><a href='?key={$k}'>&larr; Back to installer</a></p>";
    echo "</div>";

endif;
?>
</body></html>
