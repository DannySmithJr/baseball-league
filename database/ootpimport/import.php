#!/usr/bin/env php
<?php
/**
 * Import all OOTP SQL dumps in this directory into the database.
 *
 * Usage (from project root):
 *   php database/ootpimport/import.php
 *
 * Reads .env for DB credentials, then imports each .sql file.
 * Large files are streamed line-by-line to avoid memory issues.
 */

$envPath = __DIR__ . '/../../.env';
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

echo "Importing into: $db @ $host:$port\n\n";

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    die("ERROR: " . $e->getMessage() . "\n");
}

$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
$pdo->exec("SET UNIQUE_CHECKS=0");
$pdo->exec("SET AUTOCOMMIT=0");

$files = glob(__DIR__ . '/*.sql');
sort($files);

$total   = count($files);
$current = 0;
$errors  = 0;

foreach ($files as $file) {
    $current++;
    $table = basename($file, '.sql');
    $size  = filesize($file);
    $sizeH = $size > 1048576 ? round($size / 1048576, 1) . 'MB' : round($size / 1024) . 'KB';

    echo sprintf("[%d/%d] %-45s %8s ... ", $current, $total, $table, $sizeH);

    // Truncate existing data first
    try {
        $pdo->exec("TRUNCATE TABLE `$table`");
    } catch (PDOException $e) {
        // Table might not exist yet
        echo "SKIP (table missing)\n";
        $errors++;
        continue;
    }

    // For small files, exec directly
    if ($size < 5 * 1024 * 1024) {
        try {
            $sql = file_get_contents($file);
            $pdo->exec($sql);
            $pdo->exec("COMMIT");
            echo "OK\n";
        } catch (PDOException $e) {
            echo "ERROR: " . substr($e->getMessage(), 0, 80) . "\n";
            $errors++;
        }
    } else {
        // Stream large files line by line, collecting complete statements
        $handle = fopen($file, 'r');
        $stmt = '';
        $stmtCount = 0;

        while (($line = fgets($handle)) !== false) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
                continue;
            }
            $stmt .= $line;
            if (str_ends_with($trimmed, ';')) {
                try {
                    $pdo->exec($stmt);
                    $stmtCount++;
                } catch (PDOException $e) {
                    // Continue on individual statement errors
                }
                $stmt = '';

                // Commit every 100 statements for large files
                if ($stmtCount % 100 === 0) {
                    $pdo->exec("COMMIT");
                }
            }
        }
        fclose($handle);
        $pdo->exec("COMMIT");
        echo "OK ($stmtCount stmts)\n";
    }
}

$pdo->exec("SET FOREIGN_KEY_CHECKS=1");
$pdo->exec("SET UNIQUE_CHECKS=1");
$pdo->exec("SET AUTOCOMMIT=1");

echo "\n✓ Import complete. $total files processed" . ($errors ? ", $errors errors" : "") . ".\n";
