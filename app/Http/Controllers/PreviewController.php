<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class PreviewController extends Controller
{
    public function __invoke()
    {
        // ── Date range ────────────────────────────────────────────────────────
        $firstUpcoming = DB::table('games')->where('played', 0)->orderBy('date')->value('date');
        $lastUpcoming  = date('Y-m-d', strtotime($firstUpcoming . ' +6 days'));
        $lastPlayed    = DB::table('games')->where('played', 1)->orderByDesc('date')->value('date');
        $periodStart   = date('Y-m-d', strtotime($lastPlayed . ' -6 days'));

        // ── Team logos ───────────────────────────────────────────────────────
        $teamLogos = DB::table('teams')
            ->whereNotNull('logo_file_name')
            ->pluck('logo_file_name', 'team_id')
            ->map(fn($f) => $f ?: null)
            ->toArray();

        // ── Team records (W/L) + division ────────────────────────────────────
        $teamRecords = [];
        $teamRows = DB::table('teams')->where('level', 1)
            ->get(['team_id','name','nickname','abbr','division_id','sub_league_id','background_color_id','text_color_id']);

        // Bulk: W/L records — single query instead of 2 per team
        $wlRows = DB::select("
            SELECT team_id,
                SUM(CASE WHEN won=1 THEN 1 ELSE 0 END) as w,
                SUM(CASE WHEN won=0 THEN 1 ELSE 0 END) as l
            FROM (
                SELECT away_team AS team_id, IF(runs0>runs1,1,0) AS won
                FROM games WHERE played=1 AND game_type=0
                UNION ALL
                SELECT home_team AS team_id, IF(runs1>runs0,1,0) AS won
                FROM games WHERE played=1 AND game_type=0
            ) t GROUP BY team_id
        ");
        $wlByTeam = [];
        foreach ($wlRows as $r) {
            $wlByTeam[(int)$r->team_id] = ['w' => (int)$r->w, 'l' => (int)$r->l];
        }

        // Bulk: runs per game — reuse game rows from wlRows source
        $rpgRows = DB::select("
            SELECT team_id, SUM(rf) as rf, SUM(g) as g FROM (
                SELECT away_team AS team_id, SUM(runs0) AS rf, COUNT(*) AS g
                FROM games WHERE played=1 AND game_type=0 GROUP BY away_team
                UNION ALL
                SELECT home_team AS team_id, SUM(runs1) AS rf, COUNT(*) AS g
                FROM games WHERE played=1 AND game_type=0 GROUP BY home_team
            ) t GROUP BY team_id
        ");
        $rpgByTeam = [];
        foreach ($rpgRows as $r) {
            $g = (int)$r->g;
            $rpgByTeam[(int)$r->team_id] = $g > 0 ? round((float)$r->rf / $g, 1) : 0.0;
        }

        // Bulk: team batting avg + HR
        $teamBatting = DB::table('players_game_batting as pgb')
            ->join('games as g', 'g.game_id', '=', 'pgb.game_id')
            ->where('g.played', 1)->where('pgb.level_id', 1)
            ->groupBy('pgb.team_id')
            ->selectRaw('pgb.team_id, SUM(pgb.h) as h, SUM(pgb.ab) as ab, SUM(pgb.hr) as hr')
            ->get()->keyBy('team_id');

        // Bulk: team ERA
        $teamPitching = DB::table('players_game_pitching_stats as pgp')
            ->join('games as g', 'g.game_id', '=', 'pgp.game_id')
            ->where('g.played', 1)->where('pgp.level_id', 1)
            ->groupBy('pgp.team_id')
            ->selectRaw('pgp.team_id, SUM(pgp.er) as er, SUM(pgp.outs) as outs')
            ->get()->keyBy('team_id');

        foreach ($teamRows as $t) {
            $tid = (int)$t->team_id;
            $wl  = $wlByTeam[$tid] ?? ['w' => 0, 'l' => 0];
            $w   = $wl['w'];
            $l   = $wl['l'];
            $bat = $teamBatting[$tid]  ?? null;
            $pit = $teamPitching[$tid] ?? null;
            $teamRecords[$tid] = [
                'name'          => $t->name,
                'nickname'      => $t->nickname,
                'abbr'          => $t->abbr,
                'w'             => $w,
                'l'             => $l,
                'pct'           => ($w + $l) > 0 ? $w / ($w + $l) : 0,
                'division_id'   => (int)$t->division_id,
                'sub_league_id' => (int)$t->sub_league_id,
                'bgColor'       => $t->background_color_id ?? '#1f2937',
                'txColor'       => $t->text_color_id       ?? '#ffffff',
                'logo'          => $teamLogos[$tid] ?? null,
                'rpg'           => $rpgByTeam[$tid] ?? 0.0,
                'teamAvg'       => ($bat && $bat->ab > 0) ? $bat->h / $bat->ab : 0.0,
                'teamHr'        => $bat ? (int)$bat->hr : 0,
                'teamEra'       => ($pit && $pit->outs > 0) ? ($pit->er * 27) / $pit->outs : 0.0,
            ];
        }

        // ── Upcoming games — direct 7-day query ──────────────────────────────
        $upcomingGames = DB::table('games as g')
            ->leftJoin('teams as at', 'at.team_id', '=', 'g.away_team')
            ->leftJoin('teams as ht', 'ht.team_id', '=', 'g.home_team')
            ->where('g.played', 0)
            ->whereBetween('g.date', [$firstUpcoming, $lastUpcoming])
            ->select(
                'g.game_id', 'g.date', 'g.time', 'g.away_team', 'g.home_team', 'g.played',
                'at.abbr as away_abbr', 'ht.abbr as home_abbr'
            )
            ->orderBy('g.date')->orderBy('g.time')
            ->get()
            ->map(fn($g) => (object)((array)$g + ['starter0' => 0, 'starter1' => 0, 'starter0_name' => null, 'starter1_name' => null]));

        // ── Build pitching rotation for each team ─────────────────────────────
        // Get each pitcher's last start date per team; sort oldest→newest = rotation order.
        $rotationRows = DB::table('players_game_pitching_stats as pgp')
            ->join('games as g', 'g.game_id', '=', 'pgp.game_id')
            ->join('players as p', 'p.player_id', '=', 'pgp.player_id')
            ->where('g.played', 1)
            ->where('pgp.gs', 1)
            ->where('pgp.level_id', 1)
            ->groupBy('pgp.player_id', 'pgp.team_id', 'p.first_name', 'p.last_name')
            ->selectRaw("pgp.player_id, pgp.team_id,
                         CONCAT(LEFT(p.first_name,1),'. ',p.last_name) as name,
                         MAX(g.date) as last_start")
            ->get();

        $teamRotation = [];
        foreach ($rotationRows->groupBy('team_id') as $tid => $pitchers) {
            $teamRotation[(int)$tid] = $pitchers
                ->sortBy('last_start')          // oldest last start = next in cycle
                ->values()
                ->map(fn($r) => ['player_id' => (int)$r->player_id, 'name' => $r->name])
                ->toArray();
        }

        // Anchor rotation to projected_starting_pitchers.starter_0 (who starts game 1)
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

        // Assign starters to each upcoming game in date order, cycling through rotation
        foreach ($upcomingGames as $g) {
            foreach ([
                (int)$g->away_team => ['starter0', 'starter0_name'],
                (int)$g->home_team => ['starter1', 'starter1_name'],
            ] as $tid => [$spField, $nameField]) {
                if (empty($teamRotation[$tid])) continue;
                $rot  = $teamRotation[$tid];
                $pos  = $rotationPos[$tid] ?? 0;
                $sp   = $rot[$pos % count($rot)];
                $g->$spField   = $sp['player_id'];
                $g->$nameField = $sp['name'];
                $rotationPos[$tid] = ($pos + 1) % count($rot);
            }
        }

        // ── Starter season stats (from game logs — same source as pitcherWatch) ─
        $starterIds = array_values(array_unique(array_filter(array_merge(
            $upcomingGames->pluck('starter0')->toArray(),
            $upcomingGames->pluck('starter1')->toArray(),
        ), fn($v) => (int)$v > 0)));

        $starterStats = [];
        if ($starterIds) {
            $spRows = DB::table('players_game_pitching_stats as pgp')
                ->join('games as g', 'g.game_id', '=', 'pgp.game_id')
                ->whereIn('pgp.player_id', $starterIds)
                ->where('g.played', 1)
                ->where('pgp.level_id', 1)
                ->groupBy('pgp.player_id')
                ->selectRaw('pgp.player_id,
                    SUM(pgp.w) as w, SUM(pgp.l) as l, SUM(pgp.k) as k,
                    SUM(pgp.outs) as outs, SUM(pgp.er) as er,
                    SUM(pgp.ha) as ha, SUM(pgp.bb) as bb')
                ->get();
            foreach ($spRows as $r) {
                $ip = $r->outs / 3;
                $starterStats[(int)$r->player_id] = [
                    'w'    => (int)$r->w,
                    'l'    => (int)$r->l,
                    'k'    => (int)$r->k,
                    'era'  => $ip > 0 ? number_format(($r->er / $ip) * 9, 2) : '-.--',
                    'whip' => $ip > 0 ? number_format(($r->ha + $r->bb) / $ip, 2) : '-.--',
                ];
            }
        }

        // ── Active hitting streaks (single query for both scoring + sidebar) ─
        $allStreakRows = DB::table('players_streak as ps')
            ->join('players as p', 'p.player_id', '=', 'ps.player_id')
            ->join('team_roster as tr', 'tr.player_id', '=', 'p.player_id')
            ->join('teams as t', 't.team_id', '=', 'tr.team_id')
            ->where('ps.streak_id', 9)
            ->where('ps.has_ended', 0)
            ->where('ps.value', '>=', 5)
            ->where('t.level', 1)
            ->orderByDesc('ps.value')
            ->get(['p.player_id','p.first_name','p.last_name','tr.team_id','t.abbr','ps.value','ps.started'])
            ->unique('player_id');

        $streaksByTeam = [];   // team_id → [{name, value}]  (threshold ≥ 8 for scoring)
        foreach ($allStreakRows as $sr) {
            if ((int)$sr->value >= 8) {
                $tid = (int)$sr->team_id;
                $streaksByTeam[$tid][] = [
                    'player_id' => (int)$sr->player_id,
                    'name'      => $sr->first_name . ' ' . $sr->last_name,
                    'value'     => (int)$sr->value,
                ];
            }
        }

        // ── Season leaders (HR, RBI, AVG) — for star-power scoring ──────────
        $seasonStats = DB::table('players_career_batting_stats as pcs')
            ->join('players as p', 'p.player_id', '=', 'pcs.player_id')
            ->join('team_roster as tr', 'tr.player_id', '=', 'p.player_id')
            ->join('teams as t', 't.team_id', '=', 'tr.team_id')
            ->where('pcs.split_id', 1)
            ->where('pcs.year', (int)date('Y', strtotime($firstUpcoming)))
            ->where('t.level', 1)
            ->where('pcs.ab', '>=', 100)
            ->get(['p.player_id','p.first_name','p.last_name','tr.team_id','pcs.hr','pcs.rbi','pcs.ab','pcs.h'])
            ->unique('player_id');

        // Rank players: top 5 HR and top 5 RBI get bonus points
        $hrRanks  = $seasonStats->sortByDesc('hr')->values()->take(5)->pluck('player_id')->flip()->toArray();
        $rbiRanks = $seasonStats->sortByDesc('rbi')->values()->take(5)->pluck('player_id')->flip()->toArray();

        // Star players per team: player_id list
        $starsByTeam = []; // team_id → [{name, label}]
        foreach ($seasonStats as $s) {
            $tid = (int)$s->team_id;
            $labels = [];
            if (isset($hrRanks[$s->player_id]))  $labels[] = 'Top-5 HR (' . $s->hr . ')';
            if (isset($rbiRanks[$s->player_id])) $labels[] = 'Top-5 RBI (' . $s->rbi . ')';
            if (!empty($labels)) {
                $starsByTeam[$tid][] = [
                    'player_id' => (int)$s->player_id,
                    'name'      => $s->first_name . ' ' . $s->last_name,
                    'labels'    => $labels,
                ];
            }
        }

        // ── Team win streaks — bulk query, process in PHP ────────────────────
        // Pull recent games for all teams at once (one query vs N queries)
        $recentGamesAll = DB::select("
            SELECT team_id, away_team, home_team, runs0, runs1, date, game_id
            FROM (
                SELECT away_team AS team_id, away_team, home_team, runs0, runs1, date, game_id
                FROM games WHERE played=1 AND game_type=0
                UNION ALL
                SELECT home_team AS team_id, away_team, home_team, runs0, runs1, date, game_id
                FROM games WHERE played=1 AND game_type=0
            ) t
            ORDER BY team_id, date DESC, game_id DESC
        ");

        // Rows arrive grouped by team_id, most-recent-first. Track streak type & count.
        $winStreakByTeam = [];
        $teamBuf = []; // team_id → ['type' => 'W'|'L', 'streak' => int]
        foreach ($recentGamesAll as $g) {
            $tid = (int)$g->team_id;
            if (isset($winStreakByTeam[$tid])) continue;
            $won  = ((int)$g->away_team === $tid && $g->runs0 > $g->runs1)
                 || ((int)$g->home_team === $tid && $g->runs1 > $g->runs0);
            $type = $won ? 'W' : 'L';
            if (!isset($teamBuf[$tid])) {
                $teamBuf[$tid] = ['type' => $type, 'streak' => 1];
            } elseif ($type === $teamBuf[$tid]['type']) {
                $teamBuf[$tid]['streak']++;
                if ($teamBuf[$tid]['streak'] >= 15) {
                    $s = $teamBuf[$tid];
                    $winStreakByTeam[$tid] = $s['type'] === 'W' ? $s['streak'] : -$s['streak'];
                }
            } else {
                $s = $teamBuf[$tid];
                $winStreakByTeam[$tid] = $s['type'] === 'W' ? $s['streak'] : -$s['streak'];
            }
        }
        // Flush teams whose streak never broke (all games same result, or < 15 games)
        foreach ($teamBuf as $tid => $s) {
            $winStreakByTeam[$tid] ??= ($s['type'] === 'W' ? $s['streak'] : -$s['streak']);
        }
        foreach ($teamRows as $t) {
            $winStreakByTeam[(int)$t->team_id] ??= 0;
        }

        // ── Rivalries ───────────────────────────────────────────────────────
        $rivalryPairs = [];
        $rivalryRows = DB::table('rivalries')->where('approved', true)->get();
        foreach ($rivalryRows as $rv) {
            $a = (int)$rv->team0_id;
            $b = (int)$rv->team1_id;
            $key = min($a, $b) . '-' . max($a, $b);
            $rivalryPairs[$key] = true;
        }

        // ── Featured matchup scoring algorithm (individual games) ────────────
        //
        // Score components (0–1 each, then weighted):
        //   closeness   (25%) — how even the records are
        //   quality     (20%) — combined wins of both teams
        //   pitching    (15%) — starter ERA quality
        //   star_power  (10%) — HR/RBI leaders on either team
        //   rivalry     (15%) — approved rivalry matchup
        //   streak_watch(10%) — long hitting streaks on either team
        //   momentum    ( 5%) — one team hot, other cold (drama)
        //
        $maxWins = max(array_column($teamRecords, 'w')) * 2 ?: 1;

        $featuredMatchups = [];
        foreach ($upcomingGames as $g) {
            $awayId = (int)$g->away_team;
            $homeId = (int)$g->home_team;
            $away   = $teamRecords[$awayId] ?? null;
            $home   = $teamRecords[$homeId] ?? null;
            if (!$away || !$home) continue;

            // 1. Closeness: 1 = identical records, 0 = 0.500 apart
            $pctDiff   = abs($away['pct'] - $home['pct']);
            $closeness = max(0, 1 - ($pctDiff * 4));

            // 2. Quality: combined wins normalised
            $quality = ($away['w'] + $home['w']) / $maxWins;

            // 3. Star power: top-5 HR or RBI leader on either team
            $starScore = 0;
            foreach ([$awayId, $homeId] as $tid) {
                foreach ($starsByTeam[$tid] ?? [] as $star) {
                    $starScore += 0.4;
                }
            }
            $starScore = min(1, $starScore);

            // 4. Streak watch: long hitting streaks
            $streakScore = 0;
            foreach ([$awayId, $homeId] as $tid) {
                foreach ($streaksByTeam[$tid] ?? [] as $str) {
                    $streakScore += min(1, $str['value'] / 30) * 0.5;
                }
            }
            $streakScore = min(1, $streakScore);

            // 5. Momentum drama: one team on win streak, other on loss streak
            $awayMom  = $winStreakByTeam[$awayId] ?? 0;
            $homeMom  = $winStreakByTeam[$homeId] ?? 0;
            $momentum = (($awayMom > 0 && $homeMom < 0) || ($awayMom < 0 && $homeMom > 0))
                ? min(1, (abs($awayMom) + abs($homeMom)) / 10)
                : 0;

            // 6. Pitching quality: average starter ERA (ERA 1.5→1.0, ERA 5.5+→0.0)
            $awaySp   = (int)$g->starter0 > 0 ? ($starterStats[(int)$g->starter0] ?? null) : null;
            $homeSp   = (int)$g->starter1 > 0 ? ($starterStats[(int)$g->starter1] ?? null) : null;
            $pitchScore = 0.5; // neutral if no starters known
            $eraVals = array_filter([
                $awaySp && is_numeric($awaySp['era']) ? (float)$awaySp['era'] : null,
                $homeSp && is_numeric($homeSp['era']) ? (float)$homeSp['era'] : null,
            ], fn($v) => $v !== null);
            if ($eraVals) {
                $avgEra = array_sum($eraVals) / count($eraVals);
                $pitchScore = max(0, min(1, (5.5 - $avgEra) / 4.0));
            }

            // 7. Rivalry: approved rivalry matchup
            $rivalryKey   = min($awayId, $homeId) . '-' . max($awayId, $homeId);
            $isRivalry    = isset($rivalryPairs[$rivalryKey]);
            $rivalryScore = $isRivalry ? 1.0 : 0.0;

            // Weighted total
            $score = ($closeness    * 0.25)
                   + ($quality      * 0.20)
                   + ($pitchScore   * 0.15)
                   + ($rivalryScore * 0.15)
                   + ($starScore    * 0.10)
                   + ($streakScore  * 0.10)
                   + ($momentum    * 0.05);

            // Build storyline tags
            $tags = [];
            if ($isRivalry) $tags[] = ['type' => 'rivalry', 'text' => 'Rivalry'];
            if ($pctDiff <= 0.05) $tags[] = ['type' => 'matchup',  'text' => 'Even matchup'];
            if ($away['pct'] >= 0.580 && $home['pct'] >= 0.580) $tags[] = ['type' => 'elite', 'text' => 'Elite vs Elite'];
            if ($away['division_id'] === $home['division_id'])   $tags[] = ['type' => 'div',   'text' => 'Division game'];
            foreach ([$awayId, $homeId] as $tid) {
                foreach ($streaksByTeam[$tid] ?? [] as $str) {
                    if ($str['value'] >= 15) {
                        $tags[] = ['type' => 'streak', 'text' => $str['name'] . ' — ' . $str['value'] . '-game hit streak'];
                    }
                }
                foreach ($starsByTeam[$tid] ?? [] as $star) {
                    $tags[] = ['type' => 'star', 'text' => $star['name'] . ' — ' . implode(', ', $star['labels'])];
                }
            }
            if (abs($awayMom) >= 3) $tags[] = ['type' => 'momentum', 'text' => $away['abbr'] . ' ' . ($awayMom > 0 ? 'W' : 'L') . abs($awayMom)];
            if (abs($homeMom) >= 3) $tags[] = ['type' => 'momentum', 'text' => $home['abbr'] . ' ' . ($homeMom > 0 ? 'W' : 'L') . abs($homeMom)];

            $featuredMatchups[] = [
                'game_id'     => (int)$g->game_id,
                'date'        => $g->date,
                'away'        => $awayId,
                'home'        => $homeId,
                'score'       => $score,
                'tags'        => $tags,
                'awayMom'     => $awayMom,
                'homeMom'     => $homeMom,
                'awaySpName'  => $g->starter0_name,
                'awaySpStats' => $awaySp,
                'homeSpName'  => $g->starter1_name,
                'homeSpStats' => $homeSp,
            ];
        }

        // Sort by score, take top 12
        usort($featuredMatchups, fn($a, $b) => $b['score'] <=> $a['score']);
        $featuredMatchups = array_slice($featuredMatchups, 0, 12);

        // ── Team streaks (sidebar) ────────────────────────────────────────────
        $teamStreaks = [];
        foreach ($teamRows as $t) {
            $tid = (int)$t->team_id;
            $ws  = $winStreakByTeam[$tid] ?? 0;
            if (abs($ws) >= 3) {
                $teamStreaks[] = [
                    'abbr'    => $t->abbr,
                    'name'    => $t->name,
                    'streak'  => abs($ws),
                    'type'    => $ws > 0 ? 'W' : 'L',
                    'w'       => $teamRecords[$tid]['w'],
                    'l'       => $teamRecords[$tid]['l'],
                    'bgColor' => $t->background_color_id ?? '#1f2937',
                    'txColor' => $t->text_color_id       ?? '#ffffff',
                    'logo'    => $teamLogos[$tid] ?? null,
                ];
            }
        }
        usort($teamStreaks, fn($a, $b) => $b['streak'] <=> $a['streak']);

        // ── Hitting streaks (sidebar) — reuse allStreakRows (≥5 already) ────
        $hittingStreaks = $allStreakRows->take(15);

        // ── Batter Watch — last 7 days (single query, split in PHP) ─────────
        $allBatters = DB::table('players_game_batting as pgb')
            ->join('games as g', 'g.game_id', '=', 'pgb.game_id')
            ->join('players as p', 'p.player_id', '=', 'pgb.player_id')
            ->join('teams as t', 't.team_id', '=', 'pgb.team_id')
            ->where('g.played', 1)->where('g.date', '>=', $periodStart)->where('g.date', '<=', $lastPlayed)
            ->where('pgb.level_id', 1)
            ->groupBy('pgb.player_id', 'p.first_name', 'p.last_name', 't.abbr')
            ->selectRaw('pgb.player_id, p.first_name, p.last_name, t.abbr,
                COUNT(DISTINCT g.game_id) as gp, SUM(pgb.ab) as ab, SUM(pgb.h) as h,
                SUM(pgb.hr) as hr, SUM(pgb.bb) as bb, SUM(pgb.rbi) as rbi, SUM(pgb.sb) as sb,
                SUM(pgb.hp) as hp, SUM(pgb.pa) as pa, SUM(pgb.d) as d, SUM(pgb.t) as t,
                ROUND(SUM(pgb.h)/NULLIF(SUM(pgb.ab),0),3) as avg,
                ROUND((SUM(pgb.h)+SUM(pgb.bb)+SUM(pgb.hp))/NULLIF(SUM(pgb.pa),0),3) as obp,
                ROUND((SUM(pgb.h)+SUM(pgb.d)+2*SUM(pgb.t)+3*SUM(pgb.hr))/NULLIF(SUM(pgb.ab),0),3) as slg')
            ->havingRaw('SUM(pgb.ab) >= 15')
            ->get();

        $hotBatters  = $allBatters->sortByDesc('avg')->take(10)->values();
        $coldBatters = $allBatters->filter(fn($b) => $b->ab >= 18)->sortBy('avg')->take(10)->values();

        // ── Pitcher Watch — last 7 days ───────────────────────────────────────
        $pitcherWatch = DB::table('players_game_pitching_stats as pgp')
            ->join('games as g', 'g.game_id', '=', 'pgp.game_id')
            ->join('players as p', 'p.player_id', '=', 'pgp.player_id')
            ->join('teams as t', 't.team_id', '=', 'pgp.team_id')
            ->where('g.played', 1)->where('g.date', '>=', $periodStart)->where('g.date', '<=', $lastPlayed)
            ->where('pgp.level_id', 1)->where('pgp.gs', '>', 0)
            ->groupBy('pgp.player_id', 'p.first_name', 'p.last_name', 't.abbr')
            ->selectRaw('pgp.player_id, p.first_name, p.last_name, t.abbr,
                COUNT(DISTINCT g.game_id) as gs, SUM(pgp.outs) as total_outs,
                SUM(pgp.ha) as h, SUM(pgp.r) as r, SUM(pgp.er) as er,
                SUM(pgp.bb) as bb, SUM(pgp.k) as k, SUM(pgp.hra) as hr,
                ROUND(SUM(pgp.er)*27/NULLIF(SUM(pgp.outs),0),2) as era')
            ->havingRaw('SUM(pgp.outs) >= 9')
            ->orderByRaw('SUM(pgp.er)*27/NULLIF(SUM(pgp.outs),0) ASC')
            ->limit(10)->get();

        return view('preview.index', compact(
            'firstUpcoming', 'lastUpcoming', 'lastPlayed', 'periodStart',
            'teamRecords', 'teamStreaks', 'featuredMatchups',
            'hittingStreaks', 'hotBatters', 'coldBatters', 'pitcherWatch'
        ));
    }
}
