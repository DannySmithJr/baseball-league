#!/usr/bin/env php
<?php
/**
 * Standalone database installer for sMLB.
 *
 * Usage (from project root):
 *   php database/install.php
 *
 * This script:
 *   1. Reads .env for DB credentials
 *   2. Imports database/schema.sql (creates all tables)
 *   3. Runs Laravel migrations (custom tables: settings, rivalries, etc.)
 *   4. Seeds initial data (team logos, news sources)
 */

// ── Parse .env ──────────────────────────────────────────────────────────────

$envPath = __DIR__ . '/../.env';
if (!file_exists($envPath)) {
    die("ERROR: .env file not found at $envPath\n");
}

$env = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#')) continue;
    if (!str_contains($line, '=')) continue;
    [$key, $val] = explode('=', $line, 2);
    $env[trim($key)] = trim($val, " \t\n\r\0\x0B\"'");
}

$host = $env['DB_HOST'] ?? '127.0.0.1';
$port = $env['DB_PORT'] ?? '3306';
$db   = $env['DB_DATABASE'] ?? '';
$user = $env['DB_USERNAME'] ?? '';
$pass = $env['DB_PASSWORD'] ?? '';

if (!$db || !$user) {
    die("ERROR: DB_DATABASE and DB_USERNAME must be set in .env\n");
}

echo "Database: $db @ $host:$port (user: $user)\n";

// ── Connect ─────────────────────────────────────────────────────────────────

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    die("ERROR: Cannot connect to database: " . $e->getMessage() . "\n");
}

// ── Step 1: Import schema.sql ───────────────────────────────────────────────

$schemaFile = __DIR__ . '/schema.sql';
if (!file_exists($schemaFile)) {
    die("ERROR: schema.sql not found at $schemaFile\n");
}

echo "\n[1/3] Importing schema.sql...\n";

$sql = file_get_contents($schemaFile);
// Split by statement delimiter, filtering empty
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");

try {
    $pdo->exec($sql);
    echo "  ✓ Schema imported successfully.\n";
} catch (PDOException $e) {
    // MySQL can't exec multiple statements via PDO::exec in some configs
    // Fall back to statement-by-statement
    echo "  Bulk import failed, trying statement-by-statement...\n";

    $statements = array_filter(
        array_map('trim', preg_split('/;\s*\n/', $sql)),
        fn($s) => $s && !str_starts_with($s, '--') && !str_starts_with($s, '/*')
    );

    $success = 0;
    $skipped = 0;
    foreach ($statements as $stmt) {
        try {
            $pdo->exec($stmt);
            $success++;
        } catch (PDOException $e2) {
            // Table already exists or other non-fatal error
            if (str_contains($e2->getMessage(), 'already exists')) {
                $skipped++;
            } else {
                echo "  WARNING: " . substr($e2->getMessage(), 0, 120) . "\n";
                $skipped++;
            }
        }
    }
    echo "  ✓ Done. $success statements executed, $skipped skipped.\n";
}

$pdo->exec("SET FOREIGN_KEY_CHECKS=1");

// ── Step 2: Run Laravel migrations ──────────────────────────────────────────

echo "\n[2/3] Running Laravel migrations...\n";
$projectRoot = realpath(__DIR__ . '/..');
$output = [];
$exitCode = 0;
exec("cd " . escapeshellarg($projectRoot) . " && php artisan migrate --force 2>&1", $output, $exitCode);
echo "  " . implode("\n  ", $output) . "\n";
if ($exitCode !== 0) {
    echo "  WARNING: Migration exited with code $exitCode\n";
} else {
    echo "  ✓ Migrations complete.\n";
}

// ── Step 3: Check table count ───────────────────────────────────────────────

echo "\n[3/3] Verifying...\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "  ✓ $db has " . count($tables) . " tables.\n";

// Check for key tables
$required = ['teams', 'players', 'games', 'settings', 'users'];
$missing = array_diff($required, $tables);
if ($missing) {
    echo "  WARNING: Missing tables: " . implode(', ', $missing) . "\n";
} else {
    echo "  ✓ All required tables present.\n";
}

// Check if tables have data
$teamCount = $pdo->query("SELECT COUNT(*) FROM teams")->fetchColumn();
echo "\n  Teams in database: $teamCount\n";
if ((int)$teamCount === 0) {
    echo "  NOTE: Tables are empty. You need to import your OOTP database export.\n";
    echo "  Upload your SQL dump and import it via cPanel → phpMyAdmin or:\n";
    echo "    mysql -u $user -p $db < your_ootp_export.sql\n";
}

echo "\n✓ Install complete.\n";
