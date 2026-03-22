<?php

namespace App\Http\Controllers;

use App\Services\OotpService;
use Illuminate\Support\Facades\DB;

class PreviewController extends Controller
{
    public function __construct(private OotpService $ootp) {}

    public function __invoke()
    {
        $firstUpcoming = DB::table('games')->where('played', 0)->orderBy('date')->value('date');
        $lastUpcoming  = date('Y-m-d', strtotime($firstUpcoming . ' +6 days'));
        $lastPlayed    = DB::table('games')->where('played', 1)->orderByDesc('date')->value('date');
        $periodStart   = date('Y-m-d', strtotime($lastPlayed . ' -6 days'));

        // ── Featured matchups (top 12, scored & ranked) ─────────────────
        $featuredMatchups = $this->ootp->featuredMatchups(12);

        // ── Team records for sidebar ────────────────────────────────────
        $teamRecords = [];
        $teamRows = DB::table('teams')->where('level', 1)
            ->get(['team_id','name','nickname','abbr','background_color_id','text_color_id']);

        $teamLogos = DB::table('teams')->whereNotNull('logo_file_name')
            ->pluck('logo_file_name', 'team_id')->map(fn($f) => $f ?: null)->toArray();

        $wlRows = DB::select("
            SELECT team_id, SUM(CASE WHEN won=1 THEN 1 ELSE 0 END) as w, SUM(CASE WHEN won=0 THEN 1 ELSE 0 END) as l
            FROM (
                SELECT away_team AS team_id, IF(runs0>runs1,1,0) AS won FROM games WHERE played=1 AND game_type=0
                UNION ALL
                SELECT home_team AS team_id, IF(runs1>runs0,1,0) AS won FROM games WHERE played=1 AND game_type=0
            ) t GROUP BY team_id
        ");
        $wlByTeam = [];
        foreach ($wlRows as $r) $wlByTeam[(int)$r->team_id] = ['w' => (int)$r->w, 'l' => (int)$r->l];

        foreach ($teamRows as $t) {
            $tid = (int)$t->team_id;
            $wl = $wlByTeam[$tid] ?? ['w'=>0,'l'=>0];
            $teamRecords[$tid] = ['name'=>$t->name, 'nickname'=>$t->nickname, 'abbr'=>$t->abbr, 'w'=>$wl['w'], 'l'=>$wl['l']];
        }

        // ── Team win streaks (sidebar) ──────────────────────────────────
        $recentGamesAll = DB::select("
            SELECT team_id, away_team, home_team, runs0, runs1 FROM (
                SELECT away_team AS team_id, away_team, home_team, runs0, runs1, date, game_id FROM games WHERE played=1 AND game_type=0
                UNION ALL
                SELECT home_team AS team_id, away_team, home_team, runs0, runs1, date, game_id FROM games WHERE played=1 AND game_type=0
            ) t ORDER BY team_id, date DESC, game_id DESC
        ");
        $winStreakByTeam = []; $teamBuf = [];
        foreach ($recentGamesAll as $rg) {
            $tid = (int)$rg->team_id;
            if (isset($winStreakByTeam[$tid])) continue;
            $won = ((int)$rg->away_team === $tid && $rg->runs0 > $rg->runs1) || ((int)$rg->home_team === $tid && $rg->runs1 > $rg->runs0);
            $type = $won ? 'W' : 'L';
            if (!isset($teamBuf[$tid])) { $teamBuf[$tid] = ['type'=>$type,'streak'=>1]; }
            elseif ($type === $teamBuf[$tid]['type']) { $teamBuf[$tid]['streak']++; }
            else { $winStreakByTeam[$tid] = $teamBuf[$tid]['type'] === 'W' ? $teamBuf[$tid]['streak'] : -$teamBuf[$tid]['streak']; }
        }
        foreach ($teamBuf as $tid => $s) { $winStreakByTeam[$tid] ??= ($s['type'] === 'W' ? $s['streak'] : -$s['streak']); }

        $teamStreaks = [];
        foreach ($teamRows as $t) {
            $tid = (int)$t->team_id;
            $ws = $winStreakByTeam[$tid] ?? 0;
            if (abs($ws) >= 3) {
                $wl = $wlByTeam[$tid] ?? ['w'=>0,'l'=>0];
                $teamStreaks[] = [
                    'abbr' => $t->abbr, 'name' => $t->name,
                    'streak' => abs($ws), 'type' => $ws > 0 ? 'W' : 'L',
                    'w' => $wl['w'], 'l' => $wl['l'],
                    'bgColor' => $t->background_color_id ?? '#1f2937',
                    'txColor' => $t->text_color_id ?? '#ffffff',
                    'logo' => $teamLogos[$tid] ?? null,
                ];
            }
        }
        usort($teamStreaks, fn($a, $b) => $b['streak'] <=> $a['streak']);

        // ── Hitting streaks (sidebar) ───────────────────────────────────
        $hittingStreaks = DB::table('players_streak as ps')
            ->join('players as p', 'p.player_id', '=', 'ps.player_id')
            ->join('team_roster as tr', 'tr.player_id', '=', 'p.player_id')
            ->join('teams as t', 't.team_id', '=', 'tr.team_id')
            ->where('ps.streak_id', 9)->where('ps.has_ended', 0)->where('ps.value', '>=', 5)->where('t.level', 1)
            ->orderByDesc('ps.value')
            ->get(['p.player_id','p.first_name','p.last_name','tr.team_id','t.abbr','ps.value','ps.started'])
            ->unique('player_id')->take(15);

        // ── Batter Watch — last 7 days ──────────────────────────────────
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

        // ── Pitcher Watch — last 7 days ─────────────────────────────────
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
