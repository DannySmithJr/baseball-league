<?php

namespace App\Http\Controllers;

use App\Services\OotpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    public function __invoke(Request $request, OotpService $ootp)
    {
        $teamId = max(0, (int) $request->query('team', 0));
        $date   = $request->query('date', $ootp->gameDate() ?? date('Y-m-d'));
        $cacheKey = "schedule.{$date}.{$teamId}";
        $html = Cache::remember($cacheKey, 3600, fn () => $this->_invoke($request, $ootp)->render());
        return response($html);
    }

    private function _invoke(Request $request, OotpService $ootp)
    {
        $teamId = max(0, (int) $request->query('team', 0));

        $gameDate  = $ootp->gameDate();
        $todayDate = $gameDate ?? date('Y-m-d');
        $date      = $request->query('date', $todayDate);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = $todayDate;
        }

        $prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
        $nextDate = date('Y-m-d', strtotime($date . ' +1 day'));

        $allTeams    = $ootp->teams();
        $currentTeam = $teamId ? $allTeams->firstWhere('team_id', $teamId) : null;

        $games = $ootp->scheduleByDate($date, $teamId);

        // Sort by ET start time
        $tzOffsets = $ootp->teamEtOffsets();
        $games = $games->sortBy(fn ($g) =>
            (int)($g->time ?? 0) + (($tzOffsets[(int)$g->home_team] ?? 0) * 100)
        )->values();

        $year = (int) substr($date, 0, 4);

        // ── Rotation cycling for unplayed games ─────────────────────────────
        $firstUnplayed = DB::table('games')->where('played', 0)->orderBy('date')->value('date');
        $cutoffDate    = $firstUnplayed ? date('Y-m-d', strtotime($firstUnplayed . ' +6 days')) : null;
        $showPitchers  = $firstUnplayed && $date <= $cutoffDate;

        $hasUnplayed = $games->contains(fn ($g) => (int)$g->played === 0);
        if ($hasUnplayed && $showPitchers && $firstUnplayed) {
            // Build rotation from historical starts (same as preview)
            $rotationRows = DB::table('players_game_pitching_stats as pgp')
                ->join('games as g', 'g.game_id', '=', 'pgp.game_id')
                ->join('players as p', 'p.player_id', '=', 'pgp.player_id')
                ->where('g.played', 1)->where('pgp.gs', 1)->where('pgp.level_id', 1)
                ->groupBy('pgp.player_id', 'pgp.team_id', 'p.first_name', 'p.last_name')
                ->selectRaw("pgp.player_id, pgp.team_id,
                    CONCAT(LEFT(p.first_name,1),'. ',p.last_name) as name,
                    MAX(g.date) as last_start")
                ->get();

            $teamRotation = [];
            foreach ($rotationRows->groupBy('team_id') as $tid => $pitchers) {
                $teamRotation[(int)$tid] = $pitchers->sortBy('last_start')->values()
                    ->map(fn($r) => ['player_id' => (int)$r->player_id, 'name' => $r->name])->toArray();
            }

            // Anchor to projected_starting_pitchers.starter_0
            $projected   = DB::table('projected_starting_pitchers')->get()->keyBy('team_id');
            $rotationPos = [];
            foreach ($teamRotation as $tid => $rotation) {
                $nextPid = isset($projected[$tid]) ? (int)$projected[$tid]->starter_0 : null;
                $pos = 0;
                if ($nextPid) {
                    foreach ($rotation as $i => $sp) {
                        if ($sp['player_id'] === $nextPid) { $pos = $i; break; }
                    }
                }
                $rotationPos[$tid] = $pos;
            }

            // Fetch ALL unplayed games from firstUnplayed through the requested date,
            // ordered by date+time, so rotation cycles correctly across days
            $allUnplayed = DB::table('games as g')
                ->leftJoin('teams as at', 'at.team_id', '=', 'g.away_team')
                ->leftJoin('teams as ht', 'ht.team_id', '=', 'g.home_team')
                ->where('g.played', 0)
                ->whereBetween('g.date', [$firstUnplayed, $date])
                ->where('at.level', 1)->where('ht.level', 1)
                ->select('g.game_id', 'g.date', 'g.time', 'g.away_team', 'g.home_team')
                ->orderBy('g.date')->orderBy('g.time')
                ->get();

            // Cycle rotation for each game; collect starters for today's games
            $todayStarters = []; // game_id => [starter0, starter0_name, starter1, starter1_name]
            foreach ($allUnplayed as $ug) {
                $assignments = [];
                foreach ([
                    (int)$ug->away_team => ['starter0', 'starter0_name'],
                    (int)$ug->home_team => ['starter1', 'starter1_name'],
                ] as $tid => [$spField, $nameField]) {
                    if (empty($teamRotation[$tid])) {
                        $assignments[$spField]   = 0;
                        $assignments[$nameField] = null;
                        continue;
                    }
                    $rot = $teamRotation[$tid];
                    $pos = $rotationPos[$tid] ?? 0;
                    $sp  = $rot[$pos % count($rot)];
                    $assignments[$spField]   = $sp['player_id'];
                    $assignments[$nameField] = $sp['name'];
                    $rotationPos[$tid] = ($pos + 1) % count($rot);
                }
                if ($ug->date === $date) {
                    $todayStarters[(int)$ug->game_id] = $assignments;
                }
            }

            // Apply computed starters to today's games
            foreach ($games as $g) {
                if ((int)$g->played === 1) continue;
                $ts = $todayStarters[(int)$g->game_id] ?? null;
                if ($ts) {
                    $g->starter0      = $ts['starter0'];
                    $g->starter0_name = $ts['starter0_name'];
                    $g->starter1      = $ts['starter1'];
                    $g->starter1_name = $ts['starter1_name'];
                }
            }
        } elseif ($hasUnplayed && !$showPitchers) {
            // Beyond 7 days: clear starters so component shows TBD
            foreach ($games as $g) {
                if ((int)$g->played === 1) continue;
                $g->starter0 = 0;
                $g->starter0_name = null;
                $g->starter1 = 0;
                $g->starter1_name = null;
            }
        }

        $starterIds = array_values(array_unique(array_filter(array_merge(
            $games->pluck('starter0')->toArray(),
            $games->pluck('starter1')->toArray(),
            $games->pluck('winning_pitcher')->toArray(),
            $games->pluck('losing_pitcher')->toArray(),
            $games->pluck('save_pitcher')->toArray(),
        ), fn($v) => (int)$v > 0)));

        $starterStats  = $ootp->starterSeasonStats($starterIds, $year);
        $homeAwayRecs  = $ootp->teamHomeAwayRecords();
        $teamTzOffsets = $tzOffsets;

        $seasonYear = $ootp->seasonYear() ?? $year;
        $teamLogos  = [];
        foreach ($ootp->teams() as $t) {
            $base = pathinfo($t->logo_file_name ?? '', PATHINFO_FILENAME);
            $teamLogos[(int)$t->team_id] = OotpService::logoForYear($base, $seasonYear);
        }

        $gameIds = $games->pluck('game_id')->toArray();
        $gameStreams = $gameIds
            ? DB::table('game_streams')->whereIn('game_id', $gameIds)
                ->get()->keyBy('game_id')->map(fn ($s) => [
                    'type'         => $s->stream_type,
                    'scheduled_at' => $s->scheduled_at,
                    'notes'        => $s->notes,
                ])->toArray()
            : [];

        $recentRecs  = $ootp->teamRecentRecords(10);
        $runsPerGame = $ootp->teamRunsPerGame();
        $teamBatting = $ootp->teamBattingStats();
        $bullpenEra  = $ootp->teamBullpenEra();

        // Lineup OPS vs starter handedness
        $starterHands = $ootp->playerThrows($starterIds);
        $lineupHandMap = [];
        foreach ($games as $g) {
            if ((int)$g->played) continue;
            $s1Hand = $starterHands[(int)($g->starter1 ?? 0)] ?? 'R';
            $s0Hand = $starterHands[(int)($g->starter0 ?? 0)] ?? 'R';
            $lineupHandMap[(int)$g->away_team] = $s1Hand === 'R' ? 'rhp' : 'lhp';
            $lineupHandMap[(int)$g->home_team] = $s0Hand === 'R' ? 'rhp' : 'lhp';
        }
        $lineupOps = $ootp->lineupOpsVsHand($lineupHandMap);

        $gameOdds = $ootp->computeGameOdds($games, $homeAwayRecs, $starterStats, $recentRecs, $runsPerGame, $bullpenEra, $lineupOps, $starterHands);

        // Build matchup cards for unplayed games (shared with preview)
        $matchupCards = $ootp->buildMatchupCards($games, $starterStats, $homeAwayRecs, $teamLogos, $teamBatting, $runsPerGame, $gameOdds, $teamTzOffsets, $gameStreams);
        $matchupCardsByGame = [];
        foreach ($matchupCards as $card) $matchupCardsByGame[$card['game_id']] = $card;

        return view('schedule.index', compact(
            'teamId', 'date', 'prevDate', 'nextDate', 'todayDate',
            'games', 'allTeams', 'currentTeam', 'gameDate',
            'starterStats', 'homeAwayRecs', 'teamLogos', 'teamTzOffsets', 'gameStreams', 'gameOdds',
            'runsPerGame', 'teamBatting', 'matchupCardsByGame',
        ));
    }
}
