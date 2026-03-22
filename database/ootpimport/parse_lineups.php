<?php
/**
 * Parse starting lineups and pitching staff from OOTP almanac HTML team pages.
 *
 * On dev: auto-detects OOTP almanac directory (newest almanac_YYYY).
 * On live: reads from public/reports/teams/
 *
 * Generates:
 *   - team_starting_lineups.sql
 *   - team_pitching_staff.sql
 *
 * Usage: php parse_lineups.php
 */

$reportsDir = null;
$lineupFile = __DIR__ . '/team_starting_lineups.sql';
$staffFile  = __DIR__ . '/team_pitching_staff.sql';

// Dev: auto-detect from OOTP saved game directory
$ootpBase = 'C:/Users/danny/Documents/Out of the Park Developments/OOTP Baseball 27/saved_games/sMLB.lg/news';
if (is_dir($ootpBase)) {
    // Find newest almanac_YYYY directory
    $almanacs = glob($ootpBase . '/almanac_*', GLOB_ONLYDIR);
    if (!empty($almanacs)) {
        usort($almanacs, fn($a, $b) => basename($b) <=> basename($a)); // newest first
        $reportsDir = $almanacs[0] . '/teams';
        echo "Using OOTP almanac: " . basename($almanacs[0]) . "\n";
    }
}

// Fallback: public/reports/teams/
if (!$reportsDir || !is_dir($reportsDir)) {
    $reportsDir = __DIR__ . '/../../public/reports/teams';
}

if (!is_dir($reportsDir)) {
    echo "Reports directory not found.\n";
    echo "Expected OOTP at: {$ootpBase}/almanac_YYYY/teams/\n";
    echo "Or: public/reports/teams/\n";
    exit(1);
}

echo "Reading from: {$reportsDir}\n";

$files = glob($reportsDir . '/team_[0-9]*.html');
$files = array_filter($files, fn($f) => preg_match('/team_\d+\.html$/', basename($f)));

if (empty($files)) {
    echo "No team HTML files found in {$reportsDir}\n";
    exit(1);
}

$lineupRows = [];
$staffRows  = [];
$teamCount  = 0;

foreach ($files as $file) {
    if (!preg_match('/team_(\d+)\.html$/', basename($file), $m)) continue;
    $teamId = (int)$m[1];
    $teamCount++;

    $html = file_get_contents($file);

    // --- Starting Lineups (vs RHP / vs LHP) ---
    foreach (['RHP' => 'rhp', 'LHP' => 'lhp'] as $label => $vs) {
        $pattern = '/LINEUP VS ' . $label . '<\/th>.*?<\/table>\s*<table[^>]*>(.*?)<\/table>/si';
        if (!preg_match($pattern, $html, $tm)) continue;

        preg_match_all(
            '/<tr>\s*<td[^>]*>(\d+)<\/td>\s*<td[^>]*>([^<]*)<\/td>\s*<td[^>]*><a href="[^"]*player_(\d*)\.[^"]*">([^<]*)<\/a><\/td>\s*<td[^>]*>([^<]*)<\/td>/si',
            $tm[1], $matches, PREG_SET_ORDER
        );

        foreach ($matches as $row) {
            $slot     = (int)$row[1];
            $playerId = (int)$row[3];
            $bats     = addslashes(trim($row[2]));
            $position = addslashes(trim($row[5]));

            if ($playerId === 0 || $position === '') continue;

            $lineupRows[] = "({$teamId},'{$vs}',{$slot},{$playerId},'{$bats}','{$position}')";
        }
    }

    // --- Pitching Staff ---
    $pattern = '/PITCHING STAFF<\/th>.*?<\/table>\s*<table[^>]*>(.*?)<\/table>/si';
    if (preg_match($pattern, $html, $pm)) {
        preg_match_all('/<tr>(.*?)<\/tr>/si', $pm[1], $rows);
        $order = 0;
        foreach ($rows[1] as $rowHtml) {
            // Role | Throws | Player link | stats...
            if (!preg_match('/<td[^>]*>([^<]+)<\/td>\s*<td[^>]*>([^<]*)<\/td>\s*<td[^>]*><a href="[^"]*player_(\d+)\.[^"]*">/si', $rowHtml, $rm)) continue;

            $role     = addslashes(trim($rm[1]));
            $throws   = addslashes(trim($rm[2]));
            $playerId = (int)$rm[3];
            $order++;

            $staffRows[] = "({$teamId},{$playerId},'{$role}','{$throws}',{$order})";
        }
    }
}

// Write lineup SQL
if (!empty($lineupRows)) {
    $sql = "INSERT INTO `team_starting_lineups` VALUES " . implode(",", $lineupRows) . ";\n";
    file_put_contents($lineupFile, $sql);
    echo "Lineups: " . count($lineupRows) . " slots from {$teamCount} teams -> {$lineupFile}\n";
} else {
    echo "No lineup data found.\n";
}

// Write pitching staff SQL
if (!empty($staffRows)) {
    $sql = "INSERT INTO `team_pitching_staff` VALUES " . implode(",", $staffRows) . ";\n";
    file_put_contents($staffFile, $sql);
    echo "Pitching: " . count($staffRows) . " staff from {$teamCount} teams -> {$staffFile}\n";
} else {
    echo "No pitching staff data found.\n";
}
