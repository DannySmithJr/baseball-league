<?php

namespace App\Http\Controllers;

use App\Services\OotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TeamController extends Controller
{
    public function __construct(private OotpService $ootp) {}

    // -------------------------------------------------------------------------
    // Teams list
    // -------------------------------------------------------------------------

    public function index()
    {
        $standings = collect($this->ootp->standingsByDivision());

        return view('teams.index', [
            'standingsByDivision' => $standings,
        ]);
    }

    // -------------------------------------------------------------------------
    // Team home — roster + recent games + leaders
    // -------------------------------------------------------------------------

    public function show(int $id)
    {
        $team = $this->ootp->teamWithDetails($id);
        if (!$team) return redirect()->route('standings')->with('error', 'Team not found.');

        $record     = $this->ootp->teamRecord($id);
        $roster     = $this->ootp->teamRoster($id);
        $recentGames = $this->ootp->teamRecentGames($id, 10);
        $simLength     = (int)(DB::table('settings')->value('sim_length') ?? 7);
        $firstUnplayed = DB::table('games')->where('played', 0)->orderBy('date')->value('date');
        $upcomingEnd   = $firstUnplayed ? date('Y-m-d', strtotime($firstUnplayed . ' +' . ($simLength - 1) . ' days')) : null;
        $upcoming      = $firstUnplayed
            ? (DB::table('games as g')
                ->join('teams as ht', 'g.home_team', '=', 'ht.team_id')
                ->join('teams as at', 'g.away_team', '=', 'at.team_id')
                ->where('g.played', 0)
                ->whereBetween('g.date', [$firstUnplayed, $upcomingEnd])
                ->where(fn($q) => $q->where('g.home_team', $id)->orWhere('g.away_team', $id))
                ->select('g.game_id', 'g.date', 'g.home_team', 'g.away_team',
                    'ht.abbr as home_abbr', 'ht.name as home_name', 'ht.nickname as home_nickname',
                    'at.abbr as away_abbr', 'at.name as away_name', 'at.nickname as away_nickname')
                ->orderBy('g.date')->orderBy('g.time')
                ->get())
            : collect();
        $leaders    = $this->ootp->teamLeaders($id);
        $affiliates = $this->ootp->teamAffiliates($id);
        $parentTeam = (int)($team->parent_team_id ?? 0) > 0
            ? $this->ootp->team((int)$team->parent_team_id)
            : null;

        // Right sidebar data
        $divisionStandings = $this->ootp->divisionStandingsForTeam($id);
        $slId              = (int)($team->sub_league_id ?? 0);
        $extendedRecords   = $this->ootp->teamExtendedRecords($id);
        $homeAway          = $extendedRecords ? ['home_w' => $extendedRecords['home']['w'], 'home_l' => $extendedRecords['home']['l'],
                                                  'road_w' => $extendedRecords['road']['w'], 'road_l' => $extendedRecords['road']['l']] : null;
        $last10Rec         = $extendedRecords['last10'] ?? null;
        $battingRankings   = $this->ootp->teamBattingRankings($id, $slId);
        $pitchingRankings  = $this->ootp->teamPitchingRankings($id, $slId);

        // Farm ranking for this team
        $farmRank = null;
        if ((int)($team->level ?? 1) === 1) {
            $allFarmRanks = $this->ootp->farmRankings();
            $farmRank = collect($allFarmRanks)->firstWhere('parent_team_id', $id);
        }

        // Who's Hot / Who's Not — last 7 days batting + last 2 starts pitching
        // Who's Hot / Who's Not — last 14 days for better sample size
        $lastPlayed = DB::table('games')->where('played', 1)->orderByDesc('date')->value('date');
        $hotCutoff  = $lastPlayed ? date('Y-m-d', strtotime($lastPlayed . ' -13 days')) : null;

        $hotBatters = $coldBatters = $hotPitchers = $coldPitchers = collect();
        if ($hotCutoff) {
            $recentBatting = DB::table('players_game_batting as pgb')
                ->join('games as g', 'g.game_id', '=', 'pgb.game_id')
                ->join('players as p', 'p.player_id', '=', 'pgb.player_id')
                ->where('pgb.team_id', $id)->where('g.played', 1)
                ->where('g.date', '>=', $hotCutoff)->where('g.date', '<=', $lastPlayed)
                ->groupBy('pgb.player_id', 'p.first_name', 'p.last_name', 'p.position')
                ->selectRaw('pgb.player_id, p.first_name, p.last_name, p.position,
                    COUNT(DISTINCT g.game_id) as gp, SUM(pgb.ab) as ab, SUM(pgb.h) as h,
                    SUM(pgb.hr) as hr, SUM(pgb.rbi) as rbi,
                    ROUND(SUM(pgb.h)/NULLIF(SUM(pgb.ab),0),3) as avg')
                ->havingRaw('SUM(pgb.ab) >= 15')
                ->get();

            // Hot: batting .300+ recently
            $hotBatters  = $recentBatting->filter(fn($b) => (float)$b->avg >= .300)->sortByDesc('avg')->take(3)->values();
            // Cold: batting under .200 recently
            $coldBatters = $recentBatting->filter(fn($b) => (float)$b->avg < .200)->sortBy('avg')->take(3)->values();

            $recentPitching = DB::table('players_game_pitching_stats as pgp')
                ->join('games as g', 'g.game_id', '=', 'pgp.game_id')
                ->join('players as p', 'p.player_id', '=', 'pgp.player_id')
                ->where('pgp.team_id', $id)->where('g.played', 1)
                ->where('g.date', '>=', $hotCutoff)->where('g.date', '<=', $lastPlayed)
                ->where('pgp.gs', 1)
                ->groupBy('pgp.player_id', 'p.first_name', 'p.last_name')
                ->selectRaw('pgp.player_id, p.first_name, p.last_name,
                    SUM(pgp.w) as w, SUM(pgp.l) as l, SUM(pgp.outs) as outs,
                    SUM(pgp.er) as er,
                    ROUND(SUM(pgp.er)*27/NULLIF(SUM(pgp.outs),0),2) as era')
                ->havingRaw('SUM(pgp.outs) >= 9')
                ->get();

            // Hot: ERA under 3.50 recently
            $hotPitchers  = $recentPitching->filter(fn($p) => (float)$p->era <= 3.50)->sortBy('era')->take(2)->values();
            // Cold: ERA over 4.50 recently
            $coldPitchers = $recentPitching->filter(fn($p) => (float)$p->era >= 4.50)->sortByDesc('era')->take(2)->values();
        }

        // Starting lineups (vs RHP / vs LHP) from parsed almanac data
        $lineupsRaw = DB::table('team_starting_lineups as tsl')
            ->join('players as p', 'p.player_id', '=', 'tsl.player_id')
            ->where('tsl.team_id', $id)
            ->select('tsl.vs', 'tsl.slot', 'tsl.player_id', 'tsl.bats', 'tsl.position',
                     'p.first_name', 'p.last_name')
            ->orderBy('tsl.vs')->orderBy('tsl.slot')
            ->get();
        $lineups = ['rhp' => $lineupsRaw->where('vs', 'rhp')->values(),
                     'lhp' => $lineupsRaw->where('vs', 'lhp')->values()];

        // Pitching staff from parsed almanac data
        $pitchingStaff = DB::table('team_pitching_staff as tps')
            ->join('players as p', 'p.player_id', '=', 'tps.player_id')
            ->where('tps.team_id', $id)
            ->select('tps.player_id', 'tps.role', 'tps.throws', 'tps.sort_order',
                     'p.first_name', 'p.last_name')
            ->orderBy('tps.sort_order')
            ->get();

        // Group roster by position group
        $roleLabels = [
            11 => 'Starter', 12 => 'Reliever', 13 => 'Closer',
            14 => 'Specialist', 15 => 'Specialist',
            16 => 'Middle Relief', 17 => 'Middle Relief',
            18 => 'Setup', 19 => 'Long Relief',
        ];
        $groups = ['catchers' => [], 'infield' => [], 'outfield' => [], 'dh' => [],
                   'starters' => [], 'relievers' => [], 'closers' => []];

        foreach ($roster as $p) {
            $pos  = (int)($p->position ?? 0);
            $role = (int)($p->role ?? 0);
            $p->role_label = $roleLabels[$role] ?? ($pos === 1 ? 'Pitcher' : null);

            if ($pos === 1) {
                // Pitcher — sub-classify by role
                if ($role === 13)     $groups['closers'][]   = $p;
                elseif ($role === 11) $groups['starters'][]  = $p;
                else                  $groups['relievers'][] = $p;  // role 12, 14-19, or 0
            } elseif ($pos === 2)                     $groups['catchers'][]  = $p;
            elseif (in_array($pos, [3, 4, 5, 6]))     $groups['infield'][]   = $p;
            elseif (in_array($pos, [7, 8, 9]))         $groups['outfield'][]  = $p;
            elseif ($pos === 10)                       $groups['dh'][]        = $p;
            else                                       $groups['infield'][]   = $p;
        }

        $batterIds  = array_map(fn ($p) => $p->player_id,
            array_merge($groups['catchers'], $groups['infield'], $groups['outfield'], $groups['dh']));
        $pitcherIds = array_map(fn ($p) => $p->player_id,
            array_merge($groups['starters'], $groups['relievers'], $groups['closers']));

        $batterStats  = $this->ootp->teamSeasonBatting($batterIds, $id);
        $pitcherStats = $this->ootp->teamSeasonPitching($pitcherIds, $id);

        // MLB-wide leaders for yellow highlighting in roster tables
        $mlbLeaders = $this->ootp->mlbLeaderValues();

        return view('teams.show', compact(
            'team', 'record', 'groups', 'batterStats', 'pitcherStats',
            'recentGames', 'upcoming', 'leaders', 'affiliates', 'parentTeam',
            'divisionStandings', 'homeAway', 'last10Rec',
            'extendedRecords', 'battingRankings', 'pitchingRankings', 'farmRank',
            'hotBatters', 'coldBatters', 'hotPitchers', 'coldPitchers',
            'mlbLeaders', 'lineups', 'pitchingStaff',
        ));
    }

    // -------------------------------------------------------------------------
    // Finances
    // -------------------------------------------------------------------------

    public function finances(int $id)
    {
        $team = $this->ootp->teamWithDetails($id);
        if (!$team) return redirect()->route('standings');

        $record     = $this->ootp->teamRecord($id);
        $affiliates = $this->ootp->teamAffiliates($id);
        $parentTeam = (int)($team->parent_team_id ?? 0) > 0 ? $this->ootp->team((int)$team->parent_team_id) : null;
        $fin        = $this->ootp->teamFinancials($id);
        $contracts  = $this->ootp->teamContracts($id);

        return view('teams.finances', compact('team', 'record', 'affiliates', 'parentTeam', 'fin', 'contracts'));
    }

    // -------------------------------------------------------------------------
    // History
    // -------------------------------------------------------------------------

    public function history(int $id)
    {
        $team    = $this->ootp->teamWithDetails($id);
        if (!$team) return redirect()->route('standings');

        $record     = $this->ootp->teamRecord($id);
        $affiliates = $this->ootp->teamAffiliates($id);
        $parentTeam = (int)($team->parent_team_id ?? 0) > 0 ? $this->ootp->team((int)$team->parent_team_id) : null;
        $history    = $this->ootp->teamHistoryRecord($id);

        return view('teams.history', compact('team', 'record', 'affiliates', 'parentTeam', 'history'));
    }

    // -------------------------------------------------------------------------
    // Injuries
    // -------------------------------------------------------------------------

    public function injuries(int $id)
    {
        $team    = $this->ootp->teamWithDetails($id);
        if (!$team) return redirect()->route('standings');

        $record     = $this->ootp->teamRecord($id);
        $affiliates = $this->ootp->teamAffiliates($id);
        $parentTeam = (int)($team->parent_team_id ?? 0) > 0 ? $this->ootp->team((int)$team->parent_team_id) : null;
        $current    = $this->ootp->teamCurrentInjuries($id);
        $history    = $this->ootp->teamInjuryHistory($id);

        return view('teams.injuries', compact('team', 'record', 'affiliates', 'parentTeam', 'current', 'history'));
    }

    // -------------------------------------------------------------------------
    // Minor League / Farm System overview
    // -------------------------------------------------------------------------

    public function minors(int $id)
    {
        $team       = $this->ootp->teamWithDetails($id);
        if (!$team) return redirect()->route('standings');

        $record     = $this->ootp->teamRecord($id);
        $parentTeam = (int)($team->parent_team_id ?? 0) > 0 ? $this->ootp->team((int)$team->parent_team_id) : null;
        $affiliates = $this->ootp->teamAffiliates($id);

        // For each affiliate, grab their full roster grouped
        $affiliateRosters = [];
        foreach ($affiliates as $aff) {
            $roster = $this->ootp->teamRoster((int)$aff->team_id);
            $batterIds  = $roster->whereNotIn('position', [1, 2])->pluck('player_id')->toArray();
            $pitcherIds = $roster->whereIn('position', [1, 2])->pluck('player_id')->toArray();
            $affiliateRosters[$aff->team_id] = [
                'team'          => $aff,
                'roster'        => $roster,
                'batter_stats'  => $this->ootp->teamSeasonBatting($batterIds, (int)$aff->team_id),
                'pitcher_stats' => $this->ootp->teamSeasonPitching($pitcherIds, (int)$aff->team_id),
            ];
        }

        return view('teams.minors', compact('team', 'record', 'parentTeam', 'affiliates', 'affiliateRosters'));
    }
}
