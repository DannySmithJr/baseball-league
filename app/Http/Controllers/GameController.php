<?php

namespace App\Http\Controllers;

use App\Services\OotpService;

class GameController extends Controller
{
    public function show(int $id, OotpService $ootp)
    {
        $game = $ootp->game($id);
        if (!$game) abort(404);

        $lineScore   = $ootp->gameInningScores($id);
        $boxBatting  = $ootp->gameBoxBatting($id);
        $boxPitching = $ootp->gameBoxPitching($id);
        $atBats      = $ootp->gameAtBats($id, (int) $game->away_team, (int) $game->home_team);

        // For All-Star games, remap at-bat team_ids from real teams to All-Star team IDs
        if (in_array((int)$game->game_type, [3, 4])) {
            $awayId = (int)$game->away_team;
            $homeId = (int)$game->home_team;
            // Map real team sub_league to All-Star side
            $teamSubLeague = [];
            foreach ($ootp->teams() as $t) {
                $teamSubLeague[(int)$t->team_id] = (int)$t->sub_league_id;
            }
            $awaySub = $teamSubLeague[$awayId] ?? 0;
            $homeSub = $teamSubLeague[$homeId] ?? 1;
            foreach ($atBats as &$half) {
                foreach ($half['atbats'] as $ab) {
                    $realSub = $teamSubLeague[(int)$ab->team_id] ?? null;
                    if ($realSub === $awaySub) $ab->team_id = $awayId;
                    elseif ($realSub === $homeSub) $ab->team_id = $homeId;
                }
            }
            unset($half);
        }

        // Team logos (era-accurate)
        $year       = (int) substr($game->date, 0, 4);
        $seasonYear = $ootp->seasonYear() ?? $year;
        $teamLogos  = [];
        foreach ($ootp->teams() as $t) {
            $base = pathinfo($t->logo_file_name ?? '', PATHINFO_FILENAME);
            $teamLogos[(int)$t->team_id] = OotpService::logoForYear($base, $seasonYear);
        }

        // Team W-L records
        $homeAwayRecs = $ootp->teamHomeAwayRecords();

        // Season batting averages for all batters in this box score
        $batterIds = [];
        foreach ($boxBatting as $players) {
            foreach ($players as $p) {
                $batterIds[] = (int)$p->player_id;
            }
        }
        $batSeasonStats = $ootp->playerSeasonBatStats(array_unique($batterIds), $year);

        // Per-player errors this game + season error totals
        $gameLOB        = $ootp->gameLOB($id, (int)$game->away_team, (int)$game->home_team);
        $gameErrorData  = $ootp->gameErrors($id, (int)$game->away_team, (int)$game->home_team);
        $gameDoublePlays = $ootp->gameDoublePlays($id, (int)$game->away_team, (int)$game->home_team);
        $errorPlayerIds = [];
        foreach ($gameErrorData['counts'] as $teamErrors) {
            foreach (array_keys($teamErrors) as $pid) {
                $errorPlayerIds[] = $pid;
            }
        }
        $seasonErrors = $ootp->playerSeasonErrorTotals(array_unique($errorPlayerIds), $year);
        $atBatLogs    = $ootp->gameAtBatLogs($id);

        return view('games.show', compact(
            'game', 'lineScore', 'boxBatting', 'boxPitching', 'atBats',
            'teamLogos', 'homeAwayRecs', 'batSeasonStats',
            'gameErrorData', 'seasonErrors', 'gameLOB', 'gameDoublePlays',
            'atBatLogs'
        ));
    }

    public function logs(int $id, OotpService $ootp)
    {
        $game = $ootp->game($id);
        if (!$game) abort(404);

        $atBats = $ootp->gameAtBats($id, (int) $game->away_team, (int) $game->home_team);

        // For All-Star games, remap at-bat team_ids
        if (in_array((int)$game->game_type, [3, 4])) {
            $awayId = (int)$game->away_team;
            $homeId = (int)$game->home_team;
            $teamSubLeague = [];
            foreach ($ootp->teams() as $t) {
                $teamSubLeague[(int)$t->team_id] = (int)$t->sub_league_id;
            }
            $awaySub = $teamSubLeague[$awayId] ?? 0;
            $homeSub = $teamSubLeague[$homeId] ?? 1;
            foreach ($atBats as &$half) {
                foreach ($half['atbats'] as $ab) {
                    $realSub = $teamSubLeague[(int)$ab->team_id] ?? null;
                    if ($realSub === $awaySub) $ab->team_id = $awayId;
                    elseif ($realSub === $homeSub) $ab->team_id = $homeId;
                }
            }
            unset($half);
        }

        $atBatLogs = $ootp->gameAtBatLogs($id);
        $lineScore = $ootp->gameInningScores($id);

        // Team logos
        $year       = (int) substr($game->date, 0, 4);
        $seasonYear = $ootp->seasonYear() ?? $year;
        $teamLogos  = [];
        foreach ($ootp->teams() as $t) {
            $base = pathinfo($t->logo_file_name ?? '', PATHINFO_FILENAME);
            $teamLogos[(int)$t->team_id] = OotpService::logoForYear($base, $seasonYear);
        }

        $homeAwayRecs = $ootp->teamHomeAwayRecords();

        return view('games.logs', compact(
            'game', 'atBats', 'atBatLogs', 'lineScore',
            'teamLogos', 'homeAwayRecs'
        ));
    }
}
