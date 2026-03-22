<?php
/**
 * Dump team_starting_lineups and team_pitching_staff tables
 * to SQL files in database/migrations/ for live deploy.
 *
 * Usage: php database/ootpimport/dump_lineups.php
 * Run AFTER parse_lineups.php + import_lineups.php
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$migrationsDir = __DIR__ . '/../migrations';

// Dump team_starting_lineups
$rows = DB::table('team_starting_lineups')->get();
$vals = [];
foreach ($rows as $r) {
    $vals[] = "({$r->team_id},'" . addslashes($r->vs) . "',{$r->slot},{$r->player_id},'" . addslashes($r->bats) . "','" . addslashes($r->position) . "')";
}
$sql  = "DROP TABLE IF EXISTS `team_starting_lineups`;\n";
$sql .= "CREATE TABLE `team_starting_lineups` (`team_id` int DEFAULT NULL, `vs` varchar(3) DEFAULT NULL, `slot` smallint DEFAULT NULL, `player_id` int DEFAULT NULL, `bats` varchar(1) DEFAULT NULL, `position` varchar(4) DEFAULT NULL) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n";
if ($vals) $sql .= "INSERT INTO `team_starting_lineups` VALUES " . implode(",", $vals) . ";\n";
file_put_contents($migrationsDir . '/team_starting_lineups.sql', $sql);
echo "Lineups: " . count($vals) . " rows -> database/migrations/team_starting_lineups.sql\n";

// Dump team_pitching_staff
$rows = DB::table('team_pitching_staff')->get();
$vals = [];
foreach ($rows as $r) {
    $vals[] = "({$r->team_id},{$r->player_id},'" . addslashes($r->role) . "','" . addslashes($r->throws) . "',{$r->sort_order})";
}
$sql  = "DROP TABLE IF EXISTS `team_pitching_staff`;\n";
$sql .= "CREATE TABLE `team_pitching_staff` (`team_id` int DEFAULT NULL, `player_id` int DEFAULT NULL, `role` varchar(20) DEFAULT NULL, `throws` varchar(1) DEFAULT NULL, `sort_order` smallint DEFAULT NULL) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;\n";
if ($vals) $sql .= "INSERT INTO `team_pitching_staff` VALUES " . implode(",", $vals) . ";\n";
file_put_contents($migrationsDir . '/team_pitching_staff.sql', $sql);
echo "Staff: " . count($vals) . " rows -> database/migrations/team_pitching_staff.sql\n";

echo "Done. Commit and push to deploy to live.\n";
