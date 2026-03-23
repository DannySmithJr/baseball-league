<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Central read-only access layer for OOTP-imported data.
 *
 * All tables read here are owned by OOTP exports — never write to them
 * from the application.  Add new accessors here as features are built.
 */
class OotpService
{
    private ?object $leagueCache = null;
    private bool $leagueFetched = false;

    // -------------------------------------------------------------------------
    // League
    // -------------------------------------------------------------------------

    /** The top-level league row (parent_league_id = 0). Memoized per request. */
    public function league(): ?object
    {
        if (!$this->leagueFetched) {
            $this->leagueCache = $this->safeQuery(fn () =>
                DB::table('leagues')->where('parent_league_id', 0)->first()
            );
            $this->leagueFetched = true;
        }
        return $this->leagueCache;
    }

    public function seasonYear(): ?int
    {
        return $this->league()?->season_year;
    }

    public function gameDate(): ?string
    {
        return $this->league()?->current_date;
    }

    /**
     * Playoff config from OOTP's league_playoffs table.
     * Returns: ['division_winners' => bool, 'wildcards' => int]
     */
    public function playoffConfig(): array
    {
        $league = $this->league();
        if (!$league) return ['division_winners' => true, 'wildcards' => 0];

        $row = $this->safeQuery(fn () =>
            DB::table('league_playoffs')->where('league_id', $league->league_id)->first()
        );

        return [
            'division_winners' => true, // always true when divisions exist
            'wildcards'        => $row ? (int)$row->num_wild_cards : 0,
        ];
    }

    /** All sub-leagues for the top-level league. */
    public function subLeagues(): \Illuminate\Support\Collection
    {
        $league = $this->league();
        if (!$league) return collect();

        return $this->safeQuery(fn () =>
            DB::table('sub_leagues')->where('league_id', $league->league_id)->get()
        ) ?? collect();
    }

    /** All divisions, optionally filtered by sub_league_id. */
    public function divisions(?int $subLeagueId = null): \Illuminate\Support\Collection
    {
        $league = $this->league();
        if (!$league) return collect();

        return $this->safeQuery(fn () =>
            DB::table('divisions')
                ->where('league_id', $league->league_id)
                ->when($subLeagueId !== null, fn ($q) => $q->where('sub_league_id', $subLeagueId))
                ->get()
        ) ?? collect();
    }

    // -------------------------------------------------------------------------
    // Teams
    // -------------------------------------------------------------------------

    /** All active (non-affiliate) teams. */
    public function teams(): \Illuminate\Support\Collection
    {
        $league = $this->league();
        if (!$league) return collect();

        return $this->safeQuery(fn () =>
            DB::table('teams')
                ->where('league_id', $league->league_id)
                ->where('allstar_team', 0)
                ->orderBy('name')
                ->get()
        ) ?? collect();
    }

    public function team(int $teamId): ?object
    {
        return $this->safeQuery(fn () =>
            DB::table('teams')->where('team_id', $teamId)->first()
        );
    }

    /** Team enriched with sub_league, division, and park names. */
    public function teamWithDetails(int $teamId): ?object
    {
        return $this->safeQuery(fn () =>
            DB::table('teams as t')
                ->leftJoin('sub_leagues as sl', function ($j) {
                    $j->on('t.league_id', '=', 'sl.league_id')
                      ->on('t.sub_league_id', '=', 'sl.sub_league_id');
                })
                ->leftJoin('divisions as d', function ($j) {
                    $j->on('t.league_id', '=', 'd.league_id')
                      ->on('t.division_id', '=', 'd.division_id')
                      ->on('t.sub_league_id', '=', 'd.sub_league_id');
                })
                ->leftJoin('parks as pk', 't.park_id', '=', 'pk.park_id')
                ->select(
                    't.*',
                    'sl.name as sub_league_name', 'sl.abbr as sub_league_abbr',
                    'd.name as division_name',
                    'pk.name as park_name',
                )
                ->where('t.team_id', $teamId)
                ->first()
        );
    }

    /** Team W-L record from team_record table. */
    public function teamRecord(int $teamId): ?object
    {
        return $this->safeQuery(fn () =>
            DB::table('team_record')->where('team_id', $teamId)->first()
        );
    }

    /**
     * Active roster for a team.
     * Tries team_roster (list_id=1 = 25-man) first, falls back to players.team_id.
     */
    public function teamRoster(int $teamId): \Illuminate\Support\Collection
    {
        $roster = $this->safeQuery(fn () =>
            DB::table('team_roster as tr')
                ->join('players as p', 'tr.player_id', '=', 'p.player_id')
                ->where('tr.team_id', $teamId)
                ->where('tr.list_id', 1)
                ->select('p.*')
                ->orderBy('p.position')
                ->orderBy('p.last_name')
                ->get()
        );

        if ($roster && $roster->isNotEmpty()) return $roster;

        // Fallback: all players currently on the team
        return $this->safeQuery(fn () =>
            DB::table('players')
                ->where('team_id', $teamId)
                ->where('retired', 0)
                ->orderBy('position')
                ->orderBy('last_name')
                ->get()
        ) ?? collect();
    }

    /** Season batting stats for a list of players, aggregated from per-game logs. */
    public function teamSeasonBatting(array $playerIds, int $teamId): array
    {
        if (empty($playerIds)) return [];

        $rows = $this->safeQuery(fn () =>
            DB::table('players_game_batting as b')
                ->whereIn('b.player_id', $playerIds)
                ->where('b.team_id', $teamId)
                ->select(
                    'b.player_id',
                    DB::raw('COUNT(DISTINCT b.game_id) as g'),
                    DB::raw('SUM(b.pa) as pa'),
                    DB::raw('SUM(b.ab) as ab'),
                    DB::raw('SUM(b.h)  as h'),
                    DB::raw('SUM(b.d)  as d'),
                    DB::raw('SUM(b.t)  as t'),
                    DB::raw('SUM(b.hr) as hr'),
                    DB::raw('SUM(b.r)  as r'),
                    DB::raw('SUM(b.rbi) as rbi'),
                    DB::raw('SUM(b.bb) as bb'),
                    DB::raw('SUM(b.k)  as k'),
                    DB::raw('SUM(b.sb) as sb'),
                    DB::raw('SUM(b.cs) as cs'),
                    DB::raw('SUM(b.hp) as hp'),
                    DB::raw('SUM(b.sf) as sf'),
                )
                ->groupBy('b.player_id')
                ->get()
        ) ?? collect();

        $result = [];
        foreach ($rows as $row) {
            $ab  = (int) $row->ab;
            $h   = (int) $row->h;
            $bb  = (int) $row->bb;
            $hp  = (int) $row->hp;
            $sf  = (int) $row->sf;
            $d   = (int) $row->d;
            $t   = (int) $row->t;
            $hr  = (int) $row->hr;
            $avg = $ab > 0 ? $h / $ab : 0;
            $obp = ($ab + $bb + $hp + $sf) > 0 ? ($h + $bb + $hp) / ($ab + $bb + $hp + $sf) : 0;
            $slg = $ab > 0 ? (($h - $d - $t - $hr) + 2*$d + 3*$t + 4*$hr) / $ab : 0;
            $row->avg = $avg;
            $row->obp = $obp;
            $row->slg = $slg;
            $row->ops = $obp + $slg;
            $result[(int) $row->player_id] = $row;
        }

        return $result;
    }

    /** Season pitching stats for a list of players, aggregated from per-game logs. */
    public function teamSeasonPitching(array $playerIds, int $teamId): array
    {
        if (empty($playerIds)) return [];

        $rows = $this->safeQuery(fn () =>
            DB::table('players_game_pitching_stats as ps')
                ->whereIn('ps.player_id', $playerIds)
                ->where('ps.team_id', $teamId)
                ->select(
                    'ps.player_id',
                    DB::raw('COUNT(DISTINCT ps.game_id) as g'),
                    DB::raw('SUM(ps.gs)   as gs'),
                    DB::raw('SUM(ps.w)    as w'),
                    DB::raw('SUM(ps.l)    as l'),
                    DB::raw('SUM(ps.s)    as sv'),
                    DB::raw('SUM(ps.hld)  as hld'),
                    DB::raw('SUM(ps.outs) as outs'),
                    DB::raw('SUM(ps.ha)   as h'),
                    DB::raw('SUM(ps.er)   as er'),
                    DB::raw('SUM(ps.bb)   as bb'),
                    DB::raw('SUM(ps.k)    as k'),
                    DB::raw('SUM(ps.hra)  as hr'),
                    DB::raw('SUM(ps.pi)   as pitches'),
                    DB::raw('SUM(ps.qs)   as qs'),
                    DB::raw('SUM(ps.cg)   as cg'),
                )
                ->groupBy('ps.player_id')
                ->get()
        ) ?? collect();

        $result = [];
        foreach ($rows as $row) {
            $outs = (int) $row->outs;
            $ip   = $outs / 3;
            $row->ip_display = floor($outs / 3) . '.' . ($outs % 3);
            $row->era  = $ip > 0 ? number_format(((int) $row->er / $ip) * 9, 2) : '—';
            $row->whip = $ip > 0 ? number_format(((int) $row->h + (int) $row->bb) / $ip, 2) : '—';
            $result[(int) $row->player_id] = $row;
        }

        return $result;
    }

    /** Recent completed games for a team. */
    public function teamRecentGames(int $teamId, int $limit = 10): \Illuminate\Support\Collection
    {
        return $this->safeQuery(fn () =>
            DB::table('games as g')
                ->join('teams as ht', 'g.home_team', '=', 'ht.team_id')
                ->join('teams as at', 'g.away_team', '=', 'at.team_id')
                ->where('g.played', 1)
                ->where('g.game_type', 0)
                ->where(fn ($q) => $q->where('g.home_team', $teamId)->orWhere('g.away_team', $teamId))
                ->select(
                    'g.game_id', 'g.date', 'g.runs0', 'g.runs1',
                    'g.home_team', 'g.away_team', 'g.innings',
                    'ht.abbr as home_abbr', 'ht.name as home_name',
                    'at.abbr as away_abbr', 'at.name as away_name',
                )
                ->orderByDesc('g.date')
                ->orderByDesc('g.game_id')
                ->limit($limit)
                ->get()
        ) ?? collect();
    }

    /** Upcoming games for a team. */
    public function teamUpcomingGames(int $teamId, int $limit = 5): \Illuminate\Support\Collection
    {
        return $this->safeQuery(fn () =>
            DB::table('games as g')
                ->join('teams as ht', 'g.home_team', '=', 'ht.team_id')
                ->join('teams as at', 'g.away_team', '=', 'at.team_id')
                ->where('g.played', 0)
                ->where(fn ($q) => $q->where('g.home_team', $teamId)->orWhere('g.away_team', $teamId))
                ->select(
                    'g.game_id', 'g.date',
                    'g.home_team', 'g.away_team',
                    'ht.abbr as home_abbr', 'at.abbr as away_abbr',
                )
                ->orderBy('g.date')
                ->limit($limit)
                ->get()
        ) ?? collect();
    }

    /** Stat leaders for a team. Returns array keyed by stat. */
    /**
     * MLB-wide leader values for highlighting in roster tables.
     * Returns ['hr' => int, 'rbi' => int, 'avg' => float, 'h' => int, 'sb' => int,
     *          'w' => int, 'k' => int, 'sv' => int, 'era' => float, 'whip' => float]
     */
    /**
     * Farm system rankings for all MLB parent teams.
     * Returns collection sorted by rank: [{parent_team_id, abbr, name, score, prospect_count, elite_count, rank, top_prospect}]
     */
    public function farmRankings(): array
    {
        $prospects = $this->safeQuery(fn () =>
            DB::table('players_scouted_ratings as r')
                ->join('team_roster as tr', 'tr.player_id', '=', 'r.player_id')
                ->join('teams as t', 't.team_id', '=', 'tr.team_id')
                ->join('players as p', 'p.player_id', '=', 'r.player_id')
                ->where('t.level', '>', 1)->where('t.parent_team_id', '>', 0)->where('tr.list_id', 1)
                ->select('r.player_id', 'r.talent', 'p.age', 'p.first_name', 'p.last_name', 't.parent_team_id')
                ->get()
        ) ?? collect();

        $parentNames = DB::table('teams')->where('level', 1)->where('allstar_team', 0)
            ->get(['team_id', 'name', 'nickname', 'abbr'])->keyBy('team_id');

        $farms = [];
        foreach ($prospects as $p) {
            $parent = (int)$p->parent_team_id;
            $talent = (int)$p->talent;
            $age    = (int)$p->age;
            // Age multiplier: younger = higher value. Peak at 18 (1.5x), 1.0 at 25, min 0.3
            $ageMult = max(0.3, 1.5 - (($age - 18) * 0.07));
            $score   = $talent * $ageMult;

            if (!isset($farms[$parent])) {
                $farms[$parent] = ['score' => 0, 'count' => 0, 'elite' => 0, 'top' => null, 'topScore' => 0];
            }
            $farms[$parent]['score'] += $score;
            $farms[$parent]['count']++;
            if ($talent >= 100) $farms[$parent]['elite']++;
            if ($score > $farms[$parent]['topScore']) {
                $farms[$parent]['top'] = $p->first_name[0] . '. ' . $p->last_name;
                $farms[$parent]['topScore'] = $score;
                $farms[$parent]['topTalent'] = $talent;
                $farms[$parent]['topAge'] = $age;
            }
        }

        // Sort by score descending
        arsort($farms);

        $result = [];
        $rank = 1;
        foreach ($farms as $parentId => $data) {
            $info = $parentNames[$parentId] ?? null;
            if (!$info) continue;
            $result[] = [
                'parent_team_id' => $parentId,
                'abbr'           => $info->abbr,
                'name'           => $info->name . ' ' . ($info->nickname ?? ''),
                'score'          => round($data['score']),
                'prospect_count' => $data['count'],
                'elite_count'    => $data['elite'],
                'rank'           => $rank++,
                'top_prospect'   => $data['top'],
                'top_talent'     => $data['topTalent'] ?? 0,
                'top_age'        => $data['topAge'] ?? 0,
            ];
        }

        return $result;
    }

    /**
     * Player's league rank for header stats. Returns ranks only if top 100.
     * For batters: ['avg', 'hr', 'rbi', 'ops'], for pitchers: ['wl', 'era', 'k', 'whip']
     */
    /**
     * Parse OOTP message body markup into HTML with links.
     * Converts <Name:player#123> to player links and <Team:team#1> to team links.
     */
    public static function parseMessageBody(string $body): string
    {
        // First escape everything
        $body = e($body);

        // Player links: &lt;Name:player#ID&gt;
        $body = preg_replace_callback(
            '/&lt;([^&]+):player#(\d+)&gt;/',
            fn($m) => '<a href="' . route('player', (int)$m[2]) . '" class="text-red-400 hover:text-red-300 font-semibold">' . $m[1] . '</a>',
            $body
        );

        // Team links: &lt;Name:team#ID&gt;
        $body = preg_replace_callback(
            '/&lt;([^&]+):team#(\d+)&gt;/',
            fn($m) => '<a href="' . route('team', (int)$m[2]) . '" class="text-red-400 hover:text-red-300 font-semibold">' . $m[1] . '</a>',
            $body
        );

        // Box score links: &lt;View Boxscore:box#ID&gt;
        $body = preg_replace_callback(
            '/&lt;[^&]*[Bb]oxscore[^&]*:box#(\d+)&gt;/',
            fn($m) => '<a href="' . route('game', (int)$m[1]) . '" class="text-red-400 hover:text-red-300 font-semibold">Box Score</a>',
            $body
        );

        // Game log links: &lt;View Game Log:log#ID&gt;
        $body = preg_replace_callback(
            '/&lt;[^&]*[Gg]ame [Ll]og[^&]*:log#(\d+)&gt;/',
            fn($m) => '<a href="' . route('game', (int)$m[1]) . '" class="text-red-400 hover:text-red-300 font-semibold">Game Log</a>',
            $body
        );

        // Highlight links: &lt;Watch Highlights:highlight#ID&gt; — remove entirely
        $body = preg_replace('/&lt;[^&]*[Hh]ighlights?[^&]*:highlight#\d+&gt;/', '', $body);

        // Bold text: &lt;Text:value_bold#N&gt;
        $body = preg_replace('/&lt;([^&]+):value_bold#\d+&gt;/', '<strong>$1</strong>', $body);

        // Page links: &lt;Text:page#N&gt; — strip, just show text
        $body = preg_replace('/&lt;([^&]+):page#\d+&gt;/', '$1', $body);

        // Convert newlines to <br>
        $body = nl2br($body);

        return $body;
    }

    /**
     * Get news/messages for a player from the messages table.
     */
    public function playerNews(int $playerId, int $limit = 20): \Illuminate\Support\Collection
    {
        return $this->safeQuery(fn () =>
            DB::table('messages')
                ->where(function ($q) use ($playerId) {
                    for ($i = 0; $i <= 9; $i++) {
                        $q->orWhere("player_id_$i", $playerId);
                    }
                    $q->orWhere('body', 'like', '%player#' . $playerId . '>%');
                })
                ->where('deleted', 0)
                ->orderByDesc('date')
                ->limit($limit)
                ->get(['message_id', 'subject', 'body', 'date', 'team_id_0', 'importance', 'message_type'])
        ) ?? collect();
    }

    public function playerHeaderRanks(int $playerId, bool $isPitcher): array
    {
        $ord = fn($n) => $n . match((int)$n % 10) {
            1 => (int)$n % 100 === 11 ? 'th' : 'st',
            2 => (int)$n % 100 === 12 ? 'th' : 'nd',
            3 => (int)$n % 100 === 13 ? 'th' : 'rd',
            default => 'th',
        };

        if ($isPitcher) {
            $all = $this->safeQuery(fn () =>
                DB::table('players_game_pitching_stats as pgp')
                    ->join('teams as t', 't.team_id', '=', 'pgp.team_id')
                    ->where('t.level', 1)
                    ->groupBy('pgp.player_id')
                    ->selectRaw('pgp.player_id,
                        SUM(pgp.w) as w, SUM(pgp.l) as l, SUM(pgp.k) as k,
                        SUM(pgp.outs) as outs, SUM(pgp.er) as er,
                        SUM(pgp.ha) as ha, SUM(pgp.bb) as bb')
                    ->havingRaw('SUM(pgp.outs) >= 15')
                    ->get()
            ) ?? collect();

            $all = $all->map(function ($r) {
                $ip = (int)$r->outs / 3;
                $r->era  = $ip > 0 ? ((int)$r->er / $ip) * 9 : 99;
                $r->whip = $ip > 0 ? ((int)$r->ha + (int)$r->bb) / $ip : 99;
                return $r;
            });

            $wRank    = $all->sortByDesc('w')->values()->search(fn($r) => (int)$r->player_id === $playerId);
            $eraRank  = $all->filter(fn($r) => (int)$r->outs >= 45)->sortBy('era')->values()->search(fn($r) => (int)$r->player_id === $playerId);
            $kRank    = $all->sortByDesc('k')->values()->search(fn($r) => (int)$r->player_id === $playerId);
            $whipRank = $all->filter(fn($r) => (int)$r->outs >= 45)->sortBy('whip')->values()->search(fn($r) => (int)$r->player_id === $playerId);

            // Check for ties at the same value
            $me = $all->firstWhere('player_id', $playerId);
            $wTied = $me ? $all->where('w', $me->w)->count() > 1 : false;

            return [
                'wl'   => $wRank !== false && $wRank < 100 ? ($wTied ? 'Tied-' : '') . $ord($wRank + 1) : null,
                'era'  => $eraRank !== false && $eraRank < 100 ? $ord($eraRank + 1) : null,
                'k'    => $kRank !== false && $kRank < 100 ? $ord($kRank + 1) : null,
                'whip' => $whipRank !== false && $whipRank < 100 ? $ord($whipRank + 1) : null,
            ];
        } else {
            $all = $this->safeQuery(fn () =>
                DB::table('players_game_batting as pgb')
                    ->join('teams as t', 't.team_id', '=', 'pgb.team_id')
                    ->where('t.level', 1)
                    ->groupBy('pgb.player_id')
                    ->selectRaw('pgb.player_id,
                        SUM(pgb.h) as h, SUM(pgb.ab) as ab, SUM(pgb.hr) as hr,
                        SUM(pgb.rbi) as rbi, SUM(pgb.bb) as bb, SUM(pgb.hp) as hp,
                        SUM(pgb.sf) as sf, SUM(pgb.pa) as pa,
                        SUM(pgb.d) as d, SUM(pgb.t) as t')
                    ->havingRaw('SUM(pgb.ab) >= 10')
                    ->get()
            ) ?? collect();

            $all = $all->map(function ($r) {
                $ab = (int)$r->ab ?: 1;
                $pa = (int)$r->pa ?: 1;
                $h  = (int)$r->h;
                $r->avg = $h / $ab;
                $r->obp = ($h + (int)$r->bb + (int)$r->hp) / $pa;
                $tb = $h + (int)$r->d + 2*(int)$r->t + 3*(int)$r->hr;
                $r->slg = $tb / $ab;
                $r->ops = $r->obp + $r->slg;
                return $r;
            });

            $avgRank = $all->filter(fn($r) => (int)$r->ab >= 100)->sortByDesc('avg')->values()->search(fn($r) => (int)$r->player_id === $playerId);
            $hrRank  = $all->sortByDesc('hr')->values()->search(fn($r) => (int)$r->player_id === $playerId);
            $rbiRank = $all->sortByDesc('rbi')->values()->search(fn($r) => (int)$r->player_id === $playerId);
            $opsRank = $all->filter(fn($r) => (int)$r->ab >= 100)->sortByDesc('ops')->values()->search(fn($r) => (int)$r->player_id === $playerId);

            $me = $all->firstWhere('player_id', $playerId);
            $hrTied  = $me ? $all->where('hr', $me->hr)->count() > 1 : false;
            $rbiTied = $me ? $all->where('rbi', $me->rbi)->count() > 1 : false;

            return [
                'avg' => $avgRank !== false && $avgRank < 100 ? $ord($avgRank + 1) : null,
                'hr'  => $hrRank !== false && $hrRank < 100 ? ($hrTied ? 'Tied-' : '') . $ord($hrRank + 1) : null,
                'rbi' => $rbiRank !== false && $rbiRank < 100 ? ($rbiTied ? 'Tied-' : '') . $ord($rbiRank + 1) : null,
                'ops' => $opsRank !== false && $opsRank < 100 ? $ord($opsRank + 1) : null,
            ];
        }
    }

    public function mlbLeaderValues(): array
    {
        // Single batting query — get all player aggregates, compute leaders in PHP
        $batRows = $this->safeQuery(fn () =>
            DB::table('players_game_batting as b')
                ->join('teams as t', 't.team_id', '=', 'b.team_id')
                ->where('t.level', 1)
                ->groupBy('b.player_id')
                ->selectRaw('SUM(b.hr) as hr, SUM(b.rbi) as rbi, SUM(b.h) as h,
                    SUM(b.sb) as sb, SUM(b.ab) as ab, SUM(b.bb) as bb,
                    SUM(b.hp) as hp, SUM(b.pa) as pa')
                ->get()
        ) ?? collect();

        $maxHr = $maxRbi = $maxH = $maxSb = 0;
        $maxAvg = 0.0;
        foreach ($batRows as $r) {
            if ((int)$r->hr > $maxHr)   $maxHr  = (int)$r->hr;
            if ((int)$r->rbi > $maxRbi) $maxRbi = (int)$r->rbi;
            if ((int)$r->h > $maxH)     $maxH   = (int)$r->h;
            if ((int)$r->sb > $maxSb)   $maxSb  = (int)$r->sb;
            if ((int)$r->ab >= 100) {
                $avg = (int)$r->h / (int)$r->ab;
                if ($avg > $maxAvg) $maxAvg = $avg;
            }
        }

        // Single pitching query
        $pitRows = $this->safeQuery(fn () =>
            DB::table('players_game_pitching_stats as ps')
                ->join('teams as t', 't.team_id', '=', 'ps.team_id')
                ->where('t.level', 1)
                ->groupBy('ps.player_id')
                ->selectRaw('SUM(ps.w) as w, SUM(ps.k) as k, SUM(ps.s) as sv,
                    SUM(ps.outs) as outs, SUM(ps.er) as er,
                    SUM(ps.ha) as ha, SUM(ps.bb) as bb')
                ->get()
        ) ?? collect();

        $maxW = $maxK = $maxSv = 0;
        $minEra = 99.0; $minWhip = 99.0;
        foreach ($pitRows as $r) {
            if ((int)$r->w > $maxW)   $maxW  = (int)$r->w;
            if ((int)$r->k > $maxK)   $maxK  = (int)$r->k;
            if ((int)$r->sv > $maxSv) $maxSv = (int)$r->sv;
            if ((int)$r->outs >= 45) {
                $ip = (int)$r->outs / 3;
                $era = ((int)$r->er / $ip) * 9;
                $whip = ((int)$r->ha + (int)$r->bb) / $ip;
                if ($era < $minEra)   $minEra  = $era;
                if ($whip < $minWhip) $minWhip = $whip;
            }
        }

        return [
            'hr'   => $maxHr,  'rbi'  => $maxRbi, 'h' => $maxH, 'sb' => $maxSb,
            'avg'  => round($maxAvg, 3),
            'w'    => $maxW,   'k'    => $maxK,   'sv' => $maxSv,
            'era'  => round($minEra, 2),
            'whip' => round($minWhip, 2),
        ];
    }

    /**
     * MLB leader values per year for career stats highlighting.
     * Returns [year => ['hr' => int, 'rbi' => int, ...]]
     */
    public function mlbLeaderValuesByYear(array $years): array
    {
        if (empty($years)) return [];

        // MLB team IDs for filtering without join
        $mlbTeamIds = DB::table('teams')->where('level', 1)->pluck('team_id')->toArray();
        if (empty($mlbTeamIds)) return [];

        // Batch: one query per stat type across all years (no join needed)
        $batRows = $this->safeQuery(fn () =>
            DB::table('players_career_batting_stats')
                ->whereIn('team_id', $mlbTeamIds)
                ->whereIn('year', $years)->where('split_id', 1)
                ->groupBy('year')
                ->selectRaw('year, MAX(hr) as hr, MAX(rbi) as rbi, MAX(h) as h, MAX(sb) as sb')
                ->get()
        ) ?? collect();

        $batAvgRows = $this->safeQuery(fn () =>
            DB::table('players_career_batting_stats')
                ->whereIn('team_id', $mlbTeamIds)
                ->whereIn('year', $years)->where('split_id', 1)
                ->where('ab', '>=', 100)
                ->groupBy('year')
                ->selectRaw('year, MAX(h / ab) as avg')
                ->get()
        ) ?? collect();

        $pitRows = $this->safeQuery(fn () =>
            DB::table('players_career_pitching_stats')
                ->whereIn('team_id', $mlbTeamIds)
                ->whereIn('year', $years)->where('split_id', 1)
                ->groupBy('year')
                ->selectRaw('year, MAX(w) as w, MAX(k) as k, MAX(s) as sv')
                ->get()
        ) ?? collect();

        $pitEraRows = $this->safeQuery(fn () =>
            DB::table('players_career_pitching_stats')
                ->whereIn('team_id', $mlbTeamIds)
                ->whereIn('year', $years)->where('split_id', 1)
                ->where('outs', '>=', 45)
                ->groupBy('year')
                ->selectRaw('year, MIN(er * 9 / (outs / 3)) as era')
                ->get()
        ) ?? collect();

        $batByYear    = $batRows->keyBy('year');
        $batAvgByYear = $batAvgRows->keyBy('year');
        $pitByYear    = $pitRows->keyBy('year');
        $pitEraByYear = $pitEraRows->keyBy('year');

        $result = [];
        foreach ($years as $yr) {
            $bat    = $batByYear[$yr] ?? null;
            $batAvg = $batAvgByYear[$yr] ?? null;
            $pit    = $pitByYear[$yr] ?? null;
            $pitEra = $pitEraByYear[$yr] ?? null;
            $result[$yr] = [
                'hr'  => (int)($bat->hr ?? 0),
                'rbi' => (int)($bat->rbi ?? 0),
                'h'   => (int)($bat->h ?? 0),
                'sb'  => (int)($bat->sb ?? 0),
                'avg' => round((float)($batAvg->avg ?? 0), 3),
                'w'   => (int)($pit->w ?? 0),
                'k'   => (int)($pit->k ?? 0),
                'sv'  => (int)($pit->sv ?? 0),
                'era' => round((float)($pitEra->era ?? 99), 2),
            ];
        }

        return $result;
    }

    public function teamLeaders(int $teamId): array
    {
        // Single batting query
        $batters = $this->safeQuery(fn () =>
            DB::table('players_game_batting as b')
                ->join('players as p', 'b.player_id', '=', 'p.player_id')
                ->where('b.team_id', $teamId)
                ->select('p.player_id', 'p.first_name', 'p.last_name', 'p.position',
                    DB::raw('SUM(b.h) as h'), DB::raw('SUM(b.ab) as ab'),
                    DB::raw('SUM(b.hr) as hr'), DB::raw('SUM(b.rbi) as rbi'),
                    DB::raw('SUM(b.sb) as sb'))
                ->groupBy('b.player_id', 'p.player_id', 'p.first_name', 'p.last_name', 'p.position')
                ->havingRaw('SUM(b.ab) >= 1')
                ->get()
        ) ?? collect();

        // Single pitching query
        $pitchers = $this->safeQuery(fn () =>
            DB::table('players_game_pitching_stats as ps')
                ->join('players as p', 'ps.player_id', '=', 'p.player_id')
                ->where('ps.team_id', $teamId)
                ->select('p.player_id', 'p.first_name', 'p.last_name',
                    DB::raw('SUM(ps.outs) as outs'), DB::raw('SUM(ps.er) as er'),
                    DB::raw('SUM(ps.w) as w'), DB::raw('SUM(ps.k) as k'),
                    DB::raw('SUM(ps.s) as sv'))
                ->groupBy('ps.player_id', 'p.player_id', 'p.first_name', 'p.last_name')
                ->get()
        ) ?? collect();

        $fmtName = fn($r) => $r->first_name[0] . '. ' . $r->last_name;
        $fmtAvg  = fn($v) => $v >= 1 ? '1.000' : '.' . str_pad((string)round($v * 1000), 3, '0', STR_PAD_LEFT);

        // Compute derived values on batters
        $batters = $batters->map(function ($b) {
            $b->hr  = (int)$b->hr;
            $b->rbi = (int)$b->rbi;
            $b->sb  = (int)$b->sb;
            $b->avg_val = (int)$b->ab >= 10 ? (int)$b->h / (int)$b->ab : 0;
            return $b;
        });

        // Compute derived values on pitchers
        $pitchers = $pitchers->map(function ($p) {
            $p->w  = (int)$p->w;
            $p->k  = (int)$p->k;
            $p->sv = (int)$p->sv;
            $ip = (int)$p->outs / 3;
            $p->era_val = $ip > 0 ? ((int)$p->er / $ip) * 9 : 99;
            return $p;
        });

        // Top 5 helper — returns array of [{player_id, name, val, tie}]
        $top5 = function ($collection, string $sortField, bool $desc, callable $valFmt, ?callable $filter = null) use ($fmtName) {
            $filtered = $filter ? $collection->filter($filter) : $collection;
            $sorted   = $desc ? $filtered->sortByDesc($sortField)->values() : $filtered->sortBy($sortField)->values();
            $entries  = [];
            $prevVal  = null;
            foreach ($sorted->take(5) as $r) {
                $display = $valFmt($r);
                $entries[] = [
                    'player_id' => $r->player_id,
                    'name'      => $fmtName($r),
                    'val'       => $display,
                    'tie'       => $prevVal !== null && $display === $prevVal,
                ];
                $prevVal = $display;
            }
            return $entries;
        };

        return [
            'avg' => $top5($batters, 'avg_val', true,
                fn($r) => $fmtAvg($r->avg_val),
                fn($b) => (int)$b->ab >= 10),
            'hr'  => $top5($batters, 'hr', true, fn($r) => (string)$r->hr),
            'rbi' => $top5($batters, 'rbi', true, fn($r) => (string)$r->rbi),
            'w'   => $top5($pitchers, 'w', true, fn($r) => (string)$r->w),
            'era' => $top5($pitchers, 'era_val', false,
                fn($r) => number_format($r->era_val, 2),
                fn($p) => (int)$p->outs >= 15),
            'k'   => $top5($pitchers, 'k', true, fn($r) => (string)$r->k),
        ];

    }

    /** Minor league / affiliate teams for a given parent team, with their records. */
    public function teamAffiliates(int $parentTeamId): \Illuminate\Support\Collection
    {
        $affiliates = $this->safeQuery(fn () =>
            DB::table('teams as t')
                ->leftJoin('leagues as l', 't.league_id', '=', 'l.league_id')
                ->where('t.parent_team_id', $parentTeamId)
                ->where('t.parent_team_id', '!=', 0)
                ->select('t.*', 'l.name as league_name')
                ->orderBy('t.level')
                ->get()
        ) ?? collect();

        $levelLabels = [1 => 'MLB', 2 => 'AAA', 3 => 'AA', 4 => 'A+', 5 => 'A', 6 => 'Rk'];

        // Batch: all records + division info in bulk
        $allRecords = $this->safeQuery(fn () =>
            DB::table('team_record')->get(['team_id', 'w', 'l'])
        ) ?? collect();
        $recordsByTeam = $allRecords->keyBy('team_id');

        $affTeamIds = $affiliates->pluck('team_id')->toArray();
        $divLeaders = [];
        if ($affTeamIds) {
            // Get league_id + division_id for each affiliate
            $affInfo = DB::table('teams')->whereIn('team_id', $affTeamIds)
                ->get(['team_id', 'league_id', 'division_id']);

            // Get unique league+division combos, fetch all teams in those combos in one query
            $combos = $affInfo->map(fn($a) => ['league_id' => $a->league_id, 'division_id' => $a->division_id])->unique()->values();
            $allDivTeams = DB::table('teams')
                ->where(function ($q) use ($combos) {
                    foreach ($combos as $c) {
                        $q->orWhere(fn($q2) => $q2->where('league_id', $c['league_id'])->where('division_id', $c['division_id']));
                    }
                })
                ->get(['team_id', 'league_id', 'division_id']);

            // Group by league+division, find leader in each
            foreach ($allDivTeams->groupBy(fn($t) => $t->league_id . '-' . $t->division_id) as $divTeams) {
                $divTeamIds = $divTeams->pluck('team_id')->toArray();
                $best = $allRecords->whereIn('team_id', $divTeamIds)
                    ->sortByDesc(fn($r) => ($r->w + $r->l) > 0 ? $r->w / ($r->w + $r->l) : 0)
                    ->first();
                if ($best) $divLeaders[(int)$best->team_id] = true;
            }
        }

        return $affiliates->map(function ($aff) use ($levelLabels, $divLeaders, $recordsByTeam) {
            $record = $recordsByTeam[(int)$aff->team_id] ?? null;
            $aff->w = $record ? (int)$record->w : 0;
            $aff->l = $record ? (int)$record->l : 0;
            $aff->level_label = $levelLabels[(int) $aff->level] ?? ('Level ' . $aff->level);
            $aff->leads_division = isset($divLeaders[(int) $aff->team_id]);
            return $aff;
        });
    }

    // -------------------------------------------------------------------------
    // Team Finances
    // -------------------------------------------------------------------------

    public function teamFinancials(int $teamId): ?object
    {
        return $this->safeQuery(fn () =>
            DB::table('team_financials')->where('team_id', $teamId)->first()
        );
    }

    /**
     * Player contracts for a team, split into major/minor.
     * Returns ['major' => [...], 'minor' => [...], 'total_payroll' => int]
     */
    public function teamContracts(int $teamId): array
    {
        $contracts = $this->safeQuery(fn () =>
            DB::table('players_contract as c')
                ->join('players as p', 'c.player_id', '=', 'p.player_id')
                ->where('c.team_id', $teamId)
                ->select(
                    'c.*',
                    'p.first_name', 'p.last_name', 'p.position', 'p.age',
                )
                ->orderByDesc('c.salary0')
                ->get()
        ) ?? collect();

        $major = [];
        $minor = [];
        $totalPayroll = 0;

        foreach ($contracts as $c) {
            if ((int) $c->is_major) {
                $major[] = $c;
                $totalPayroll += (int) $c->salary0;
            } else {
                $minor[] = $c;
            }
        }

        return compact('major', 'minor', 'totalPayroll');
    }

    // -------------------------------------------------------------------------
    // Team History
    // -------------------------------------------------------------------------

    /**
     * Year-by-year record. Tries team_history_record first,
     * then falls back to computing from games table.
     */
    public function teamHistoryRecord(int $teamId): \Illuminate\Support\Collection
    {
        // Try OOTP history table first
        $history = $this->safeQuery(fn () =>
            DB::table('team_history_record')
                ->where('team_id', $teamId)
                ->orderByDesc('year')
                ->get()
        );

        if ($history && $history->isNotEmpty()) return $history;

        // Fallback: aggregate from career batting stats year column
        // (just years that show up in games)
        $history = $this->safeQuery(fn () =>
            DB::table('games as g')
                ->where('g.game_type', 0)
                ->where('g.played', 1)
                ->where(fn ($q) => $q->where('g.home_team', $teamId)->orWhere('g.away_team', $teamId))
                ->selectRaw("
                    YEAR(g.date) as year,
                    SUM(CASE WHEN
                        (g.home_team = ? AND g.runs1 > g.runs0) OR
                        (g.away_team = ? AND g.runs0 > g.runs1)
                    THEN 1 ELSE 0 END) as w,
                    SUM(CASE WHEN
                        (g.home_team = ? AND g.runs0 > g.runs1) OR
                        (g.away_team = ? AND g.runs1 > g.runs0)
                    THEN 1 ELSE 0 END) as l,
                    COUNT(*) as g
                ", [$teamId, $teamId, $teamId, $teamId])
                ->groupByRaw('YEAR(g.date)')
                ->orderByRaw('YEAR(g.date) DESC')
                ->get()
        );

        return $history ?? collect();
    }

    // -------------------------------------------------------------------------
    // Team Injuries
    // -------------------------------------------------------------------------

    /** Players currently on the IL / injured. */
    public function teamCurrentInjuries(int $teamId): \Illuminate\Support\Collection
    {
        // Try team_roster list_id = 2 (typically IL) first
        $injured = $this->safeQuery(fn () =>
            DB::table('team_roster as tr')
                ->join('players as p', 'tr.player_id', '=', 'p.player_id')
                ->where('tr.team_id', $teamId)
                ->where('tr.list_id', 2)
                ->select('p.*')
                ->orderBy('p.last_name')
                ->get()
        );

        if ($injured && $injured->isNotEmpty()) return $injured;

        // Fallback: players with injured flag
        return $this->safeQuery(fn () =>
            DB::table('players')
                ->where('team_id', $teamId)
                ->where('injured', 1)
                ->orderBy('last_name')
                ->get()
        ) ?? collect();
    }

    /** Injury history for all players on a team. */
    public function teamInjuryHistory(int $teamId): \Illuminate\Support\Collection
    {
        return $this->safeQuery(fn () =>
            DB::table('players_injury_history as ih')
                ->join('players as p', 'ih.player_id', '=', 'p.player_id')
                ->where('p.team_id', $teamId)
                ->select(
                    'ih.*',
                    'p.first_name', 'p.last_name', 'p.position',
                )
                ->orderByDesc('ih.date')
                ->limit(100)
                ->get()
        ) ?? collect();
    }

    // -------------------------------------------------------------------------
    // Timezones
    // -------------------------------------------------------------------------

    /**
     * Returns team_id → hours-to-add-to-get-ET for every team.
     * Derived from the home city longitude — works automatically for any
     * expansion team added to OOTP in future seasons.
     *
     * Longitude boundaries (US-centric):
     *   < -115° → Pacific  (+3 from ET)
     *   < -87.5° → Central (+1 from ET)
     *   otherwise → Eastern (0)
     *
     * Cities with longitude = 0 (bad/missing data) fall back to ET (0).
     */
    public function teamEtOffsets(): array
    {
        $rows = $this->safeQuery(fn () =>
            DB::table('teams as t')
                ->join('cities as c', 't.city_id', '=', 'c.city_id')
                ->select('t.team_id', 'c.longitude')
                ->get()
        ) ?? collect();

        $result = [];
        foreach ($rows as $r) {
            $lon = (float) $r->longitude;
            if ($lon < -115) {
                $offset = 3; // Pacific
            } elseif ($lon < -87.5) {
                $offset = 1; // Central
            } else {
                $offset = 0; // Eastern (also covers lon=0 bad data)
            }
            $result[(int) $r->team_id] = $offset;
        }
        return $result;
    }

    // -------------------------------------------------------------------------
    // Logos
    // -------------------------------------------------------------------------

    /**
     * Era-accurate logo filename for a team.
     *
     * Given the base part of logo_file_name (e.g. "atlanta_braves") and the
     * in-game season year, returns the most-era-appropriate PNG filename from
     * public/images/logos/.  Falls back to the generic "current" logo.
     *
     * Usage:  OotpService::logoForYear('atlanta_braves', 1970)
     *         → 'atlanta_braves_1969-1986.png'
     */
    public static function logoForYear(string $base, int $year): string
    {
        static $eras = [
            'atlanta_braves'         => [[1966,1968],[1969,1986],[1986,2017]],
            'baltimore_orioles'      => [[1875,1902],[1954,1965],[1966,1969],[1970,1988],[1989,1991],[1992,1994],[1995,1997],[1998,1998],[1999,2008]],
            'boston_red_sox'         => [[1908,1923],[1924,1960],[1961,1969],[1970,1975],[1976,2008]],
            'california_angels'      => [[1965,1970],[1971,1973],[1974,1985],[1986,1992],[1993,1993],[1994,1996]],
            'chicago_cubs'           => [[1903,1925],[1926,1929],[1930,1936],[1937,1939],[1940,1941],[1942,1946],[1947,1947],[1948,1956],[1957,1978],[1979,2017]],
            'chicago_white_sox'      => [[1901,1901],[1902,1917],[1918,1931],[1932,1947],[1948,1948],[1949,1959],[1960,1975],[1976,1981],[1982,1986],[1987,1990],[1991,2017]],
            'cincinnati_reds'        => [[1869,1938],[1938,1953],[1960,1967],[1968,1992],[1993,1998],[1999,2012]],
            'cleveland_guardians'    => [[1800,2021]],
            'detroit_tigers'         => [[1901,1904],[1905,1913],[1914,1917],[1918,1924],[1925,1935],[1936,1946],[1947,1956],[1957,1960],[1961,1963],[1964,1993],[1994,2005]],
            'houston_astros'         => [[1965,1976],[1977,1993],[1994,1994],[1995,1999],[2000,2012]],
            'kansas_city_royals'     => [[1969,1978],[1979,1985],[1986,1992],[1993,2001],[2002,2017],[2018,2018]],
            'los_angeles_dodgers'    => [[1958,1967],[1968,1971],[1972,1978],[1979,2011]],
            'milwaukee_brewers'      => [[1900,1901],[1970,1977],[1978,1993],[1994,1999],[2000,2019]],
            'minnesota_twins'        => [[1961,1975],[1976,1986],[1987,1993],[1994,2009]],
            'montreal_expos'         => [[1969,1991],[1992,2004]],
            'new_york_mets'          => [[1962,1980],[1981,1992],[1993,1998]],
            'new_york_yankees'       => [[1901,1945],[1946,1980]],
            'oakland_athletics'      => [[1968,1970],[1971,1981],[1982,1992]],
            'philadelphia_phillies'  => [[1900,1941],[1942,1942],[1945,1950],[1951,1969],[1970,1975],[1976,1980],[1981,1981],[1982,1991],[1992,2018]],
            'pittsburgh_pirates'     => [[1900,1957],[1958,1966],[1967,1986],[1987,1996]],
            'san_diego_padres'       => [[1969,1984],[1985,1985],[1986,1989],[1990,1990],[1991,1991],[1992,1998],[1999,2003],[2004,2010],[2011,2011],[2012,2019]],
            'san_francisco_giants'   => [[1958,1967],[1968,1972],[1973,1979],[1980,1982],[1983,1993],[1994,1999],[2000,2017]],
            'st_louis_cardinals'     => [[1900,1948],[1949,1964],[1965,1966],[1967,1997],[1998,1998]],
            'washington_senators'    => [[1901,1904],[1905,1927],[1928,1952],[1953,1956],[1957,1960],[1961,1971]],
        ];

        if (isset($eras[$base])) {
            foreach ($eras[$base] as [$from, $to]) {
                if ($year >= $from && $year <= $to) {
                    $suffix = ($from === $to) ? (string)$from : "{$from}-{$to}";
                    return "{$base}_{$suffix}.png";
                }
            }
        }

        // Fallback: generic current logo
        return "{$base}.png";
    }

    // -------------------------------------------------------------------------
    // Odds / Betting lines
    // -------------------------------------------------------------------------

    /**
     * Last-N-game W/L record per team, keyed by team_id.
     * Used for recent-form factor in odds calculation.
     */
    public function teamRecentRecords(int $n = 10): array
    {
        $rows = $this->safeQuery(fn () =>
            DB::table('games')
                ->where('played', 1)
                ->where('game_type', 0)
                ->select('home_team', 'away_team', 'runs0', 'runs1', 'date', 'game_id')
                ->orderByDesc('date')
                ->orderByDesc('game_id')
                ->limit(2000)
                ->get()
        ) ?? collect();

        $teamGames = [];
        foreach ($rows as $g) {
            $ht = (int)$g->home_team;
            $at = (int)$g->away_team;
            $teamGames[$ht][] = $g->runs1 > $g->runs0 ? 'w' : 'l';
            $teamGames[$at][] = $g->runs0 > $g->runs1 ? 'w' : 'l';
        }

        $result = [];
        foreach ($teamGames as $tid => $outcomes) {
            $recent = array_slice($outcomes, 0, $n);
            $result[$tid] = [
                'w' => count(array_filter($recent, fn ($x) => $x === 'w')),
                'l' => count(array_filter($recent, fn ($x) => $x === 'l')),
            ];
        }
        return $result;
    }

    /**
     * Average runs scored per game per team (offensive strength indicator).
     * Keyed by team_id → ['rpg' => float]
     */
    public function teamRunsPerGame(): array
    {
        $rows = $this->safeQuery(fn () =>
            DB::select("
                SELECT team_id, SUM(rf) as rf, SUM(g) as g FROM (
                    SELECT home_team AS team_id, SUM(runs1) AS rf, COUNT(*) AS g
                    FROM games WHERE played=1 AND game_type=0 GROUP BY home_team
                    UNION ALL
                    SELECT away_team AS team_id, SUM(runs0) AS rf, COUNT(*) AS g
                    FROM games WHERE played=1 AND game_type=0 GROUP BY away_team
                ) t GROUP BY team_id
            ")
        ) ?? [];

        $result = [];
        foreach ($rows as $r) {
            $g = (int)$r->g;
            $result[(int)$r->team_id] = ['rpg' => $g > 0 ? round((float)$r->rf / $g, 2) : 4.5];
        }
        return $result;
    }

    /**
     * Team batting stats (AVG and HR) for the current season — keyed by team_id.
     * Returns: ['avg' => float, 'hr' => int]
     */
    public function teamBattingStats(): array
    {
        $rows = $this->safeQuery(fn () =>
            DB::table('players_game_batting as pgb')
                ->join('games as g', 'g.game_id', '=', 'pgb.game_id')
                ->where('g.played', 1)
                ->where('pgb.level_id', 1)
                ->groupBy('pgb.team_id')
                ->selectRaw('pgb.team_id, SUM(pgb.h) as h, SUM(pgb.ab) as ab, SUM(pgb.hr) as hr')
                ->get()
        ) ?? collect();

        $result = [];
        foreach ($rows as $r) {
            $ab = (int)$r->ab;
            $result[(int)$r->team_id] = [
                'avg' => $ab > 0 ? (float)$r->h / $ab : 0.0,
                'hr'  => (int)$r->hr,
            ];
        }
        return $result;
    }

    /**
     * Compute simulated moneyline and over/under for each unplayed game.
     *
     * Algorithm factors (moneyline):
     *   - Home-field baseline (+4% edge)
     *   - Overall W% differential          (weight 0.25)
     *   - Home/Away split records           (weight 0.15)
     *   - Recent form (last 10 games)       (weight 0.10)
     *   - Starting pitcher ERA differential (weight 0.20)
     *
     * Over/Under factors:
     *   - Starter expected runs (ERA × 6 IP / 9)
     *   - Bullpen estimate (4.50 ERA × 3 IP / 9 per side)
     *   - Team offensive RPG vs league average
     *
     * Returns array keyed by game_id:
     *   ['home_ml' => '-145', 'away_ml' => '+125', 'over_under' => 9.5]
     */
    /**
     * Bullpen ERA per team (relief appearances only).
     */
    public function teamBullpenEra(): array
    {
        $rows = $this->safeQuery(fn () =>
            DB::table('players_game_pitching_stats as pgp')
                ->join('games as g', 'g.game_id', '=', 'pgp.game_id')
                ->where('g.played', 1)->where('pgp.gs', 0)
                ->groupBy('pgp.team_id')
                ->selectRaw('pgp.team_id, SUM(pgp.outs) as outs, SUM(pgp.er) as er')
                ->get()
        ) ?? collect();

        $result = [];
        foreach ($rows as $r) {
            $ip = (int)$r->outs / 3;
            $result[(int)$r->team_id] = $ip > 0 ? round((int)$r->er * 9 / $ip, 2) : 4.50;
        }
        return $result;
    }

    /**
     * Average OPS for each team's starting lineup vs the specified handedness.
     * Input: [team_id => 'rhp'|'lhp']
     */
    public function lineupOpsVsHand(array $teamHandMap): array
    {
        if (empty($teamHandMap)) return [];

        // Collect all lineup player IDs
        $lineupPlayers = []; // team_id => [player_ids]
        try {
            foreach ($teamHandMap as $teamId => $vs) {
                $players = DB::table('team_starting_lineups')
                    ->where('team_id', $teamId)->where('vs', $vs)
                    ->pluck('player_id')->toArray();
                if (!empty($players)) $lineupPlayers[$teamId] = $players;
            }
        } catch (\Exception $e) {
            return []; // table doesn't exist yet
        }

        if (empty($lineupPlayers)) return [];

        // All player IDs flat
        $allIds = array_values(array_unique(array_merge(...array_values($lineupPlayers))));

        // Get season batting stats for all these players
        $stats = DB::table('players_game_batting as pgb')
            ->join('games as g', 'g.game_id', '=', 'pgb.game_id')
            ->where('g.played', 1)
            ->whereIn('pgb.player_id', $allIds)
            ->groupBy('pgb.player_id')
            ->selectRaw('pgb.player_id, SUM(pgb.ab) as ab, SUM(pgb.h) as h,
                SUM(pgb.d) as d, SUM(pgb.t) as t, SUM(pgb.hr) as hr,
                SUM(pgb.bb) as bb, SUM(pgb.hp) as hp, SUM(pgb.sf) as sf,
                SUM(pgb.pa) as pa')
            ->get()->keyBy('player_id');

        $result = [];
        foreach ($lineupPlayers as $teamId => $playerIds) {
            $opsSum = 0; $count = 0;
            foreach ($playerIds as $pid) {
                $s = $stats[$pid] ?? null;
                if (!$s || (int)$s->ab < 1) continue;
                $ab = (int)$s->ab; $h = (int)$s->h; $bb = (int)$s->bb;
                $hp = (int)$s->hp; $sf = (int)$s->sf; $pa = (int)$s->pa ?: 1;
                $tb = $h + (int)$s->d + 2*(int)$s->t + 3*(int)$s->hr;
                $obp = ($h + $bb + $hp) / $pa;
                $slg = $tb / $ab;
                $opsSum += $obp + $slg;
                $count++;
            }
            $result[$teamId] = $count > 0 ? $opsSum / $count : 0.720;
        }
        return $result;
    }

    /**
     * Get throwing hand for player IDs.
     */
    public function playerThrows(array $playerIds): array
    {
        if (empty($playerIds)) return [];
        $rows = DB::table('players')->whereIn('player_id', $playerIds)
            ->select('player_id', 'throws')->get();
        $result = [];
        foreach ($rows as $r) {
            $result[(int)$r->player_id] = match((int)$r->throws) { 1 => 'R', 2 => 'L', default => 'R' };
        }
        return $result;
    }

    /**
     * Compute game odds: moneyline, spread, over/under.
     */
    public function computeGameOdds(
        \Illuminate\Support\Collection $games,
        array $homeAwayRecs,
        array $starterStats,
        array $recentRecs,
        array $runsPerGame,
        array $bullpenEra = [],
        array $lineupOps = [],
        array $starterHands = []
    ): array {
        $leagueEra = 4.20;
        $leagueOps = 0.720;
        $avgRpg    = 4.50;
        $result    = [];

        foreach ($games as $g) {
            if ((int)$g->played) continue;

            $homeRec = $homeAwayRecs[(int)$g->home_team] ?? [];
            $awayRec = $homeAwayRecs[(int)$g->away_team] ?? [];

            // ── Moneyline ──────────────────────────────────────────────────
            $homeProb = 0.54;

            $hw = ($homeRec['home_w'] ?? 0) + ($homeRec['road_w'] ?? 0);
            $hl = ($homeRec['home_l'] ?? 0) + ($homeRec['road_l'] ?? 0);
            $aw = ($awayRec['home_w'] ?? 0) + ($awayRec['road_w'] ?? 0);
            $al = ($awayRec['home_l'] ?? 0) + ($awayRec['road_l'] ?? 0);
            $homeWpct = ($hw + $hl) > 0 ? $hw / ($hw + $hl) : 0.500;
            $awayWpct = ($aw + $al) > 0 ? $aw / ($aw + $al) : 0.500;
            $homeProb += ($homeWpct - $awayWpct) * 0.25;

            $hhg = ($homeRec['home_w'] ?? 0) + ($homeRec['home_l'] ?? 0);
            $arg = ($awayRec['road_w'] ?? 0) + ($awayRec['road_l'] ?? 0);
            $homeHomeWpct = $hhg > 0 ? ($homeRec['home_w'] ?? 0) / $hhg : 0.500;
            $awayRoadWpct = $arg > 0 ? ($awayRec['road_w'] ?? 0) / $arg : 0.500;
            $homeProb += ($homeHomeWpct - 0.5) * 0.15;
            $homeProb -= ($awayRoadWpct - 0.5) * 0.15;

            $hr = $recentRecs[(int)$g->home_team] ?? null;
            $ar = $recentRecs[(int)$g->away_team] ?? null;
            if ($hr && ($hr['w'] + $hr['l']) > 0) {
                $homeProb += ($hr['w'] / ($hr['w'] + $hr['l']) - 0.5) * 0.10;
            }
            if ($ar && ($ar['w'] + $ar['l']) > 0) {
                $homeProb -= ($ar['w'] / ($ar['w'] + $ar['l']) - 0.5) * 0.10;
            }

            // Starter stats
            $s1 = isset($g->starter1, $starterStats[(int)$g->starter1]) ? $starterStats[(int)$g->starter1] : null;
            $s0 = isset($g->starter0, $starterStats[(int)$g->starter0]) ? $starterStats[(int)$g->starter0] : null;
            $homeEra  = ($s1 && is_numeric($s1['era']))  ? (float)$s1['era']  : $leagueEra;
            $awayEra  = ($s0 && is_numeric($s0['era']))  ? (float)$s0['era']  : $leagueEra;
            $homeWhip = ($s1 && is_numeric($s1['whip'])) ? (float)$s1['whip'] : 1.30;
            $awayWhip = ($s0 && is_numeric($s0['whip'])) ? (float)$s0['whip'] : 1.30;
            $homeProb += ($awayEra - $homeEra) * 0.025;
            $homeProb = max(0.20, min(0.80, $homeProb));

            // American odds
            if ($homeProb >= 0.50) {
                $homeRaw = -($homeProb / (1 - $homeProb)) * 100;
                $awayRaw = ((1 - $homeProb) / $homeProb) * 100;
            } else {
                $homeRaw = ((1 - $homeProb) / $homeProb) * 100;
                $awayRaw = -($homeProb / (1 - $homeProb)) * 100;
            }
            $homeML = (int)(round($homeRaw / 5) * 5);
            $awayML = (int)(round($awayRaw / 5) * 5);
            $homeMLStr = $homeML > 0 ? '+' . $homeML : (string)$homeML;
            $awayMLStr = $awayML > 0 ? '+' . $awayML : (string)$awayML;

            // ── Expected Runs Per Team ─────────────────────────────────────
            $homeRpg = $runsPerGame[(int)$g->home_team]['rpg'] ?? $avgRpg;
            $awayRpg = $runsPerGame[(int)$g->away_team]['rpg'] ?? $avgRpg;

            // Starter factors (67% of game)
            $homeStarterFactor = $homeEra / $leagueEra;
            $awayStarterFactor = $awayEra / $leagueEra;
            $homeWhipFactor = 1 + ($homeWhip - 1.30) * 0.15;
            $awayWhipFactor = 1 + ($awayWhip - 1.30) * 0.15;

            $awayVsStarter = $awayRpg * $homeStarterFactor * $homeWhipFactor * 0.67;
            $homeVsStarter = $homeRpg * $awayStarterFactor * $awayWhipFactor * 0.67;

            // Bullpen factors (33% of game)
            $homeBpEra = $bullpenEra[(int)$g->home_team] ?? 4.50;
            $awayBpEra = $bullpenEra[(int)$g->away_team] ?? 4.50;
            $awayVsBullpen = $awayRpg * ($homeBpEra / $leagueEra) * 0.33;
            $homeVsBullpen = $homeRpg * ($awayBpEra / $leagueEra) * 0.33;

            // Lineup vs handedness adjustment
            $awayLineupOps = $lineupOps[(int)$g->away_team] ?? $leagueOps;
            $homeLineupOps = $lineupOps[(int)$g->home_team] ?? $leagueOps;
            $awayLineupMult = 1 + ($awayLineupOps - $leagueOps) * 1.5;
            $homeLineupMult = 1 + ($homeLineupOps - $leagueOps) * 1.5;

            // Combine
            $awayExpected = ($awayVsStarter + $awayVsBullpen) * $awayLineupMult;
            $homeExpected = ($homeVsStarter + $homeVsBullpen) * $homeLineupMult + 0.25;

            $awayExpected = max(2.0, min(8.0, $awayExpected));
            $homeExpected = max(2.0, min(8.0, $homeExpected));

            // ── Spread & Over/Under ────────────────────────────────────────
            $spread    = round(($homeExpected - $awayExpected) * 2) / 2;
            $overUnder = round(($homeExpected + $awayExpected) * 2) / 2;
            $overUnder = max(5.5, min(14.0, $overUnder));
            $favorite  = $spread > 0 ? 'home' : ($spread < 0 ? 'away' : 'home');

            $result[(int)$g->game_id] = [
                'home_ml'    => $homeMLStr,
                'away_ml'    => $awayMLStr,
                'over_under' => $overUnder,
                'spread'     => $spread,
                'favorite'   => $favorite,
            ];
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Matchup Card Builder
    // -------------------------------------------------------------------------

    /**
     * Build matchup card data for unplayed games, ready for the x-game-matchup component.
     * Each card contains: away, home, awayStarter, homeStarter, odds, location, time, date, game object.
     */
    public function buildMatchupCards(
        \Illuminate\Support\Collection $games,
        array $starterStats,
        array $homeAwayRecs,
        array $teamLogos,
        array $teamBatting,
        array $runsPerGame,
        array $gameOdds,
        array $teamTzOffsets = [],
        array $gameStreams = []
    ): array {
        $cards = [];
        foreach ($games as $game) {
            if ((int)$game->played === 1) continue;

            $awayRec = $homeAwayRecs[(int)$game->away_team] ?? null;
            $homeRec = $homeAwayRecs[(int)$game->home_team] ?? null;
            $s0 = ((int)($game->starter0 ?? 0) > 0) ? ($starterStats[(int)$game->starter0] ?? null) : null;
            $s1 = ((int)($game->starter1 ?? 0) > 0) ? ($starterStats[(int)$game->starter1] ?? null) : null;

            // Time string
            $etOffset = $teamTzOffsets[(int)$game->home_team] ?? 0;
            if ($game->time) {
                $lh = intdiv((int)$game->time, 100) + $etOffset;
                $lm = (int)$game->time % 100;
                $ampm = $lh >= 12 ? 'PM' : 'AM';
                $h12 = $lh % 12 ?: 12;
                $timeStr = sprintf('%d:%02d %s ET', $h12, $lm, $ampm);
            } else {
                $timeStr = 'TBD';
            }

            $awayOvr = $awayRec ? ($awayRec['road_w']+$awayRec['home_w']).'-'.($awayRec['road_l']+$awayRec['home_l']) : null;
            $homeOvr = $homeRec ? ($homeRec['home_w']+$homeRec['road_w']).'-'.($homeRec['home_l']+$homeRec['road_l']) : null;
            $parkLocation = implode(', ', array_filter([$game->park_city ?? null, $game->park_state ?? null]));

            $awayBat = $teamBatting[(int)$game->away_team] ?? null;
            $homeBat = $teamBatting[(int)$game->home_team] ?? null;

            $cards[] = [
                'game_id' => (int)$game->game_id,
                'date'    => $game->date,
                'time'    => $timeStr,
                'away' => [
                    'abbr'   => $game->away_abbr ?? '',
                    'name'   => $game->away_nickname ?? $game->away_name ?? '',
                    'logo'   => $teamLogos[(int)$game->away_team] ?? null,
                    'record' => $awayOvr,
                    'rpg'    => $runsPerGame[(int)$game->away_team]['rpg'] ?? null,
                    'avg'    => $awayBat['avg'] ?? null,
                    'hr'     => $awayBat['hr'] ?? null,
                ],
                'home' => [
                    'abbr'   => $game->home_abbr ?? '',
                    'name'   => $game->home_nickname ?? $game->home_name ?? '',
                    'logo'   => $teamLogos[(int)$game->home_team] ?? null,
                    'record' => $homeOvr,
                    'rpg'    => $runsPerGame[(int)$game->home_team]['rpg'] ?? null,
                    'avg'    => $homeBat['avg'] ?? null,
                    'hr'     => $homeBat['hr'] ?? null,
                ],
                'awayStarter' => $game->starter0_name
                    ? ['name' => $game->starter0_name, 'w' => $s0['w'] ?? 0, 'l' => $s0['l'] ?? 0, 'era' => $s0['era'] ?? '-.--', 'k' => $s0['k'] ?? 0]
                    : null,
                'homeStarter' => $game->starter1_name
                    ? ['name' => $game->starter1_name, 'w' => $s1['w'] ?? 0, 'l' => $s1['l'] ?? 0, 'era' => $s1['era'] ?? '-.--', 'k' => $s1['k'] ?? 0]
                    : null,
                'odds'     => $gameOdds[(int)$game->game_id] ?? null,
                'location' => implode(', ', array_filter([$game->park_name ?? null, $parkLocation ?: null])),
                'stream'   => (bool)($gameStreams[(int)$game->game_id] ?? null),
                'game'     => $game,
            ];
        }
        return $cards;
    }

    // -------------------------------------------------------------------------
    // Featured Matchups (preview page)
    // -------------------------------------------------------------------------

    /**
     * Build the top featured matchups for the next 7 days.
     * Returns an array of matchup data sorted by interest score, limited to $limit.
     */
    public function featuredMatchups(int $limit = 12): array
    {
        // ── Date range ──────────────────────────────────────────────────
        $firstUpcoming = DB::table('games')->where('played', 0)->orderBy('date')->value('date');
        if (!$firstUpcoming) return [];
        $lastUpcoming = date('Y-m-d', strtotime($firstUpcoming . ' +6 days'));
        $lastPlayed   = DB::table('games')->where('played', 1)->orderByDesc('date')->value('date');

        // ── Team data (records, batting, pitching, parks) ───────────────
        $teamLogos = DB::table('teams')->whereNotNull('logo_file_name')
            ->pluck('logo_file_name', 'team_id')->map(fn($f) => $f ?: null)->toArray();
        $seasonYear = $this->seasonYear() ?? (int)date('Y', strtotime($firstUpcoming));

        $teamRows = DB::table('teams as tm')
            ->leftJoin('parks as pk', 'pk.park_id', '=', 'tm.park_id')
            ->leftJoin('cities as ct', 'ct.city_id', '=', 'tm.city_id')
            ->leftJoin('states as st', 'st.state_id', '=', 'ct.state_id')
            ->where('tm.level', 1)
            ->get(['tm.team_id','tm.name','tm.nickname','tm.abbr','tm.division_id','tm.sub_league_id',
                   'tm.background_color_id','tm.text_color_id',
                   'pk.name as park_name','ct.name as park_city','st.name as park_state']);

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

        $rpgRows = DB::select("
            SELECT team_id, SUM(rf) as rf, SUM(g) as g FROM (
                SELECT away_team AS team_id, SUM(runs0) AS rf, COUNT(*) AS g FROM games WHERE played=1 AND game_type=0 GROUP BY away_team
                UNION ALL
                SELECT home_team AS team_id, SUM(runs1) AS rf, COUNT(*) AS g FROM games WHERE played=1 AND game_type=0 GROUP BY home_team
            ) t GROUP BY team_id
        ");
        $rpgByTeam = [];
        foreach ($rpgRows as $r) {
            $g = (int)$r->g;
            $rpgByTeam[(int)$r->team_id] = $g > 0 ? round((float)$r->rf / $g, 1) : 0.0;
        }

        $teamBatting = DB::table('players_game_batting as pgb')
            ->join('games as g', 'g.game_id', '=', 'pgb.game_id')
            ->where('g.played', 1)->where('pgb.level_id', 1)
            ->groupBy('pgb.team_id')
            ->selectRaw('pgb.team_id, SUM(pgb.h) as h, SUM(pgb.ab) as ab, SUM(pgb.hr) as hr')
            ->get()->keyBy('team_id');

        $teams = [];
        foreach ($teamRows as $t) {
            $tid = (int)$t->team_id;
            $wl  = $wlByTeam[$tid] ?? ['w' => 0, 'l' => 0];
            $w = $wl['w']; $l = $wl['l'];
            $bat = $teamBatting[$tid] ?? null;
            $logoBase = pathinfo($teamLogos[$tid] ?? '', PATHINFO_FILENAME);
            $teams[$tid] = [
                'name' => $t->name, 'nickname' => $t->nickname, 'abbr' => $t->abbr,
                'w' => $w, 'l' => $l, 'pct' => ($w+$l) > 0 ? $w/($w+$l) : 0,
                'division_id' => (int)$t->division_id,
                'bgColor' => $t->background_color_id ?? '#1f2937',
                'logo' => self::logoForYear($logoBase, $seasonYear),
                'rpg' => $rpgByTeam[$tid] ?? 0.0,
                'teamAvg' => ($bat && $bat->ab > 0) ? $bat->h / $bat->ab : 0.0,
                'teamHr' => $bat ? (int)$bat->hr : 0,
                'park_name' => $t->park_name, 'park_city' => $t->park_city, 'park_state' => $t->park_state,
            ];
        }

        // ── Upcoming games ──────────────────────────────────────────────
        $upcomingGames = DB::table('games as g')
            ->where('g.played', 0)->whereBetween('g.date', [$firstUpcoming, $lastUpcoming])
            ->select('g.game_id','g.date','g.time','g.away_team','g.home_team')
            ->orderBy('g.date')->orderBy('g.time')->get()
            ->map(fn($g) => (object)((array)$g + ['starter0'=>0,'starter1'=>0,'starter0_name'=>null,'starter1_name'=>null]));

        // ── Rotation cycling ────────────────────────────────────────────
        $rotationRows = DB::table('players_game_pitching_stats as pgp')
            ->join('games as g', 'g.game_id', '=', 'pgp.game_id')
            ->join('players as p', 'p.player_id', '=', 'pgp.player_id')
            ->where('g.played', 1)->where('pgp.gs', 1)->where('pgp.level_id', 1)
            ->groupBy('pgp.player_id', 'pgp.team_id', 'p.first_name', 'p.last_name')
            ->selectRaw("pgp.player_id, pgp.team_id, CONCAT(LEFT(p.first_name,1),'. ',p.last_name) as name, MAX(g.date) as last_start")
            ->get();

        $teamRotation = [];
        foreach ($rotationRows->groupBy('team_id') as $tid => $pitchers) {
            $teamRotation[(int)$tid] = $pitchers->sortBy('last_start')->values()
                ->map(fn($r) => ['player_id' => (int)$r->player_id, 'name' => $r->name])->toArray();
        }

        $projected = DB::table('projected_starting_pitchers')->get()->keyBy('team_id');
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

        foreach ($upcomingGames as $g) {
            foreach ([(int)$g->away_team => ['starter0','starter0_name'], (int)$g->home_team => ['starter1','starter1_name']] as $tid => [$spF, $nF]) {
                if (empty($teamRotation[$tid])) continue;
                $rot = $teamRotation[$tid]; $pos = $rotationPos[$tid] ?? 0;
                $sp = $rot[$pos % count($rot)];
                $g->$spF = $sp['player_id']; $g->$nF = $sp['name'];
                $rotationPos[$tid] = ($pos + 1) % count($rot);
            }
        }

        // ── Starter stats ───────────────────────────────────────────────
        $starterIds = array_values(array_unique(array_filter(array_merge(
            $upcomingGames->pluck('starter0')->toArray(), $upcomingGames->pluck('starter1')->toArray()
        ), fn($v) => (int)$v > 0)));

        $starterStats = [];
        if ($starterIds) {
            $spRows = DB::table('players_game_pitching_stats as pgp')
                ->join('games as g', 'g.game_id', '=', 'pgp.game_id')
                ->whereIn('pgp.player_id', $starterIds)->where('g.played', 1)->where('pgp.level_id', 1)
                ->groupBy('pgp.player_id')
                ->selectRaw('pgp.player_id, SUM(pgp.w) as w, SUM(pgp.l) as l, SUM(pgp.k) as k, SUM(pgp.outs) as outs, SUM(pgp.er) as er, SUM(pgp.ha) as ha, SUM(pgp.bb) as bb')
                ->get();
            foreach ($spRows as $r) {
                $ip = $r->outs / 3;
                $starterStats[(int)$r->player_id] = [
                    'w' => (int)$r->w, 'l' => (int)$r->l, 'k' => (int)$r->k,
                    'era' => $ip > 0 ? number_format(($r->er / $ip) * 9, 2) : '-.--',
                    'whip' => $ip > 0 ? number_format(($r->ha + $r->bb) / $ip, 2) : '-.--',
                ];
            }
        }

        // ── Odds data ───────────────────────────────────────────────────
        $bullpenEra   = $this->teamBullpenEra();
        $starterHands = $this->playerThrows($starterIds);
        $lineupHandMap = [];
        foreach ($upcomingGames as $g) {
            $lineupHandMap[(int)$g->away_team] = ($starterHands[(int)($g->starter1 ?? 0)] ?? 'R') === 'R' ? 'rhp' : 'lhp';
            $lineupHandMap[(int)$g->home_team] = ($starterHands[(int)($g->starter0 ?? 0)] ?? 'R') === 'R' ? 'rhp' : 'lhp';
        }
        $lineupOps = $this->lineupOpsVsHand($lineupHandMap);

        // ── Win streaks ─────────────────────────────────────────────────
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
            if (!isset($teamBuf[$tid])) { $teamBuf[$tid] = ['type' => $type, 'streak' => 1]; }
            elseif ($type === $teamBuf[$tid]['type']) { $teamBuf[$tid]['streak']++; }
            else { $winStreakByTeam[$tid] = $teamBuf[$tid]['type'] === 'W' ? $teamBuf[$tid]['streak'] : -$teamBuf[$tid]['streak']; }
        }
        foreach ($teamBuf as $tid => $s) { $winStreakByTeam[$tid] ??= ($s['type'] === 'W' ? $s['streak'] : -$s['streak']); }

        // ── Hitting streaks, stars, rivalries ────────────────────────────
        $streaksByTeam = [];
        $allStreakRows = DB::table('players_streak as ps')
            ->join('players as p', 'p.player_id', '=', 'ps.player_id')
            ->join('team_roster as tr', 'tr.player_id', '=', 'p.player_id')
            ->join('teams as t', 't.team_id', '=', 'tr.team_id')
            ->where('ps.streak_id', 9)->where('ps.has_ended', 0)->where('ps.value', '>=', 8)->where('t.level', 1)
            ->orderByDesc('ps.value')->get(['p.player_id','p.first_name','p.last_name','tr.team_id','ps.value'])->unique('player_id');
        foreach ($allStreakRows as $sr) {
            $streaksByTeam[(int)$sr->team_id][] = ['name' => $sr->first_name.' '.$sr->last_name, 'value' => (int)$sr->value];
        }

        $seasonStats = DB::table('players_career_batting_stats as pcs')
            ->join('players as p', 'p.player_id', '=', 'pcs.player_id')
            ->join('team_roster as tr', 'tr.player_id', '=', 'p.player_id')
            ->join('teams as t', 't.team_id', '=', 'tr.team_id')
            ->where('pcs.split_id', 1)->where('pcs.year', $seasonYear)->where('t.level', 1)->where('pcs.ab', '>=', 100)
            ->get(['p.player_id','p.first_name','p.last_name','tr.team_id','pcs.hr','pcs.rbi'])->unique('player_id');

        $hrRanks  = $seasonStats->sortByDesc('hr')->values()->take(5)->pluck('player_id')->flip()->toArray();
        $rbiRanks = $seasonStats->sortByDesc('rbi')->values()->take(5)->pluck('player_id')->flip()->toArray();
        $starsByTeam = [];
        foreach ($seasonStats as $s) {
            $labels = [];
            if (isset($hrRanks[$s->player_id]))  $labels[] = 'Top-5 HR ('.$s->hr.')';
            if (isset($rbiRanks[$s->player_id])) $labels[] = 'Top-5 RBI ('.$s->rbi.')';
            if ($labels) $starsByTeam[(int)$s->team_id][] = ['name' => $s->first_name.' '.$s->last_name, 'labels' => $labels];
        }

        $rivalryPairs = [];
        foreach (DB::table('rivalries')->where('approved', true)->get() as $rv) {
            $key = min((int)$rv->team0_id, (int)$rv->team1_id).'-'.max((int)$rv->team0_id, (int)$rv->team1_id);
            $rivalryPairs[$key] = true;
        }

        // ── Enrich game objects with team fields for buildMatchupCards ──
        foreach ($upcomingGames as $g) {
            $a = $teams[(int)$g->away_team] ?? []; $h = $teams[(int)$g->home_team] ?? [];
            $g->away_abbr = $a['abbr'] ?? ''; $g->away_name = $a['name'] ?? ''; $g->away_nickname = $a['nickname'] ?? '';
            $g->home_abbr = $h['abbr'] ?? ''; $g->home_name = $h['name'] ?? ''; $g->home_nickname = $h['nickname'] ?? '';
            $g->park_name = $h['park_name'] ?? null; $g->park_city = $h['park_city'] ?? null; $g->park_state = $h['park_state'] ?? null;
            $g->played = 0;
        }

        // Build team logos map
        $teamLogoMap = [];
        foreach ($teams as $tid => $t) $teamLogoMap[$tid] = $t['logo'] ?? null;

        // Build home/away records from W/L
        $homeAwayRecs = [];
        foreach ($teams as $tid => $t) {
            $homeAwayRecs[$tid] = ['home_w' => $t['w'], 'home_l' => $t['l'], 'road_w' => $t['w'], 'road_l' => $t['l']];
        }
        // Use actual home/away if available
        $realHARecs = $this->teamHomeAwayRecords();
        foreach ($realHARecs as $tid => $rec) $homeAwayRecs[$tid] = $rec;

        $rpg = []; foreach ($teams as $tid => $t) $rpg[$tid] = ['rpg' => $t['rpg']];
        $bat = []; foreach ($teams as $tid => $t) $bat[$tid] = ['avg' => $t['teamAvg'], 'hr' => $t['teamHr']];

        // Compute odds
        $gameOdds = $this->computeGameOdds($upcomingGames, $homeAwayRecs, $starterStats, $this->teamRecentRecords(10), $rpg, $bullpenEra, $lineupOps, $starterHands);

        // Build cards using shared method
        $cards = $this->buildMatchupCards($upcomingGames, $starterStats, $homeAwayRecs, $teamLogoMap, $bat, $rpg, $gameOdds);

        // ── Score each card for ranking ──────────────────────────────────
        $maxWins = max(array_column($teams, 'w')) * 2 ?: 1;

        foreach ($cards as &$card) {
            $awayId = (int)$card['game']->away_team; $homeId = (int)$card['game']->home_team;
            $away = $teams[$awayId] ?? null; $home = $teams[$homeId] ?? null;
            if (!$away || !$home) { $card['score'] = 0; continue; }

            $pctDiff   = abs($away['pct'] - $home['pct']);
            $closeness = max(0, 1 - ($pctDiff * 4));
            $quality   = ($away['w'] + $home['w']) / $maxWins;

            $starScore = 0;
            foreach ([$awayId, $homeId] as $tid) foreach ($starsByTeam[$tid] ?? [] as $_) $starScore += 0.4;
            $starScore = min(1, $starScore);

            $streakScore = 0;
            foreach ([$awayId, $homeId] as $tid) foreach ($streaksByTeam[$tid] ?? [] as $str) $streakScore += min(1, $str['value']/30) * 0.5;
            $streakScore = min(1, $streakScore);

            $awayMom = $winStreakByTeam[$awayId] ?? 0; $homeMom = $winStreakByTeam[$homeId] ?? 0;
            $momentum = (($awayMom > 0 && $homeMom < 0) || ($awayMom < 0 && $homeMom > 0)) ? min(1, (abs($awayMom)+abs($homeMom))/10) : 0;

            $pitchScore = 0.5;
            $eraVals = array_filter([
                isset($card['awayStarter']['era']) && is_numeric($card['awayStarter']['era']) ? (float)$card['awayStarter']['era'] : null,
                isset($card['homeStarter']['era']) && is_numeric($card['homeStarter']['era']) ? (float)$card['homeStarter']['era'] : null,
            ], fn($v) => $v !== null);
            if ($eraVals) $pitchScore = max(0, min(1, (5.5 - array_sum($eraVals)/count($eraVals)) / 4.0));

            $rivalryKey = min($awayId,$homeId).'-'.max($awayId,$homeId);
            $isRivalry  = isset($rivalryPairs[$rivalryKey]);

            $card['score'] = ($closeness*0.25) + ($quality*0.20) + ($pitchScore*0.15) + (($isRivalry?1:0)*0.15) + ($starScore*0.10) + ($streakScore*0.10) + ($momentum*0.05);

            // Tags
            $tags = [];
            if ($isRivalry) $tags[] = ['type'=>'rivalry','text'=>'Rivalry'];
            if ($pctDiff <= 0.05) $tags[] = ['type'=>'matchup','text'=>'Even matchup'];
            if ($away['pct'] >= 0.580 && $home['pct'] >= 0.580) $tags[] = ['type'=>'elite','text'=>'Elite vs Elite'];
            if ($away['division_id'] === $home['division_id']) $tags[] = ['type'=>'div','text'=>'Division game'];
            foreach ([$awayId,$homeId] as $tid) {
                foreach ($streaksByTeam[$tid] ?? [] as $str) if ($str['value'] >= 15) $tags[] = ['type'=>'streak','text'=>$str['name'].' — '.$str['value'].'-game hit streak'];
                foreach ($starsByTeam[$tid] ?? [] as $star) $tags[] = ['type'=>'star','text'=>$star['name'].' — '.implode(', ',$star['labels'])];
            }
            if (abs($awayMom) >= 3) $tags[] = ['type'=>'momentum','text'=>$away['abbr'].' '.($awayMom>0?'W':'L').abs($awayMom)];
            if (abs($homeMom) >= 3) $tags[] = ['type'=>'momentum','text'=>$home['abbr'].' '.($homeMom>0?'W':'L').abs($homeMom)];
            $card['tags'] = $tags;

            // Add momentum to away/home for component
            $card['away']['momentum'] = $awayMom;
            $card['home']['momentum'] = $homeMom;
        }
        unset($card);

        usort($cards, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
        return array_slice($cards, 0, $limit);
    }

    // -------------------------------------------------------------------------
    // Schedule
    // -------------------------------------------------------------------------

    /**
     * Games for a specific date as a flat Collection, sorted by game time.
     */
    public function scheduleByDate(string $date, int $teamId = 0): \Illuminate\Support\Collection
    {
        $rows = $this->safeQuery(fn () =>
            DB::table('games as g')
                ->join('teams as ht', 'g.home_team', '=', 'ht.team_id')
                ->join('teams as at', 'g.away_team', '=', 'at.team_id')
                ->whereIn('g.game_type', [0, 3, 4])
                ->where('g.date', $date)
                ->when($teamId > 0,
                    fn ($q) => $q->where(fn ($q2) => $q2->where('g.home_team', $teamId)->orWhere('g.away_team', $teamId)),
                    fn ($q) => $q->where('ht.level', 1)->where('at.level', 1)
                )
                ->leftJoin('parks as pk', 'ht.park_id', '=', 'pk.park_id')
                ->leftJoin('cities as ht_city', 'ht.city_id', '=', 'ht_city.city_id')
                ->leftJoin('states as ht_state', 'ht_city.state_id', '=', 'ht_state.state_id')
                ->select(
                    'g.game_id', 'g.home_team', 'g.away_team',
                    'g.date', 'g.time', 'g.played', 'g.innings',
                    'g.runs0', 'g.runs1', 'g.hits0', 'g.hits1', 'g.errors0', 'g.errors1',
                    'g.game_type', 'g.starter0', 'g.starter1',
                    'g.winning_pitcher', 'g.losing_pitcher', 'g.save_pitcher',
                    'ht.abbr as home_abbr', 'ht.name as home_name', 'ht.nickname as home_nickname',
                    'ht.logo_file_name as home_logo',
                    'at.abbr as away_abbr', 'at.name as away_name', 'at.nickname as away_nickname',
                    'at.logo_file_name as away_logo',
                    'pk.name as park_name',
                    'ht_city.name as park_city',
                    'ht_state.name as park_state',
                )
                ->orderBy('g.time')
                ->get()
        ) ?? collect();

        // Batch-resolve player names (starters, W/L/SV) in one query instead of 5 joins
        $playerIds = [];
        foreach ($rows as $g) {
            foreach (['starter0','starter1','winning_pitcher','losing_pitcher','save_pitcher'] as $col) {
                if ((int)($g->$col ?? 0) > 0) $playerIds[] = (int)$g->$col;
            }
        }
        $playerNames = $playerIds
            ? (DB::table('players')->whereIn('player_id', array_unique($playerIds))
                ->select('player_id', DB::raw("CONCAT(LEFT(first_name,1),'. ',last_name) as full_name"))
                ->get()->pluck('full_name', 'player_id')->toArray())
            : [];

        foreach ($rows as $g) {
            $g->starter0_name = $playerNames[(int)($g->starter0 ?? 0)] ?? null;
            $g->starter1_name = $playerNames[(int)($g->starter1 ?? 0)] ?? null;
            $g->wp_name       = $playerNames[(int)($g->winning_pitcher ?? 0)] ?? null;
            $g->lp_name       = $playerNames[(int)($g->losing_pitcher ?? 0)] ?? null;
            $g->svp_name      = $playerNames[(int)($g->save_pitcher ?? 0)] ?? null;
        }

        return $rows;
    }

    /**
     * Games for a given month/year, indexed by day-of-month.
     * If $teamId > 0, only returns games involving that team.
     * Only includes level-1 (MLB) teams when no team filter.
     */
    public function scheduleByMonth(int $month, int $year, int $teamId = 0): array
    {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = date('Y-m-t', strtotime($start));

        $rows = $this->safeQuery(fn () =>
            DB::table('games as g')
                ->join('teams as ht', 'g.home_team', '=', 'ht.team_id')
                ->join('teams as at', 'g.away_team', '=', 'at.team_id')
                ->whereIn('g.game_type', [0, 3, 4])
                ->whereBetween('g.date', [$start, $end])
                ->when($teamId > 0,
                    fn ($q) => $q->where(fn ($q2) => $q2->where('g.home_team', $teamId)->orWhere('g.away_team', $teamId)),
                    fn ($q) => $q->where('ht.level', 1)->where('at.level', 1)
                )
                ->leftJoin('players as sp0', 'g.starter0', '=', 'sp0.player_id')
                ->leftJoin('players as sp1', 'g.starter1', '=', 'sp1.player_id')
                ->leftJoin('parks as pk', 'ht.park_id', '=', 'pk.park_id')
                ->leftJoin('cities as ht_city', 'ht.city_id', '=', 'ht_city.city_id')
                ->leftJoin('states as ht_state', 'ht_city.state_id', '=', 'ht_state.state_id')
                ->select(
                    'g.game_id', 'g.home_team', 'g.away_team',
                    'g.date', 'g.time', 'g.played', 'g.innings',
                    'g.runs0', 'g.runs1', 'g.game_type',
                    'g.starter0', 'g.starter1',
                    'ht.abbr as home_abbr', 'ht.name as home_name', 'ht.nickname as home_nickname', 'ht.logo_file_name as home_logo',
                    'at.abbr as away_abbr', 'at.name as away_name', 'at.nickname as away_nickname', 'at.logo_file_name as away_logo',
                    'pk.name as park_name',
                    'ht_city.name as park_city',
                    'ht_state.name as park_state',
                    DB::raw("CONCAT(sp0.first_name,' ',sp0.last_name) as starter0_name"),
                    DB::raw("CONCAT(sp1.first_name,' ',sp1.last_name) as starter1_name"),
                )
                ->orderBy('g.date')
                ->orderBy('g.time')
                ->get()
        ) ?? collect();

        // For unplayed games, fill missing starters from projected_starting_pitchers.
        // starter_0 is always the team's next scheduled starter.
        $projected = $this->safeQuery(fn () =>
            DB::table('projected_starting_pitchers')->get()->keyBy('team_id')
        ) ?? collect();

        // Fetch player names for any projected starters we'll need
        $projIds = [];
        foreach ($rows as $game) {
            if (!$game->played) {
                if (!$game->starter0 && isset($projected[$game->away_team])) $projIds[] = $projected[$game->away_team]->starter_0;
                if (!$game->starter1 && isset($projected[$game->home_team])) $projIds[] = $projected[$game->home_team]->starter_0;
            }
        }
        $projPlayers = $projIds
            ? $this->safeQuery(fn () =>
                DB::table('players')->whereIn('player_id', array_unique($projIds))
                    ->select('player_id', DB::raw("CONCAT(first_name,' ',last_name) as full_name"))
                    ->get()->keyBy('player_id')
              ) ?? collect()
            : collect();

        $byDate = [];
        foreach ($rows as $game) {
            if (!$game->played) {
                if (!$game->starter0 && isset($projected[$game->away_team])) {
                    $pid = $projected[$game->away_team]->starter_0;
                    $game->starter0      = $pid;
                    $game->starter0_name = $projPlayers[$pid]->full_name ?? null;
                }
                if (!$game->starter1 && isset($projected[$game->home_team])) {
                    $pid = $projected[$game->home_team]->starter_0;
                    $game->starter1      = $pid;
                    $game->starter1_name = $projPlayers[$pid]->full_name ?? null;
                }
            }
            $day = (int) date('j', strtotime($game->date));
            $byDate[$day][] = $game;
        }

        return $byDate;
    }

    /**
     * Season pitching stats for a list of player_ids — keyed by player_id.
     */
    public function starterSeasonStats(array $playerIds, int $year): array
    {
        if (empty($playerIds)) return [];
        $rows = $this->safeQuery(fn () =>
            DB::table('players_game_pitching_stats as pgp')
                ->join('games as g', 'g.game_id', '=', 'pgp.game_id')
                ->whereIn('pgp.player_id', $playerIds)
                ->where('g.played', 1)
                ->where('pgp.level_id', 1)
                ->groupBy('pgp.player_id')
                ->selectRaw('pgp.player_id,
                    SUM(pgp.w) as w, SUM(pgp.l) as l, SUM(pgp.s) as sv,
                    SUM(pgp.k) as k, SUM(pgp.outs) as outs,
                    SUM(pgp.er) as er, SUM(pgp.ha) as ha, SUM(pgp.bb) as bb')
                ->get()
        ) ?? collect();

        $result = [];
        foreach ($rows as $r) {
            $outs = (int)$r->outs;
            $ip   = $outs / 3;
            $ha   = (int)$r->ha;
            $bb   = (int)$r->bb;
            $result[(int)$r->player_id] = [
                'w'    => (int)$r->w,
                'l'    => (int)$r->l,
                'sv'   => (int)$r->sv,
                'k'    => (int)$r->k,
                'era'  => $ip > 0 ? number_format(((int)$r->er / $ip) * 9, 2) : '-.--',
                'whip' => $ip > 0 ? number_format(($ha + $bb) / $ip, 2) : '-.--',
            ];
        }
        return $result;
    }

    /**
     * Season batting stats for a list of player_ids — keyed by player_id.
     * Returns AVG, OBP, SLG, OPS.
     */
    public function playerSeasonBatStats(array $playerIds, int $year): array
    {
        if (empty($playerIds)) return [];
        $rows = $this->safeQuery(fn () =>
            DB::table('players_career_batting_stats')
                ->whereIn('player_id', $playerIds)
                ->where('year', $year)
                ->where('split_id', 1)
                ->select('player_id',
                    DB::raw('SUM(ab) as ab'), DB::raw('SUM(h) as h'),
                    DB::raw('SUM(d) as d'),   DB::raw('SUM(t) as t'),
                    DB::raw('SUM(hr) as hr'),  DB::raw('SUM(bb) as bb'),
                    DB::raw('SUM(hp) as hp'),  DB::raw('SUM(sf) as sf'),
                    DB::raw('SUM(sb) as sb'),  DB::raw('SUM(cs) as cs'))
                ->groupBy('player_id')
                ->get()
        ) ?? collect();

        $result = [];
        foreach ($rows as $r) {
            $ab  = (int)$r->ab;
            $h   = (int)$r->h;
            $bb  = (int)$r->bb;
            $hp  = (int)$r->hp;
            $sf  = (int)$r->sf;
            $tb  = $h + (int)$r->d + 2*(int)$r->t + 3*(int)$r->hr;
            $avg = $ab > 0 ? $h / $ab : 0;
            $obp = ($ab + $bb + $hp + $sf) > 0 ? ($h + $bb + $hp) / ($ab + $bb + $hp + $sf) : 0;
            $slg = $ab > 0 ? $tb / $ab : 0;
            $result[(int)$r->player_id] = [
                'avg' => number_format($avg, 3),
                'obp' => number_format($obp, 3),
                'slg' => number_format($slg, 3),
                'ops' => number_format($obp + $slg, 3),
                'sb'  => (int)$r->sb,
                'cs'  => (int)$r->cs,
            ];
        }
        return $result;
    }

    /**
     * Season pitching ERA through a specific game date for a set of player IDs.
     * Sums ER and outs from all game appearances up to and including the given date.
     * Returns: [player_id => ['era' => '3.45']]
     */
    public function playerSeasonPitchStats(array $playerIds, int $year, string $throughDate = null): array
    {
        if (empty($playerIds)) return [];
        $rows = $this->safeQuery(fn () =>
            DB::table('players_game_pitching_stats as ps')
                ->join('games as g', 'g.game_id', '=', 'ps.game_id')
                ->whereIn('ps.player_id', $playerIds)
                ->where('g.played', 1)
                ->where('g.game_type', 0)
                ->whereRaw('YEAR(g.date) = ?', [$year])
                ->when($throughDate, fn($q) => $q->where('g.date', '<=', $throughDate))
                ->select('ps.player_id',
                    DB::raw('SUM(ps.outs) as outs'), DB::raw('SUM(ps.er) as er'),
                    DB::raw('SUM(ps.w) as w'), DB::raw('SUM(ps.l) as l'),
                    DB::raw('SUM(ps.s) as s'))
                ->groupBy('ps.player_id')
                ->get()
        ) ?? collect();

        $result = [];
        foreach ($rows as $r) {
            $outs = (int)$r->outs;
            $ip   = $outs / 3;
            $era  = $ip > 0 ? ((int)$r->er / $ip) * 9 : 0;
            $result[(int)$r->player_id] = [
                'era' => number_format($era, 2),
                'w'   => (int)$r->w,
                'l'   => (int)$r->l,
                's'   => (int)$r->s,
            ];
        }
        return $result;
    }

    /**
     * Parse game_logs to find per-player errors for each team.
     * Returns: [team_id => [player_id => error_count]]
     * Also returns player name info keyed by player_id.
     */
    public function gameErrors(int $gameId, int $awayTeamId, int $homeTeamId): array
    {
        // Build position → first player map per team (fielding positions 1-9 only)
        $batters = $this->safeQuery(fn () =>
            DB::table('players_game_batting as b')
                ->join('players as p', 'b.player_id', '=', 'p.player_id')
                ->where('b.game_id', $gameId)
                ->where('b.position', '>', 0)
                ->select('b.team_id', 'b.position', 'b.player_id', 'b.stint',
                         'p.first_name', 'p.last_name')
                ->orderBy('b.stint')
                ->get()
        ) ?? collect();

        $posMap = [];  // [team_id][position] = first player object
        foreach ($batters as $b) {
            $tid = (int)$b->team_id;
            $pos = (int)$b->position;
            if (!isset($posMap[$tid][$pos])) {
                $posMap[$tid][$pos] = $b;
            }
        }

        // Parse game_logs: top-of-inning → home team fielding; bottom → away fielding
        $logs = $this->safeQuery(fn () =>
            DB::table('game_logs')
                ->where('game_id', $gameId)
                ->orderBy('line')
                ->pluck('text')
        ) ?? collect();

        $fieldingTeam = null;
        $counts = [];   // [team_id][player_id] = error count
        $dps    = [];   // [team_id] = count
        foreach ($logs as $text) {
            if (preg_match('/^Top of the \d/i', $text))       $fieldingTeam = $homeTeamId;
            elseif (preg_match('/^Bottom of the \d/i', $text)) $fieldingTeam = $awayTeamId;

            if ($fieldingTeam && preg_match('/Reached on error, E(\d)/i', $text, $m)) {
                $pos    = (int)$m[1];
                $player = $posMap[$fieldingTeam][$pos] ?? null;
                if ($player) {
                    $pid = (int)$player->player_id;
                    $counts[$fieldingTeam][$pid] = ($counts[$fieldingTeam][$pid] ?? 0) + 1;
                }
            }

            if ($fieldingTeam && preg_match('/double play/i', $text)) {
                $dps[$fieldingTeam] = ($dps[$fieldingTeam] ?? 0) + 1;
            }
        }

        return ['counts' => $counts, 'posMap' => $posMap, 'dps' => $dps];
    }

    /**
     * Season fielding error totals for a list of player_ids — keyed by player_id.
     */
    public function playerSeasonErrorTotals(array $playerIds, int $year): array
    {
        if (empty($playerIds)) return [];
        $rows = $this->safeQuery(fn () =>
            DB::table('players_career_fielding_stats')
                ->whereIn('player_id', $playerIds)
                ->where('year', $year)
                ->where('split_id', 0)
                ->select('player_id', DB::raw('SUM(e) as e'))
                ->groupBy('player_id')
                ->get()
        ) ?? collect();

        $result = [];
        foreach ($rows as $r) {
            $result[(int)$r->player_id] = (int)$r->e;
        }
        return $result;
    }

    /**
     * Double play chains per fielding team, e.g. [team_id => ['Mincher-Campaneris-Mincher', ...]]
     * Parses game_logs for "double play, X-X-X" and maps position numbers to player last names.
     */
    public function gameDoublePlays(int $gameId, int $awayTeamId, int $homeTeamId): array
    {
        // Position → last name map per team (last player at each position handles subs)
        $posMap = [$awayTeamId => [], $homeTeamId => []];
        $batRows = $this->safeQuery(fn () =>
            DB::table('players_game_batting as b')
                ->join('players as p', 'p.player_id', '=', 'b.player_id')
                ->where('b.game_id', $gameId)
                ->where('b.position', '>', 0)
                ->select('b.team_id', 'b.position', 'p.last_name')
                ->get()
        ) ?? collect();
        foreach ($batRows as $r) {
            $posMap[(int)$r->team_id][(int)$r->position] = $r->last_name;
        }

        $logs = $this->safeQuery(fn () =>
            DB::table('game_logs')
                ->where('game_id', $gameId)
                ->whereIn('type', [1, 3])
                ->orderBy('line')
                ->select('type', 'text')
                ->get()
        ) ?? collect();

        $battingTeam = null;
        $chains = [$awayTeamId => [], $homeTeamId => []];

        foreach ($logs as $log) {
            if ($log->type == 1) {
                $battingTeam = stripos($log->text, 'Top of the') !== false ? $awayTeamId : $homeTeamId;
                continue;
            }
            if (!preg_match('/double play[^,]*,\s*([\d][-\d]+)/i', strip_tags($log->text), $m)) continue;
            if ($battingTeam === null) continue;
            $fieldingTeam = ($battingTeam === $awayTeamId) ? $homeTeamId : $awayTeamId;
            $parts = array_map(
                fn($pos) => $posMap[$fieldingTeam][(int)$pos] ?? '?',
                explode('-', $m[1])
            );
            $chains[$fieldingTeam][] = implode('-', $parts);
        }

        return $chains;
    }

    /**
     * Team LOB (left on base) for a game, parsed from game_logs type=4 inning-end summaries.
     * Returns [away_team_id => lob, home_team_id => lob]
     */
    public function gameLOB(int $gameId, int $awayTeamId, int $homeTeamId): array
    {
        $logs = $this->safeQuery(fn () =>
            DB::table('game_logs')
                ->where('game_id', $gameId)
                ->where('type', 4)
                ->orderBy('line')
                ->select('text')
                ->get()
        ) ?? collect();

        $lob = [$awayTeamId => 0, $homeTeamId => 0];
        foreach ($logs as $row) {
            $text = $row->text;
            if (!preg_match('/(\d+) left on base/', $text, $m)) continue;
            $count = (int)$m[1];
            // "Top of the Nth over" → away team was batting
            // "Bottom of the Nth over" → home team was batting
            if (stripos($text, 'Top of the') !== false) {
                $lob[$awayTeamId] += $count;
            } else {
                $lob[$homeTeamId] += $count;
            }
        }

        return $lob;
    }

    /**
     * Home and road W/L records for all MLB teams — keyed by team_id.
     */
    public function teamHomeAwayRecords(): array
    {
        $rows = $this->safeQuery(fn () =>
            DB::select("
                SELECT team_id, home, SUM(w) as w, SUM(l) as l FROM (
                    SELECT home_team as team_id, 1 as home,
                        SUM(CASE WHEN runs1 > runs0 THEN 1 ELSE 0 END) as w,
                        SUM(CASE WHEN runs1 < runs0 THEN 1 ELSE 0 END) as l
                    FROM games WHERE played=1 AND game_type=0 GROUP BY home_team
                    UNION ALL
                    SELECT away_team as team_id, 0 as home,
                        SUM(CASE WHEN runs0 > runs1 THEN 1 ELSE 0 END) as w,
                        SUM(CASE WHEN runs0 < runs1 THEN 1 ELSE 0 END) as l
                    FROM games WHERE played=1 AND game_type=0 GROUP BY away_team
                ) t GROUP BY team_id, home
            ")
        ) ?? [];

        $result = [];
        foreach ($rows as $r) {
            $tid = (int)$r->team_id;
            if (!isset($result[$tid])) $result[$tid] = ['home_w'=>0,'home_l'=>0,'road_w'=>0,'road_l'=>0];
            if ((int)$r->home === 1) { $result[$tid]['home_w'] = (int)$r->w; $result[$tid]['home_l'] = (int)$r->l; }
            else                     { $result[$tid]['road_w'] = (int)$r->w; $result[$tid]['road_l'] = (int)$r->l; }
        }
        return $result;
    }

    /** Next unplayed game for a team. */
    public function teamNextGame(int $teamId): ?object
    {
        return $this->safeQuery(fn () =>
            DB::table('games as g')
                ->join('teams as ht', 'g.home_team', '=', 'ht.team_id')
                ->join('teams as at', 'g.away_team', '=', 'at.team_id')
                ->where('g.played', 0)
                ->where('g.game_type', 0)
                ->where(fn ($q) => $q->where('g.home_team', $teamId)->orWhere('g.away_team', $teamId))
                ->select(
                    'g.*',
                    'ht.abbr as home_abbr', 'ht.name as home_name',
                    'at.abbr as away_abbr', 'at.name as away_name',
                )
                ->orderBy('g.date')
                ->orderBy('g.time')
                ->first()
        );
    }

    // -------------------------------------------------------------------------
    // Players
    // -------------------------------------------------------------------------

    public function player(int $playerId): ?object
    {
        return $this->safeQuery(fn () =>
            DB::table('players')->where('player_id', $playerId)->first()
        );
    }

    /** Player with team, sub-league, and division names. */
    public function playerWithTeam(int $playerId): ?object
    {
        return $this->safeQuery(fn () =>
            DB::table('players as p')
                ->leftJoin('teams as t', 'p.team_id', '=', 't.team_id')
                ->leftJoin('sub_leagues as sl', function ($j) {
                    $j->on('t.league_id', '=', 'sl.league_id')
                      ->on('t.sub_league_id', '=', 'sl.sub_league_id');
                })
                ->leftJoin('divisions as d', function ($j) {
                    $j->on('t.league_id', '=', 'd.league_id')
                      ->on('t.division_id', '=', 'd.division_id')
                      ->on('t.sub_league_id', '=', 'd.sub_league_id');
                })
                ->leftJoin('cities as bc', 'bc.city_id', '=', 'p.city_of_birth_id')
                ->leftJoin('states as bs', 'bs.state_id', '=', 'bc.state_id')
                ->select(
                    'p.*',
                    't.name as team_name', 't.abbr as team_abbr', 't.team_id as current_team_id',
                    'sl.name as sub_league_name',
                    'd.name as division_name',
                    'bc.name as birth_city', 'bs.name as birth_state',
                )
                ->where('p.player_id', $playerId)
                ->first()
        );
    }

    /** Year-by-year career batting stats (split_id=1 = full season). */
    public function playerCareerBatting(int $playerId, int $splitId = 1): \Illuminate\Support\Collection
    {
        $rows = $this->safeQuery(fn () =>
            DB::table('players_career_batting_stats as s')
                ->leftJoin('teams as t', 't.team_id', '=', 's.team_id')
                ->leftJoin('leagues as lg', 'lg.league_id', '=', 't.league_id')
                ->where('s.player_id', $playerId)
                ->where('s.team_id', '>', 0)
                ->where('s.split_id', $splitId)
                ->select(
                    's.year', 's.team_id',
                    't.abbr as team_abbr', 't.name as team_name', 't.level as team_level',
                    'lg.abbr as league_abbr',
                    DB::raw('SUM(s.g)   as g'),
                    DB::raw('SUM(s.ab)  as ab'),
                    DB::raw('SUM(s.r)   as r'),
                    DB::raw('SUM(s.h)   as h'),
                    DB::raw('SUM(s.d)   as d'),
                    DB::raw('SUM(s.t)   as t_triples'),
                    DB::raw('SUM(s.hr)  as hr'),
                    DB::raw('SUM(s.rbi) as rbi'),
                    DB::raw('SUM(s.bb)  as bb'),
                    DB::raw('SUM(s.k)   as k'),
                    DB::raw('SUM(s.sb)  as sb'),
                    DB::raw('SUM(s.cs)  as cs'),
                    DB::raw('SUM(s.hp)  as hp'),
                    DB::raw('SUM(s.sf)  as sf'),
                    DB::raw('SUM(s.sh)  as sh'),
                    DB::raw('SUM(s.ibb) as ibb'),
                    DB::raw('SUM(s.gdp) as gdp'),
                    DB::raw('SUM(s.pitches_seen) as pitches_seen'),
                    DB::raw('SUM(s.war) as war'),
                )
                ->groupBy('s.year', 's.team_id', 't.abbr', 't.name', 't.level', 'lg.abbr')
                ->orderBy('s.year')
                ->get()
        ) ?? collect();

        return $rows->map(function ($row) {
            $ab  = (int) $row->ab;
            $h   = (int) $row->h;
            $bb  = (int) $row->bb;
            $hp  = (int) $row->hp;
            $sf  = (int) $row->sf;
            $d   = (int) $row->d;
            $t   = (int) $row->t_triples;
            $hr  = (int) $row->hr;
            $avg = $ab > 0 ? $h / $ab : 0;
            $obp = ($ab + $bb + $hp + $sf) > 0 ? ($h + $bb + $hp) / ($ab + $bb + $hp + $sf) : 0;
            $slg = $ab > 0 ? (($h - $d - $t - $hr) + 2*$d + 3*$t + 4*$hr) / $ab : 0;
            $row->avg = $avg;
            $row->obp = $obp;
            $row->slg = $slg;
            $row->ops = $obp + $slg;

            // Expanded/Advanced computed fields
            $sb = (int)$row->sb; $cs = (int)($row->cs ?? 0);
            $pa = (int)($row->pa ?? 0); $k = (int)$row->k;
            $singles = $h - $d - $t - $hr;
            $row->tb  = $singles + 2*$d + 3*$t + 4*$hr;
            $row->xbh = $d + $t + $hr;
            $row->ppa = $pa > 0 ? (int)($row->pitches_seen ?? 0) / $pa : 0;
            $row->sbpct = ($sb + $cs) > 0 ? $sb / ($sb + $cs) : 0;
            $row->isop = $slg - $avg;
            $row->seca = $ab > 0 ? ($row->tb - $h + $bb + $sb - $cs) / $ab : 0;
            $row->rc   = ($ab + $bb) > 0 ? ($h + $bb) * $row->tb / ($ab + $bb) : 0;
            $row->rc27 = ($ab - $h) > 0 ? $row->rc / (($ab - $h) / 27) : 0;
            $row->abhr = $hr > 0 ? $ab / $hr : 0;
            $row->bbpa = $pa > 0 ? $bb / $pa : 0;
            $row->bbk  = $k > 0 ? $bb / $k : 0;

            return $row;
        });
    }

    /** Year-by-year career fielding stats. */
    public function playerCareerFielding(int $playerId, int $splitId = 1): \Illuminate\Support\Collection
    {
        $rows = $this->safeQuery(fn () =>
            DB::table('players_career_fielding_stats as s')
                ->leftJoin('teams as t', 't.team_id', '=', 's.team_id')
                ->leftJoin('leagues as lg', 'lg.league_id', '=', 't.league_id')
                ->where('s.player_id', $playerId)
                ->where('s.team_id', '>', 0)
                ->where('s.split_id', $splitId)
                ->select(
                    's.year', 's.team_id', 's.position',
                    't.abbr as team_abbr', 't.name as team_name', 't.level as team_level',
                    'lg.abbr as league_abbr',
                    's.g', 's.gs', 's.tc', 's.a', 's.po', 's.e', 's.dp',
                    's.pb', 's.sba', 's.rto', 's.ip', 's.er', 's.zr',
                )
                ->orderBy('s.year')
                ->orderBy('s.position')
                ->get()
        ) ?? collect();

        $posLabels = [1=>'P',2=>'C',3=>'1B',4=>'2B',5=>'3B',6=>'SS',7=>'LF',8=>'CF',9=>'RF',10=>'DH'];

        return $rows->map(function ($row) use ($posLabels) {
            $row->pos_label = $posLabels[(int)$row->position] ?? '?';
            $tc = (int)$row->tc;
            $e  = (int)$row->e;
            $g  = (int)$row->g;
            $a  = (int)$row->a;
            $po = (int)$row->po;
            $ip = (float)$row->ip;
            $sba = (int)$row->sba;
            $rto = (int)$row->rto; // runners thrown out (CS by catcher)

            $row->fp  = $tc > 0 ? ($tc - $e) / $tc : 0;
            $row->rf  = $g > 0 ? ($a + $po) / $g : 0;
            $row->fip_display = $ip > 0 ? number_format($ip, 1) : '.0';
            $row->cs_pct = $sba > 0 ? $rto / $sba : 0;
            $row->era_field = $ip > 0 ? number_format(((int)$row->er / ($ip / 9)), 1) : '—';
            // DWAR placeholder — stored as zr in some OOTP versions
            $row->dwar = (float)($row->zr ?? 0);

            return $row;
        });
    }

    /** Year-by-year career pitching stats (split_id=1 = season, 21 = postseason). */
    public function playerCareerPitching(int $playerId, int $splitId = 1): \Illuminate\Support\Collection
    {
        $rows = $this->safeQuery(fn () =>
            DB::table('players_career_pitching_stats as s')
                ->leftJoin('teams as t', 't.team_id', '=', 's.team_id')
                ->where('s.team_id', '>', 0)
                ->leftJoin('leagues as lg', 'lg.league_id', '=', 't.league_id')
                ->where('s.player_id', $playerId)
                ->where('s.split_id', $splitId)
                ->select(
                    's.year', 's.team_id',
                    't.abbr as team_abbr', 't.name as team_name', 't.level as team_level',
                    'lg.abbr as league_abbr',
                    DB::raw('SUM(s.g)    as g'),
                    DB::raw('SUM(s.gs)   as gs'),
                    DB::raw('SUM(s.w)    as w'),
                    DB::raw('SUM(s.l)    as l'),
                    DB::raw('SUM(s.s)    as sv'),
                    DB::raw('SUM(s.hld)  as hld'),
                    DB::raw('SUM(s.outs) as outs'),
                    DB::raw('SUM(s.ha)   as h'),
                    DB::raw('SUM(s.er)   as er'),
                    DB::raw('SUM(s.bb)   as bb'),
                    DB::raw('SUM(s.k)    as k'),
                    DB::raw('SUM(s.hra)  as hr'),
                    DB::raw('SUM(s.r)    as r'),
                    DB::raw('SUM(s.bs)   as bs'),
                    DB::raw('SUM(s.cg)   as cg'),
                    DB::raw('SUM(s.qs)   as qs'),
                    DB::raw('SUM(s.ab)   as opp_ab'),
                    DB::raw('SUM(s.da)   as opp_2b'),
                    DB::raw('SUM(s.ta)   as opp_3b'),
                    DB::raw('SUM(s.bf)   as opp_tbf'),
                    DB::raw('SUM(s.pi)   as opp_pitches'),
                    DB::raw('SUM(s.hp)   as opp_hbp'),
                    DB::raw('SUM(s.sf)   as opp_sf'),
                    DB::raw('SUM(s.sh)   as opp_sh'),
                    DB::raw('SUM(s.iw)   as opp_ibb'),
                    DB::raw('SUM(s.gb)   as gb'),
                    DB::raw('SUM(s.fb)   as fb'),
                    DB::raw('SUM(s.sho)  as sho'),
                    DB::raw('SUM(s.wp)   as wp'),
                    DB::raw('SUM(s.bk)   as bk'),
                    DB::raw('SUM(s.sb)   as opp_sb'),
                    DB::raw('SUM(s.cs)   as opp_cs'),
                    DB::raw('SUM(s.ir)   as ir'),
                    DB::raw('SUM(s.irs)  as irs'),
                    DB::raw('SUM(s.rs)   as rs'),
                    DB::raw('SUM(s.war)    as war'),
                    DB::raw('SUM(s.ra9war) as ra9war'),
                )
                ->groupBy('s.year', 's.team_id', 't.abbr', 't.name', 't.level', 'lg.abbr')
                ->orderBy('s.year')
                ->get()
        ) ?? collect();

        return $rows->map(function ($row) {
            $outs     = (int) $row->outs;
            $ip       = $outs / 3;
            $row->ip_display = floor($outs / 3) . '.' . ($outs % 3);
            $row->era  = $ip > 0 ? number_format(((int) $row->er / $ip) * 9, 2) : '—';
            $row->whip = $ip > 0 ? number_format(((int) $row->h + (int) $row->bb) / $ip, 2) : '—';
            return $row;
        });
    }

    /** All career awards for a player, ordered by year descending. */
    public function playerAllAwards(int $playerId): \Illuminate\Support\Collection
    {
        return $this->safeQuery(fn () =>
            DB::table('players_awards')
                ->where('player_id', $playerId)
                ->whereIn('award_id', array_keys(self::AWARD_NAMES))
                ->orderByDesc('year')
                ->orderBy('award_id')
                ->get()
        ) ?? collect();
    }

    /** Current-season batting totals from game logs. */
    public function playerSeasonBatting(int $playerId): ?object
    {
        $currentYear = $this->seasonYear();
        if (!$currentYear) return null;

        $row = $this->safeQuery(fn () =>
            DB::table('players_career_batting_stats')
                ->where('player_id', $playerId)
                ->where('year', $currentYear)
                ->where('split_id', 1)
                ->selectRaw('
                    SUM(g) as g, SUM(ab) as ab, SUM(r) as r, SUM(h) as h,
                    SUM(d) as d, SUM(t) as t_triples, SUM(hr) as hr, SUM(rbi) as rbi,
                    SUM(bb) as bb, SUM(k) as k, SUM(sb) as sb, SUM(cs) as cs,
                    SUM(hp) as hp, SUM(sf) as sf, SUM(war) as war
                ')
                ->first()
        );

        if (!$row || (int)$row->ab === 0) return null;

        $ab  = (int) $row->ab;
        $h   = (int) $row->h;
        $bb  = (int) $row->bb;
        $hp  = (int) $row->hp;
        $sf  = (int) $row->sf;
        $d   = (int) $row->d;
        $t   = (int) $row->t_triples;
        $hr  = (int) $row->hr;
        $avg = $ab > 0 ? $h / $ab : 0;
        $obp = ($ab + $bb + $hp + $sf) > 0 ? ($h + $bb + $hp) / ($ab + $bb + $hp + $sf) : 0;
        $slg = $ab > 0 ? (($h - $d - $t - $hr) + 2*$d + 3*$t + 4*$hr) / $ab : 0;
        $row->avg = $avg;
        $row->obp = $obp;
        $row->slg = $slg;
        $row->ops = $obp + $slg;
        $row->year = $currentYear;

        return $row;
    }

    /** Current-season pitching totals from career stats. */
    public function playerSeasonPitching(int $playerId): ?object
    {
        $currentYear = $this->seasonYear();
        if (!$currentYear) return null;

        $row = $this->safeQuery(fn () =>
            DB::table('players_career_pitching_stats')
                ->where('player_id', $playerId)
                ->where('year', $currentYear)
                ->where('split_id', 1)
                ->selectRaw('
                    SUM(g) as g, SUM(gs) as gs, SUM(w) as w, SUM(l) as l,
                    SUM(s) as sv, SUM(hld) as hld, SUM(bs) as bs, SUM(outs) as outs,
                    SUM(ha) as h, SUM(r) as r, SUM(er) as er, SUM(bb) as bb, SUM(k) as k,
                    SUM(hra) as hr, SUM(cg) as cg, SUM(qs) as qs,
                    SUM(war) as war, SUM(ra9war) as ra9war
                ')
                ->first()
        );

        if (!$row || (int)$row->outs === 0) return null;

        $outs = (int) $row->outs;
        $ip   = $outs / 3;
        $row->ip_display = floor($outs / 3) . '.' . ($outs % 3);
        $row->era  = $ip > 0 ? number_format(((int) $row->er / $ip) * 9, 2) : '—';
        $row->whip = $ip > 0 ? number_format(((int) $row->h + (int) $row->bb) / $ip, 2) : '—';
        $row->year = $currentYear;

        return $row;
    }

    /** Contract for a player. */
    public function playerContract(int $playerId): ?object
    {
        return $this->safeQuery(fn () =>
            DB::table('players_contract')->where('player_id', $playerId)->first()
        );
    }

    // -------------------------------------------------------------------------
    // Game — box score & log
    // -------------------------------------------------------------------------

    /**
     * At-bat result codes for players_at_bat_batting_stats.result.
     * Verified against OOTP database cross-referencing box score data.
     * HR = 9 (used for auto-expand in game log).
     */
    public const AT_BAT_RESULTS = [
        1  => 'Strikeout',
        2  => 'Walk',
        4  => 'Groundout',
        5  => 'Flyout',
        6  => 'Single',
        7  => 'Double',
        8  => 'Triple',
        9  => 'Home Run',
        10 => 'Hit by Pitch',
        11 => 'Other',
    ];

    /** Result codes that are hits (for XBH notes). */
    public const RESULT_HR = 9;
    public const RESULT_2B = 7;
    public const RESULT_3B = 8;
    public const RESULT_1B = 6;
    public const RESULT_BB = 2;
    public const RESULT_K  = 1;

    /** Full game row with team names and pitcher names. */
    public function game(int $gameId): ?object
    {
        return $this->safeQuery(fn () =>
            DB::table('games as g')
                ->join('teams as ht', 'g.home_team', '=', 'ht.team_id')
                ->join('teams as at', 'g.away_team', '=', 'at.team_id')
                ->leftJoin('players as wp', 'g.winning_pitcher', '=', 'wp.player_id')
                ->leftJoin('players as lp', 'g.losing_pitcher',  '=', 'lp.player_id')
                ->leftJoin('players as sv', 'g.save_pitcher',    '=', 'sv.player_id')
                ->leftJoin('players as s0', 'g.starter0',        '=', 's0.player_id')
                ->leftJoin('players as s1', 'g.starter1',        '=', 's1.player_id')
                ->leftJoin('parks as pk',   'ht.park_id',        '=', 'pk.park_id')
                ->select(
                    'g.*',
                    'ht.name as home_name', 'ht.abbr as home_abbr',
                    'at.name as away_name', 'at.abbr as away_abbr',
                    'pk.name as park_name',
                    DB::raw("CONCAT(wp.first_name,' ',wp.last_name) as wp_name"),
                    DB::raw("CONCAT(lp.first_name,' ',lp.last_name) as lp_name"),
                    DB::raw("CONCAT(sv.first_name,' ',sv.last_name) as sv_name"),
                    DB::raw("CONCAT(s0.first_name,' ',s0.last_name) as away_starter"),
                    DB::raw("CONCAT(s1.first_name,' ',s1.last_name) as home_starter"),
                )
                ->where('g.game_id', $gameId)
                ->first()
        );
    }

    /**
     * Inning-by-inning scores.
     * Returns ['away' => [1=>r, 2=>r, ...], 'home' => [...], 'max_inning' => n]
     */
    public function gameInningScores(int $gameId): array
    {
        $rows = $this->safeQuery(fn () =>
            DB::table('games_score')
                ->where('game_id', $gameId)
                ->orderBy('team')
                ->orderBy('inning')
                ->get()
        ) ?? collect();

        $away = [];
        $home = [];
        $max  = 9;

        foreach ($rows as $row) {
            $inn = (int) $row->inning;
            if ($inn > $max) $max = $inn;
            if ((int) $row->team === 0) {
                $away[$inn] = (int) $row->score;
            } else {
                $home[$inn] = (int) $row->score;
            }
        }

        return compact('away', 'home', 'max');
    }

    /**
     * Per-game batting stats for both teams, keyed by team_id.
     *
     * Ordering:
     *  Primary   — batting order slot (MIN spot from at-bat stats; 0-AB pitchers get pitcher slot)
     *  Secondary — first log-line appearance (Batting/PH lines for batters, Pitching lines for pitchers)
     *              This correctly sequences the pitcher-chain: starter → PH → new-P → PH → new-P …
     */
    public function gameBoxBatting(int $gameId): array
    {
        // 1. Spot (batting slot) from at-bat stats for every player who had a plate appearance
        $spots = $this->safeQuery(fn () =>
            DB::table('players_at_bat_batting_stats')
                ->where('game_id', $gameId)
                ->select('player_id', 'team_id', DB::raw('MIN(spot) as bat_order'))
                ->groupBy('player_id', 'team_id')
                ->get()
        ) ?? collect();

        $spotMap = [];   // [player_id] => batting-slot number
        foreach ($spots as $s) {
            $spotMap[(int)$s->player_id] = (int)$s->bat_order;
        }

        // 2. Parse game_logs to get first appearance line per player per team.
        //    "Batting:" / "Pinch Hitting:" → belongs to the BATTING team
        //    "Pitching:"                   → belongs to the FIELDING team
        //    Track top/bottom from type=1 headers.
        $gameRow = $this->safeQuery(fn () =>
            DB::table('games')->where('game_id', $gameId)->select('away_team','home_team')->first()
        );
        $awayTeamId = $gameRow ? (int)$gameRow->away_team : 0;
        $homeTeamId = $gameRow ? (int)$gameRow->home_team : 0;

        $logs = $this->safeQuery(fn () =>
            DB::table('game_logs')
                ->where('game_id', $gameId)
                ->whereIn('type', [1, 2])
                ->orderBy('line')
                ->get()
        ) ?? collect();

        $battingTeam  = null;
        $firstLine    = [];   // [team_id][player_id] = first log-line number
        $pitcherLines = [];   // [team_id][player_id] = pitching-entry log-line (for 0-AB pitchers)

        foreach ($logs as $log) {
            $text = (string)$log->text;
            $type = (int)$log->type;

            if ($type === 1) {
                $battingTeam = stripos($text, 'Top of the') !== false ? $awayTeamId : $homeTeamId;
                continue;
            }
            if ($type !== 2 || !$battingTeam) continue;

            $isBat   = stripos($text, 'Batting:') !== false || stripos($text, 'Pinch Hitting:') !== false;
            $isPitch = stripos($text, 'Pitching:') !== false;
            if (!$isBat && !$isPitch) continue;
            if (!preg_match('/player_(\d+)\.html/', $text, $m)) continue;

            $pid  = (int)$m[1];
            $team = $isBat
                ? $battingTeam
                : ($battingTeam === $awayTeamId ? $homeTeamId : $awayTeamId);

            if (!isset($firstLine[$team][$pid])) {
                $firstLine[$team][$pid] = (int)$log->line;
            }
            if ($isPitch && !isset($pitcherLines[$team][$pid])) {
                $pitcherLines[$team][$pid] = (int)$log->line;
            }
        }

        // 3. Determine each team's pitcher batting slot (the slot the starting pitcher occupies).
        //    Look for the pitcher (SP/RP) who appears in both spotMap and pitcherLines.
        $pitcherSlot = [$awayTeamId => 9, $homeTeamId => 9];
        foreach ([$awayTeamId, $homeTeamId] as $tid) {
            foreach ($pitcherLines[$tid] ?? [] as $pid => $line) {
                if (isset($spotMap[$pid])) {
                    $pitcherSlot[$tid] = $spotMap[$pid];
                    break;
                }
            }
        }

        // 4. Fetch all players_game_batting rows
        $rows = $this->safeQuery(fn () =>
            DB::table('players_game_batting as b')
                ->join('players as p', 'b.player_id', '=', 'p.player_id')
                ->where('b.game_id', $gameId)
                ->select(
                    'b.player_id', 'b.team_id', 'b.position', 'b.stint',
                    'b.ab', 'b.r', 'b.h', 'b.d', 'b.t', 'b.hr',
                    'b.rbi', 'b.bb', 'b.k', 'b.sb', 'b.cs', 'b.hp',
                    'b.gdp', 'b.sf', 'b.sh',
                    'p.first_name', 'p.last_name',
                    'p.position as player_position',
                )
                ->get()
        ) ?? collect();

        $result = [];
        foreach ($rows as $row) {
            $tid = (int)$row->team_id;
            $pid = (int)$row->player_id;

            $row->bat_order   = $spotMap[$pid] ?? $pitcherSlot[$tid];
            $row->appearance  = $firstLine[$tid][$pid] ?? 99999;

            $result[$tid][] = $row;
        }

        foreach ($result as $tid => &$players) {
            usort($players, fn ($a, $b) =>
                $a->bat_order  <=> $b->bat_order  ?:
                $a->appearance <=> $b->appearance
            );
        }

        return $result;
    }

    /**
     * Per-game pitching stats for both teams, keyed by team_id.
     * Ordered by first appearance line in game_logs (reliable; stint is always 0 in OOTP exports).
     */
    public function gameBoxPitching(int $gameId): array
    {
        // Build pitcher appearance order from game_logs
        $gameRow = $this->safeQuery(fn () =>
            DB::table('games')->where('game_id', $gameId)->select('away_team','home_team')->first()
        );
        $awayTeamId = $gameRow ? (int)$gameRow->away_team : 0;
        $homeTeamId = $gameRow ? (int)$gameRow->home_team : 0;

        $logs = $this->safeQuery(fn () =>
            DB::table('game_logs')
                ->where('game_id', $gameId)
                ->whereIn('type', [1, 2])
                ->orderBy('line')
                ->get(['type', 'text', 'line'])
        ) ?? collect();

        $battingTeam  = null;
        $pitcherOrder = [];  // [team_id][player_id] = first pitching line

        foreach ($logs as $log) {
            if ((int)$log->type === 1) {
                $battingTeam = stripos($log->text, 'Top of the') !== false ? $awayTeamId : $homeTeamId;
                continue;
            }
            if ((int)$log->type !== 2 || !$battingTeam) continue;
            if (stripos($log->text, 'Pitching:') === false) continue;
            if (!preg_match('/player_(\d+)\.html/', $log->text, $m)) continue;

            $pid        = (int)$m[1];
            $fieldingId = $battingTeam === $awayTeamId ? $homeTeamId : $awayTeamId;

            if (!isset($pitcherOrder[$fieldingId][$pid])) {
                $pitcherOrder[$fieldingId][$pid] = (int)$log->line;
            }
        }

        $rows = $this->safeQuery(fn () =>
            DB::table('players_game_pitching_stats as ps')
                ->join('players as p', 'ps.player_id', '=', 'p.player_id')
                ->where('ps.game_id', $gameId)
                ->select(
                    'ps.player_id', 'ps.team_id',
                    'ps.outs', 'ps.ha', 'ps.r', 'ps.er',
                    'ps.bb', 'ps.k', 'ps.hra', 'ps.pi',
                    'ps.gb', 'ps.fb', 'ps.bf', 'ps.hp',
                    'ps.w', 'ps.l', 'ps.s', 'ps.hld', 'ps.bs',
                    'p.first_name', 'p.last_name',
                )
                ->get()
        ) ?? collect();

        $result = [];
        foreach ($rows as $row) {
            $tid  = (int)$row->team_id;
            $pid  = (int)$row->player_id;
            $outs = (int)$row->outs;
            $ip   = $outs / 3;
            $row->ip_display  = floor($outs / 3) . '.' . ($outs % 3);
            $row->era_game    = $ip > 0 ? number_format(((int)$row->er / $ip) * 9, 2) : '—';
            $row->appearance  = $pitcherOrder[$tid][$pid] ?? 99999;
            $result[$tid][]   = $row;
        }

        foreach ($result as $tid => &$pitchers) {
            usort($pitchers, fn ($a, $b) => $a->appearance <=> $b->appearance);
        }

        return $result;
    }

    /**
     * At-bats for the game log, grouped by inning half.
     * Each half: ['inning'=>n, 'half'=>'top'|'bottom', 'team_id'=>n, 'atbats'=>[...]]
     * Ordered top-of-inning (away) before bottom (home).
     */
    public function gameAtBats(int $gameId, int $awayTeamId, int $homeTeamId): array
    {
        $rows = $this->safeQuery(fn () =>
            DB::table('players_at_bat_batting_stats as ab')
                ->join('players as b', 'ab.player_id', '=', 'b.player_id')
                ->leftJoin('players as p', 'ab.opponent_player_id', '=', 'p.player_id')
                ->where('ab.game_id', $gameId)
                ->select(
                    'ab.player_id', 'ab.team_id', 'ab.inning', 'ab.outs',
                    'ab.balls', 'ab.strikes', 'ab.result',
                    'ab.rbi', 'ab.r', 'ab.sb', 'ab.sac',
                    'ab.base1', 'ab.base2', 'ab.base3', 'ab.run_diff',
                    'ab.hit_loc', 'ab.exit_velo', 'ab.launch_angle',
                    'ab.spot', 'ab.pinch',
                    'b.first_name as batter_first', 'b.last_name as batter_last',
                    'p.first_name as pitcher_first', 'p.last_name as pitcher_last',
                )
                ->get()
        ) ?? collect();

        // Build sequential ordering from game_logs (authoritative game order).
        // "Batting:" and "Pinch Hitting:" type=2 entries, in line order, tell us
        // exactly which player_id came up to bat and when.
        // Track inning/half so we can match at-bats precisely when a player
        // bats multiple times in the same half-inning (batting around).
        $logRows = $this->safeQuery(fn () =>
            DB::table('game_logs')
                ->where('game_id', $gameId)
                ->whereIn('type', [1, 2])
                ->orderBy('line')
                ->get(['type', 'text'])
        ) ?? collect();

        // Build sequence: each batting entry gets a globalSeq keyed by (player_id, inning, half, occurrence)
        $seqByKey  = [];   // "pid_inning_half_N" => globalSeq
        $globalSeq = 0;
        $curInning = 1;
        $curHalf   = 'top';
        $halfCount = [];   // "pid_inning_half" => count of appearances so far
        foreach ($logRows as $log) {
            if ($log->type == 1) {
                // Inning change markers
                if (preg_match('/Top of the (\d+)/i', $log->text, $m)) {
                    $curInning = (int)$m[1]; $curHalf = 'top';
                } elseif (preg_match('/Bottom of the (\d+)/i', $log->text, $m)) {
                    $curInning = (int)$m[1]; $curHalf = 'bottom';
                }
                continue;
            }
            if (str_contains($log->text, 'Batting:') || str_contains($log->text, 'Pinch Hitting:')) {
                if (preg_match('/player_(\d+)\.html/', $log->text, $m)) {
                    $pid = (int)$m[1];
                    $baseKey = $pid . '_' . $curInning . '_' . $curHalf;
                    $n = $halfCount[$baseKey] ?? 0;
                    $seqByKey[$baseKey . '_' . $n] = $globalSeq++;
                    $halfCount[$baseKey] = $n + 1;
                }
            }
        }

        // Match each DB at-bat row to its game_logs sequence.
        // Sort DB rows by run_diff within each (inning, half) group first,
        // so occurrence 0 = earliest at-bat for that player in that half-inning.
        $rowsArr = $rows->all();
        usort($rowsArr, function ($a, $b) use ($awayTeamId) {
            $aHalf = (int)$a->team_id === $awayTeamId ? 0 : 1;
            $bHalf = (int)$b->team_id === $awayTeamId ? 0 : 1;
            $aSort = (int)$a->inning * 2 + $aHalf;
            $bSort = (int)$b->inning * 2 + $bHalf;
            if ($aSort !== $bSort) return $aSort <=> $bSort;
            // Within same half-inning, sort by run_diff to get chronological order
            return abs((int)$a->run_diff) <=> abs((int)$b->run_diff);
        });

        // Assign sequences using per-(player, inning, half) cursor
        $seqCursor = [];
        foreach ($rowsArr as $ab) {
            $pid  = (int)$ab->player_id;
            $half = (int)$ab->team_id === $awayTeamId ? 'top' : 'bottom';
            $baseKey = $pid . '_' . (int)$ab->inning . '_' . $half;
            $n = $seqCursor[$baseKey] ?? 0;
            $ab->_seq = $seqByKey[$baseKey . '_' . $n] ?? 99999;
            $seqCursor[$baseKey] = $n + 1;
        }
        usort($rowsArr, fn ($a, $b) => $a->_seq <=> $b->_seq);

        $grouped = [];
        foreach ($rowsArr as $ab) {
            $half    = (int) $ab->team_id === $awayTeamId ? 'top' : 'bottom';
            $sortKey = (int) $ab->inning * 2 + ($half === 'top' ? 0 : 1);
            $key     = "{$ab->inning}_{$half}";

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'sort'    => $sortKey,
                    'inning'  => (int) $ab->inning,
                    'half'    => $half,
                    'team_id' => (int) $ab->team_id,
                    'atbats'  => [],
                ];
            }
            $grouped[$key]['atbats'][] = $ab;
        }

        uasort($grouped, fn ($a, $b) => $a['sort'] <=> $b['sort']);

        return array_values($grouped);
    }

    /**
     * Game log pitch/play entries (type=3) grouped by at-bat sequence.
     * Returns array indexed 0..N, one entry per at-bat in game order:
     *   ['inning'=>n, 'half'=>'top'|'bottom', 'pitches'=>['0-0: Ball', ...]]
     */
    public function gameAtBatLogs(int $gameId): array
    {
        $rows = $this->safeQuery(fn () =>
            DB::table('game_logs')
                ->where('game_id', $gameId)
                ->whereIn('type', [1, 2, 3])
                ->orderBy('line')
                ->get(['type', 'text'])
        ) ?? collect();

        $result        = [];
        $currentIdx    = -1;
        $currentHalf   = 'top';
        $currentInning = 0;
        $currentPitcher = null;

        foreach ($rows as $row) {
            if ($row->type == 1) {
                $currentHalf    = str_starts_with($row->text, 'Top') ? 'top' : 'bottom';
                // Do NOT reset currentPitcher here — OOTP only emits a Pitching: entry
                // when the pitcher changes, so the same pitcher carries across innings.
                if (preg_match('/(\d+)(?:st|nd|rd|th)/', $row->text, $m)) {
                    $currentInning = (int)$m[1];
                }
            } elseif ($row->type == 2) {
                if (str_contains($row->text, 'Pitching:')) {
                    $cleanText = strip_tags($row->text);
                    preg_match('/Pitching:\s+(?:LHB|RHB|LHP|RHP|S)?\s*(.+)/', $cleanText, $nm);
                    $currentPitcher = isset($nm[1]) ? trim($nm[1]) : null;
                    // Also try to grab from the player link text
                    if (!$currentPitcher) {
                        preg_match('/>([^<]+)<\/a>/', $row->text, $lm);
                        $currentPitcher = isset($lm[1]) ? trim($lm[1]) : null;
                    }
                } elseif (str_contains($row->text, 'Batting:') || str_contains($row->text, 'Pinch Hitting:')) {
                    preg_match('/player_(\d+)\.html/', $row->text, $pm);
                    $pid = isset($pm[1]) ? (int)$pm[1] : null;
                    $cleanText = strip_tags($row->text);
                    preg_match('/(?:Batting|Pinch Hitting):\s+(?:LHB|RHB|LHP|RHP|S)?\s*(.+)/', $cleanText, $nm);
                    $batterName = isset($nm[1]) ? trim($nm[1]) : null;
                    $currentIdx++;
                    $result[$currentIdx] = [
                        'inning'      => $currentInning,
                        'half'        => $currentHalf,
                        'player_id'   => $pid,
                        'batter_name' => $batterName,
                        'pitcher'     => $currentPitcher,
                        'pitches'     => [],
                    ];
                }
            } elseif ($row->type == 3 && $currentIdx >= 0) {
                $result[$currentIdx]['pitches'][] = trim(strip_tags($row->text));
            }
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Games
    // -------------------------------------------------------------------------

    /** Recent completed games (with scores). */
    public function recentGames(int $limit = 10): \Illuminate\Support\Collection
    {
        return $this->safeQuery(fn () =>
            DB::table('games')
                ->join('games_score', 'games.game_id', '=', 'games_score.game_id')
                ->where('games.played', 1)
                ->orderByDesc('games.date')
                ->limit($limit)
                ->get()
        ) ?? collect();
    }

    /** Upcoming unplayed games. */
    public function upcomingGames(int $limit = 10): \Illuminate\Support\Collection
    {
        return $this->safeQuery(fn () =>
            DB::table('games')
                ->where('played', 0)
                ->orderBy('date')
                ->limit($limit)
                ->get()
        ) ?? collect();
    }

    // -------------------------------------------------------------------------
    // Standings
    // -------------------------------------------------------------------------

    public function standings(): \Illuminate\Support\Collection
    {
        // Compute run differential per team from played games
        $rdByTeam = [];
        $rdRows = $this->safeQuery(fn () =>
            DB::select("
                SELECT team_id, SUM(rd) as rd FROM (
                    SELECT home_team AS team_id, SUM(runs1 - runs0) AS rd FROM games WHERE played=1 GROUP BY home_team
                    UNION ALL
                    SELECT away_team AS team_id, SUM(runs0 - runs1) AS rd FROM games WHERE played=1 GROUP BY away_team
                ) t GROUP BY team_id
            ")
        ) ?? [];
        foreach ($rdRows as $r) {
            $rdByTeam[(int)$r->team_id] = (int)$r->rd;
        }

        $rows = $this->safeQuery(fn () =>
            DB::table('team_record')
                ->join('teams', 'team_record.team_id', '=', 'teams.team_id')
                ->where('teams.level', 1)
                ->where('teams.allstar_team', 0)
                ->select('team_record.*', 'teams.name', 'teams.abbr', 'teams.nickname', 'teams.division_id', 'teams.sub_league_id')
                ->get()
        ) ?? collect();

        return $rows->map(function ($t) use ($rdByTeam) {
            $t->rd = $rdByTeam[(int)$t->team_id] ?? 0;
            return $t;
        });
    }

    /**
     * Standings structured by sub-league → division.
     * Each team row has computed ->pct, ->gb, ->rd properties.
     */
    public function standingsByDivision(): array
    {
        $subLeagues = $this->subLeagues();
        $divisions  = $this->divisions();
        $standings  = $this->standings();

        if ($standings->isEmpty()) return [];

        $result = [];
        foreach ($subLeagues as $sl) {
            $slDivisions = $divisions->where('sub_league_id', $sl->sub_league_id);
            $slData = ['name' => $sl->name, 'divisions' => []];

            foreach ($slDivisions as $div) {
                $teams = $standings
                    ->where('division_id', $div->division_id)
                    ->where('sub_league_id', $div->sub_league_id)
                    ->sortByDesc(fn ($t) => ($t->w + $t->l) > 0 ? $t->w / ($t->w + $t->l) : 0)
                    ->values();

                $leader = $teams->first();

                $teams = $teams->map(function ($t) use ($leader) {
                    $total = $t->w + $t->l;
                    $t->pct = $total > 0
                        ? ($t->w / $total >= 1 ? '1.000' : '.'.str_pad((string)round(($t->w / $total) * 1000), 3, '0', STR_PAD_LEFT))
                        : '.000';

                    if ($leader && $t->team_id === $leader->team_id) {
                        $t->gb = '-';
                    } else {
                        $raw = (($leader->w - $t->w) + ($t->l - $leader->l)) / 2;
                        if ($raw <= 0) {
                            $t->gb = '-';
                        } elseif (fmod($raw, 1) === 0.5) {
                            $t->gb = ($raw === 0.5 ? '' : (int)$raw . ' ') . '½';
                        } else {
                            $t->gb = (string)(int)$raw;
                        }
                    }

                    return $t;
                });

                $slData['divisions'][] = [
                    'name'  => $div->name,
                    'teams' => $teams,
                ];
            }

            $result[] = $slData;
        }

        return $result;
    }

    /**
     * Extended team records: X-inning, one-run, vs LHP/RHP, by month.
     */
    public function teamExtendedRecords(int $teamId): array
    {
        // Pull all played games for this team in one query
        $games = $this->safeQuery(fn () =>
            DB::table('games as g')
                ->where('g.played', 1)->where('g.game_type', 0)
                ->where(fn($q) => $q->where('g.home_team', $teamId)->orWhere('g.away_team', $teamId))
                ->select('g.game_id', 'g.date', 'g.home_team', 'g.away_team',
                    'g.runs0', 'g.runs1', 'g.innings', 'g.starter0', 'g.starter1')
                ->get()
        ) ?? collect();

        // Starter handedness for vs LHP/RHP
        $oppStarterIds = $games->map(fn($g) =>
            (int)$g->home_team === $teamId ? (int)$g->starter0 : (int)$g->starter1
        )->filter(fn($v) => $v > 0)->unique()->values()->toArray();

        $pitcherHand = $oppStarterIds
            ? DB::table('players')->whereIn('player_id', $oppStarterIds)
                ->pluck('throws', 'player_id')->toArray()
            : [];

        $records = [
            'home'  => ['w' => 0, 'l' => 0],  // home record
            'road'  => ['w' => 0, 'l' => 0],  // road record
            'xi'    => ['w' => 0, 'l' => 0],  // extra innings
            'oner'  => ['w' => 0, 'l' => 0],  // one-run games
            'lhp'   => ['w' => 0, 'l' => 0],  // vs left-handed starter
            'rhp'   => ['w' => 0, 'l' => 0],  // vs right-handed starter
            'months' => [],                    // by month name
        ];

        foreach ($games as $g) {
            $isHome = (int)$g->home_team === $teamId;
            $myRuns  = $isHome ? (int)$g->runs1 : (int)$g->runs0;
            $oppRuns = $isHome ? (int)$g->runs0 : (int)$g->runs1;
            $won = $myRuns > $oppRuns;
            $wl  = $won ? 'w' : 'l';

            // Home / Road
            $records[$isHome ? 'home' : 'road'][$wl]++;

            // Extra innings
            if ((int)($g->innings ?? 9) > 9) {
                $records['xi'][$wl]++;
            }

            // One-run games
            if (abs($myRuns - $oppRuns) === 1) {
                $records['oner'][$wl]++;
            }

            // vs LHP / RHP (opposing starter handedness)
            $oppStarter = $isHome ? (int)$g->starter0 : (int)$g->starter1;
            $hand = $pitcherHand[$oppStarter] ?? 0;
            if ($hand === 2) $records['lhp'][$wl]++;       // 2 = Left
            elseif ($hand === 1) $records['rhp'][$wl]++;    // 1 = Right

            // By month
            $month = date('F', strtotime($g->date));
            $records['months'][$month] ??= ['w' => 0, 'l' => 0];
            $records['months'][$month][$wl]++;
        }

        // Last 10 games
        $last10 = ['w' => 0, 'l' => 0];
        foreach ($games->sortByDesc('date')->take(10) as $g) {
            $isHome = (int)$g->home_team === $teamId;
            $won = $isHome ? (int)$g->runs1 > (int)$g->runs0 : (int)$g->runs0 > (int)$g->runs1;
            $last10[$won ? 'w' : 'l']++;
        }
        $records['last10'] = $last10;

        return $records;
    }

    /**
     * Team batting stats with league rank.
     * Returns array of ['label' => string, 'val' => string, 'rank' => string].
     */
    public function teamBattingRankings(int $teamId, int $subLeagueId = 0): array
    {
        $myTeam = $this->safeQuery(fn () =>
            DB::table('teams')->where('team_id', $teamId)->first(['sub_league_id', 'league_id', 'level'])
        );
        if (!$myTeam) return [];

        $isMlb = (int)$myTeam->level === 1;
        if ($isMlb) {
            $mySlId = $subLeagueId !== 0 && $subLeagueId ? $subLeagueId : (int)$myTeam->sub_league_id;
            if ($mySlId === null) return [];
            $lgAbbr = $this->safeQuery(fn () =>
                DB::table('sub_leagues')->where('sub_league_id', $mySlId)->value('abbr')
            ) ?? 'LG';
            $peerFilter = fn($q) => $q->where('t.level', 1)->where('t.sub_league_id', $mySlId);
            $levelFilter = 1;
        } else {
            $lgAbbr = $this->safeQuery(fn () =>
                DB::table('leagues')->where('league_id', $myTeam->league_id)->value('abbr')
            ) ?? 'LG';
            $peerFilter = fn($q) => $q->where('t.league_id', $myTeam->league_id);
            $levelFilter = (int)$myTeam->level;
        }
        $slAbbr = $lgAbbr;

        // All teams batting in same league/sub-league
        $allTeams = $this->safeQuery(fn () =>
            DB::table('players_game_batting as pgb')
                ->join('games as g', 'g.game_id', '=', 'pgb.game_id')
                ->join('teams as t', 't.team_id', '=', 'pgb.team_id')
                ->where('g.played', 1)->where('pgb.level_id', $levelFilter)
                ->where($peerFilter)
                ->groupBy('pgb.team_id')
                ->selectRaw('pgb.team_id,
                    SUM(pgb.ab) as ab, SUM(pgb.h) as h, SUM(pgb.d) as d,
                    SUM(pgb.t) as t, SUM(pgb.hr) as hr, SUM(pgb.r) as r,
                    SUM(pgb.rbi) as rbi, SUM(pgb.bb) as bb, SUM(pgb.k) as k,
                    SUM(pgb.sb) as sb, SUM(pgb.hp) as hp, SUM(pgb.sf) as sf,
                    SUM(pgb.pa) as pa')
                ->get()
        ) ?? collect();

        if ($allTeams->isEmpty()) return [];

        // Compute derived stats for each team
        $stats = $allTeams->map(function ($t) {
            $ab = (int)$t->ab ?: 1;
            $pa = (int)$t->pa ?: 1;
            $h  = (int)$t->h;
            $bb = (int)$t->bb;
            $hp = (int)$t->hp;
            $sf = (int)$t->sf;
            $tb = $h + (int)$t->d + 2*(int)$t->t + 3*(int)$t->hr;
            $t->avg = $h / $ab;
            $t->obp = ($h + $bb + $hp) / $pa;
            $t->slg = $tb / $ab;
            $t->ops = $t->obp + $t->slg;
            $t->xbh = (int)$t->d + (int)$t->t + (int)$t->hr;
            return $t;
        });

        // Rank helper: higher is better by default
        $ord = fn($n) => $n . match((int)$n % 10) {
            1 => (int)$n % 100 === 11 ? 'th' : 'st',
            2 => (int)$n % 100 === 12 ? 'th' : 'nd',
            3 => (int)$n % 100 === 13 ? 'th' : 'rd',
            default => 'th',
        };

        $rank = function (string $field, bool $asc = false) use ($stats, $teamId, $ord, $slAbbr) {
            $sorted = $asc
                ? $stats->sortBy($field)->values()
                : $stats->sortByDesc($field)->values();
            $pos = $sorted->search(fn($t) => (int)$t->team_id === $teamId);
            return $pos !== false ? $ord($pos + 1) . ' in ' . $slAbbr : '';
        };

        $me = $stats->firstWhere('team_id', $teamId);
        if (!$me) return [];

        $fmt3 = fn($v) => $v >= 1 ? '1.000' : ltrim(number_format($v, 3), '0');

        return [
            ['label' => 'Batting Average',     'val' => $fmt3($me->avg),      'rank' => $rank('avg')],
            ['label' => 'On-Base Percentage',   'val' => $fmt3($me->obp),      'rank' => $rank('obp')],
            ['label' => 'Slugging Percentage',  'val' => $fmt3($me->slg),      'rank' => $rank('slg')],
            ['label' => 'On-Base + Slugging',   'val' => $fmt3($me->ops),      'rank' => $rank('ops')],
            ['label' => 'Runs Scored',          'val' => (string)(int)$me->r,  'rank' => $rank('r')],
            ['label' => 'Hits',                 'val' => (string)(int)$me->h,  'rank' => $rank('h')],
            ['label' => 'Extra-Base Hits',      'val' => (string)$me->xbh,     'rank' => $rank('xbh')],
            ['label' => 'Home Runs',            'val' => (string)(int)$me->hr, 'rank' => $rank('hr')],
            ['label' => 'Bases On Balls',       'val' => (string)(int)$me->bb, 'rank' => $rank('bb')],
            ['label' => 'Strikeouts',           'val' => (string)(int)$me->k,  'rank' => $rank('k', true)],
            ['label' => 'Stolen Bases',         'val' => (string)(int)$me->sb, 'rank' => $rank('sb')],
        ];
    }

    /**
     * Team pitching stats with league rank.
     */
    public function teamPitchingRankings(int $teamId, int $subLeagueId = 0): array
    {
        $myTeam = $this->safeQuery(fn () =>
            DB::table('teams')->where('team_id', $teamId)->first(['sub_league_id', 'league_id', 'level'])
        );
        if (!$myTeam) return [];

        $isMlb = (int)$myTeam->level === 1;
        if ($isMlb) {
            $mySlId = $subLeagueId !== 0 && $subLeagueId ? $subLeagueId : (int)$myTeam->sub_league_id;
            if ($mySlId === null) return [];
            $slAbbr = $this->safeQuery(fn () =>
                DB::table('sub_leagues')->where('sub_league_id', $mySlId)->value('abbr')
            ) ?? 'LG';
            $peerFilter = fn($q) => $q->where('t.level', 1)->where('t.sub_league_id', $mySlId);
            $levelFilter = 1;
        } else {
            $slAbbr = $this->safeQuery(fn () =>
                DB::table('leagues')->where('league_id', $myTeam->league_id)->value('abbr')
            ) ?? 'LG';
            $peerFilter = fn($q) => $q->where('t.league_id', $myTeam->league_id);
            $levelFilter = (int)$myTeam->level;
        }

        $allTeams = $this->safeQuery(fn () =>
            DB::table('players_game_pitching_stats as pgp')
                ->join('games as g', 'g.game_id', '=', 'pgp.game_id')
                ->join('teams as t', 't.team_id', '=', 'pgp.team_id')
                ->where('g.played', 1)->where('pgp.level_id', $levelFilter)
                ->where($peerFilter)
                ->groupBy('pgp.team_id')
                ->selectRaw('pgp.team_id,
                    SUM(pgp.outs) as outs, SUM(pgp.er) as er, SUM(pgp.r) as ra,
                    SUM(pgp.ha) as ha, SUM(pgp.bb) as bb, SUM(pgp.k) as k,
                    SUM(pgp.hra) as hra, SUM(pgp.gs) as gs,
                    SUM(pgp.bf) as bf, SUM(pgp.ab) as ab')
                ->get()
        ) ?? collect();

        if ($allTeams->isEmpty()) return [];

        // Starter vs bullpen ERA per team (same league)
        $starterBullpen = $this->safeQuery(fn () =>
            DB::table('players_game_pitching_stats as pgp')
                ->join('games as g', 'g.game_id', '=', 'pgp.game_id')
                ->join('teams as t', 't.team_id', '=', 'pgp.team_id')
                ->where('g.played', 1)->where('pgp.level_id', $levelFilter)
                ->where($peerFilter)
                ->groupBy('pgp.team_id', DB::raw('IF(pgp.gs > 0, 1, 0)'))
                ->selectRaw('pgp.team_id, IF(pgp.gs > 0, 1, 0) as is_starter,
                    SUM(pgp.outs) as outs, SUM(pgp.er) as er')
                ->get()
        ) ?? collect();

        $spEra = []; $rpEra = [];
        foreach ($starterBullpen as $row) {
            $tid = (int)$row->team_id;
            $ip = (int)$row->outs / 3;
            $era = $ip > 0 ? ((int)$row->er / $ip) * 9 : 0;
            if ((int)$row->is_starter) $spEra[$tid] = $era;
            else $rpEra[$tid] = $era;
        }

        $stats = $allTeams->map(function ($t) use ($spEra, $rpEra) {
            $outs = (int)$t->outs ?: 1;
            $ip   = $outs / 3;
            $ab   = (int)$t->ab ?: 1;
            $bf   = (int)$t->bf ?: 1;
            $ha   = (int)$t->ha;
            $bb   = (int)$t->bb;
            $t->era     = ((int)$t->er / $ip) * 9;
            $t->sp_era  = $spEra[(int)$t->team_id] ?? 0;
            $t->rp_era  = $rpEra[(int)$t->team_id] ?? 0;
            $t->opp_avg = $ha / $ab;
            // BABIP: (H - HR) / (AB - K - HR + SF) — approximate with (H-HR)/(AB-K-HR)
            $babipDenom = $ab - (int)$t->k - (int)$t->hra;
            $t->babip   = $babipDenom > 0 ? ($ha - (int)$t->hra) / $babipDenom : 0;
            return $t;
        });

        $ord = fn($n) => $n . match((int)$n % 10) {
            1 => (int)$n % 100 === 11 ? 'th' : 'st',
            2 => (int)$n % 100 === 12 ? 'th' : 'nd',
            3 => (int)$n % 100 === 13 ? 'th' : 'rd',
            default => 'th',
        };

        // Rank: lower is better for pitching stats
        $rank = function (string $field, bool $lowerBetter = true) use ($stats, $teamId, $ord, $slAbbr) {
            $sorted = $lowerBetter
                ? $stats->sortBy($field)->values()
                : $stats->sortByDesc($field)->values();
            $pos = $sorted->search(fn($t) => (int)$t->team_id === $teamId);
            return $pos !== false ? $ord($pos + 1) . ' in ' . $slAbbr : '';
        };

        $me = $stats->firstWhere('team_id', $teamId);
        if (!$me) return [];

        $fmt2 = fn($v) => number_format($v, 2);
        $fmt3 = fn($v) => $v >= 1 ? '1.000' : ltrim(number_format($v, 3), '0');

        return [
            ['label' => 'Earned Run Average',  'val' => $fmt2($me->era),          'rank' => $rank('era')],
            ['label' => "Starters' ERA",       'val' => $fmt2($me->sp_era),       'rank' => $rank('sp_era')],
            ['label' => 'Bullpen ERA',         'val' => $fmt2($me->rp_era),       'rank' => $rank('rp_era')],
            ['label' => 'Runs allowed',        'val' => (string)(int)$me->ra,     'rank' => $rank('ra')],
            ['label' => 'Hits allowed',        'val' => (string)(int)$me->ha,     'rank' => $rank('ha')],
            ['label' => 'Opponents AVG',       'val' => $fmt3($me->opp_avg),      'rank' => $rank('opp_avg')],
            ['label' => 'BABIP',               'val' => $fmt3($me->babip),        'rank' => $rank('babip')],
            ['label' => 'Home Runs allowed',   'val' => (string)(int)$me->hra,    'rank' => $rank('hra')],
            ['label' => 'Bases On Balls',      'val' => (string)(int)$me->bb,     'rank' => $rank('bb')],
            ['label' => 'Strikeouts',          'val' => (string)(int)$me->k,      'rank' => $rank('k', false)],
        ];
    }

    /**
     * Division standings for a specific team's division.
     * Returns ['division_name' => string, 'teams' => Collection] or null.
     */
    public function divisionStandingsForTeam(int $teamId): ?array
    {
        $team = $this->safeQuery(fn () =>
            DB::table('teams')->where('team_id', $teamId)
                ->first(['division_id', 'sub_league_id', 'league_id', 'level'])
        );
        if (!$team) return null;

        $isMlb = (int)$team->level === 1;

        if ($isMlb) {
            // MLB: use sub_league + division
            $divName = $this->safeQuery(fn () =>
                DB::table('divisions')
                    ->where('division_id', $team->division_id)
                    ->where('league_id', $team->league_id)
                    ->where('sub_league_id', $team->sub_league_id)
                    ->value('name')
            ) ?? '';

            $leagueTeamIds = DB::table('teams')
                ->where('level', 1)->where('allstar_team', 0)
                ->where('division_id', $team->division_id)
                ->where('sub_league_id', $team->sub_league_id)
                ->pluck('team_id')->toArray();

            $records = DB::table('team_record')->whereIn('team_id', $leagueTeamIds)->get();
            $teamInfo = DB::table('teams')->whereIn('team_id', $leagueTeamIds)
                ->get(['team_id', 'name', 'nickname', 'abbr'])->keyBy('team_id');

            $divTeams = $records->map(function ($r) use ($teamInfo) {
                $info = $teamInfo[(int)$r->team_id] ?? null;
                $r->name     = $info->name ?? '';
                $r->nickname = $info->nickname ?? '';
                $r->abbr     = $info->abbr ?? '';
                return $r;
            })->sortByDesc(fn ($t) => ($t->w + $t->l) > 0 ? $t->w / ($t->w + $t->l) : 0)->values();
        } else {
            // Minors: use league_id + division_id
            $leagueName = $this->safeQuery(fn () =>
                DB::table('leagues')->where('league_id', $team->league_id)->value('name')
            ) ?? '';
            $divName = $this->safeQuery(fn () =>
                DB::table('divisions')
                    ->where('division_id', $team->division_id)
                    ->where('league_id', $team->league_id)
                    ->value('name')
            );
            $divName = $divName ? $leagueName . ' — ' . $divName : $leagueName;

            // Get all teams in same league + division
            $leagueTeamIds = DB::table('teams')
                ->where('league_id', $team->league_id)
                ->where('division_id', $team->division_id)
                ->pluck('team_id')->toArray();

            $records = DB::table('team_record')->whereIn('team_id', $leagueTeamIds)->get();
            $teamInfo = DB::table('teams')->whereIn('team_id', $leagueTeamIds)
                ->get(['team_id', 'name', 'nickname', 'abbr'])->keyBy('team_id');

            $divTeams = $records->map(function ($r) use ($teamInfo) {
                $info = $teamInfo[(int)$r->team_id] ?? null;
                $r->name     = $info->name ?? '';
                $r->nickname = $info->nickname ?? '';
                $r->abbr     = $info->abbr ?? '';
                return $r;
            })->sortByDesc(fn ($t) => ($t->w + $t->l) > 0 ? $t->w / ($t->w + $t->l) : 0)->values();
        }

        if ($divTeams->isEmpty()) return null;

        $leader = $divTeams->first();
        $divTeams = $divTeams->map(function ($t) use ($leader) {
            $total = $t->w + $t->l;
            $t->pct = $total > 0
                ? ($t->w / $total >= 1 ? '1.000' : '.'.str_pad((string)round(($t->w / $total) * 1000), 3, '0', STR_PAD_LEFT))
                : '.000';
            if ($leader && $t->team_id === $leader->team_id) {
                $t->gb = '-';
            } else {
                $raw = (($leader->w - $t->w) + ($t->l - $leader->l)) / 2;
                $t->gb = $raw <= 0 ? '-' : (fmod($raw, 1) === 0.5
                    ? number_format($raw, 1)
                    : (string)(int)$raw);
            }
            return $t;
        });

        return ['division_name' => $divName, 'teams' => $divTeams];
    }

    // -------------------------------------------------------------------------
    // Players — Legends / Featured
    // -------------------------------------------------------------------------

    /**
     * OOTP player position codes (used in `players.position`).
     * 1=SP, 2=RP, 3=C, 4=1B, 5=2B, 6=3B, 7=SS, 8=LF, 9=CF, 10=RF, 11=DH
     */
    public const POSITIONS = [
        0 => '',   1 => 'P',  2 => 'C',   3 => '1B',
        4 => '2B', 5 => '3B', 6 => 'SS',
        7 => 'LF', 8 => 'CF', 9 => 'RF',  10 => 'DH',
    ];

    /**
     * Standard fielding position codes (used in `players_game_batting.position`).
     * Follows scorebook numbering: 1=P, 2=C, 3=1B, 4=2B, 5=3B, 6=SS, 7=LF, 8=CF, 9=RF, 10=DH
     */
    public const FIELDING_POSITIONS = [
        0 => '',   1 => 'P',
        2 => 'C',  3 => '1B', 4 => '2B', 5 => '3B', 6 => 'SS',
        7 => 'LF', 8 => 'CF', 9 => 'RF', 10 => 'DH',
    ];

    public const POSITION_GROUPS = [
        'pitchers'  => [1, 2],
        'catchers'  => [3],
        'infield'   => [4, 5, 6, 7],
        'outfield'  => [8, 9, 10],
        'dh'        => [11],
    ];

    public function positionName(int $pos): string
    {
        return self::POSITIONS[$pos] ?? '';
    }

    /**
     * Top batters for a specific season year by OPS (min 100 AB).
     * Uses players_career_batting_stats filtered by year.
     */
    public function seasonTopBatters(int $year, int $limit = 10): \Illuminate\Support\Collection
    {
        return $this->safeQuery(fn () =>
            DB::table('players_career_batting_stats as s')
                ->join('players as p', 'p.player_id', '=', 's.player_id')
                ->leftJoin('teams as t', 't.team_id', '=', 's.team_id')
                ->where('s.year', $year)
                ->where('s.split_id', 1)   // split_id 1 = full season totals
                ->where('s.league_id', 100) // MLB only
                ->where('p.position', '>=', 3)
                ->select(
                    'p.player_id', 'p.first_name', 'p.last_name', 'p.nick_name', 'p.position',
                    't.name as team_name', 't.abbr as team_abbr', 't.nickname as team_nickname',
                    DB::raw('SUM(s.ab) as ab'),
                    DB::raw('SUM(s.h)  as h'),
                    DB::raw('SUM(s.hr) as hr'),
                    DB::raw('SUM(s.rbi) as rbi'),
                    DB::raw('SUM(s.bb) as bb'),
                    DB::raw('SUM(s.hp) as hp'),
                    DB::raw('SUM(s.sf) as sf'),
                    DB::raw('SUM(s.d)  as d'),
                    DB::raw('SUM(s.t)  as t_triples'),
                    DB::raw('SUM(s.sb) as sb'),
                    DB::raw('SUM(s.war) as war'),
                )
                ->groupBy('p.player_id','p.first_name','p.last_name','p.nick_name','p.position','t.name','t.abbr','t.nickname')
                ->havingRaw('SUM(s.ab) >= 100')
                ->orderByRaw('SUM(s.war) DESC')
                ->limit($limit)
                ->get()
        ) ?? collect();
    }

    /**
     * Top pitchers for a specific season year by ERA (min 50 outs = ~16 IP).
     * Uses players_career_pitching_stats filtered by year.
     */
    public function seasonTopPitchers(int $year, int $limit = 10): \Illuminate\Support\Collection
    {
        return $this->safeQuery(fn () =>
            DB::table('players_career_pitching_stats as s')
                ->join('players as p', 'p.player_id', '=', 's.player_id')
                ->leftJoin('teams as t', 't.team_id', '=', 's.team_id')
                ->where('s.year', $year)
                ->where('s.split_id', 1)
                ->where('s.league_id', 100) // MLB only
                ->where('p.position', '<=', 2)
                ->select(
                    'p.player_id', 'p.first_name', 'p.last_name', 'p.nick_name', 'p.position',
                    't.name as team_name', 't.abbr as team_abbr', 't.nickname as team_nickname',
                    DB::raw('SUM(s.w)    as w'),
                    DB::raw('SUM(s.l)    as l'),
                    DB::raw('SUM(s.outs) as outs'),
                    DB::raw('SUM(s.er)   as er'),
                    DB::raw('SUM(s.k)    as k'),
                    DB::raw('SUM(s.ha)   as h'),
                    DB::raw('SUM(s.bb)   as bb'),
                    DB::raw('SUM(s.s)      as sv'),
                    DB::raw('SUM(s.war)    as war'),
                    DB::raw('SUM(s.ra9war) as ra9war'),
                )
                ->groupBy('p.player_id','p.first_name','p.last_name','p.nick_name','p.position','t.name','t.abbr','t.nickname')
                ->havingRaw('SUM(s.outs) >= 50')
                ->orderByRaw('SUM(s.war) DESC')
                ->limit($limit)
                ->get()
        ) ?? collect();
    }

    /**
     * OOTP award_id → display name mapping.
     * finish = 1 means winner; > 1 means runner-up / voting finish.
     *
     * 0  = Player of the Week  (ignored)
     * 1  = Pitcher of the Month (ignored)
     * 2  = Batter of the Month  (ignored)
     * 4  = Cy Young Award       (finish=1 only)
     * 5  = MVP                  (finish=1 only)
     * 6  = Rookie of the Year   (finish=1 only)
     * 7  = Gold Glove           (position listed)
     * 9  = All-Star
     * 11 = Silver Slugger       (position listed)
     * 13 = Reliever of the Year (finish=1 only)
     * 14 = World Series Champion
     * 15 = World Series MVP
     */
    public const AWARD_NAMES = [
        4  => 'Cy Young Award',
        5  => 'MVP',
        6  => 'Rookie of the Year',
        7  => 'Gold Glove',
        9  => 'All-Star',
        11 => 'Silver Slugger',
        13 => 'Reliever of the Year',
        14 => 'World Series Champion',
        15 => 'World Series MVP',
    ];

    /** award_ids where only finish=1 is meaningful (winners only). */
    public const AWARD_WINNERS_ONLY = [4, 5, 6, 13, 15];

    /**
     * Career batting totals for a list of players (split_id=1 across all years).
     * Returns array keyed by player_id.
     */
    public function playersCareerBattingTotals(array $playerIds): array
    {
        if (empty($playerIds)) return [];

        $rows = $this->safeQuery(fn () =>
            DB::table('players_career_batting_stats')
                ->whereIn('player_id', $playerIds)
                ->where('split_id', 1)
                ->where('league_id', 100) // MLB career totals only
                ->select(
                    'player_id',
                    DB::raw('SUM(ab)  as ab'),  DB::raw('SUM(h)   as h'),
                    DB::raw('SUM(hr)  as hr'),  DB::raw('SUM(rbi) as rbi'),
                    DB::raw('SUM(bb)  as bb'),  DB::raw('SUM(hp)  as hp'),
                    DB::raw('SUM(sf)  as sf'),  DB::raw('SUM(d)   as d'),
                    DB::raw('SUM(t)   as t'),   DB::raw('SUM(sb)  as sb'),
                    DB::raw('SUM(war) as war'),
                )
                ->groupBy('player_id')
                ->get()
        ) ?? collect();

        $result = [];
        foreach ($rows as $row) {
            $ab  = (int)$row->ab; $h = (int)$row->h;
            $bb  = (int)$row->bb; $hp = (int)$row->hp; $sf = (int)$row->sf;
            $d   = (int)$row->d;  $t  = (int)$row->t;  $hr = (int)$row->hr;
            $avg = $ab > 0 ? $h / $ab : 0;
            $obp = ($ab+$bb+$hp+$sf) > 0 ? ($h+$bb+$hp)/($ab+$bb+$hp+$sf) : 0;
            $slg = $ab > 0 ? (($h-$d-$t-$hr)+2*$d+3*$t+4*$hr)/$ab : 0;
            $row->avg = $avg;
            $row->ops = $obp + $slg;
            $result[(int)$row->player_id] = $row;
        }
        return $result;
    }

    /**
     * Career pitching totals for a list of players (split_id=1 across all years).
     * Returns array keyed by player_id.
     */
    public function playersCareerPitchingTotals(array $playerIds): array
    {
        if (empty($playerIds)) return [];

        $rows = $this->safeQuery(fn () =>
            DB::table('players_career_pitching_stats')
                ->whereIn('player_id', $playerIds)
                ->where('split_id', 1)
                ->where('league_id', 100) // MLB career totals only
                ->select(
                    'player_id',
                    DB::raw('SUM(w)    as w'),   DB::raw('SUM(l)    as l'),
                    DB::raw('SUM(s)    as sv'),  DB::raw('SUM(outs) as outs'),
                    DB::raw('SUM(er)   as er'),  DB::raw('SUM(ha)   as h'),
                    DB::raw('SUM(bb)   as bb'),  DB::raw('SUM(k)    as k'),
                    DB::raw('SUM(war)  as war'),
                )
                ->groupBy('player_id')
                ->get()
        ) ?? collect();

        $result = [];
        foreach ($rows as $row) {
            $outs = (int)$row->outs;
            $ip   = $outs / 3;
            $row->era  = $ip > 0 ? number_format(((int)$row->er / $ip) * 9, 2) : '—';
            $row->ip_display = floor($outs/3) . '.' . ($outs%3);
            $result[(int)$row->player_id] = $row;
        }
        return $result;
    }

    /**
     * All career awards for a list of players.
     * Returns array keyed by player_id → array of award objects.
     */
    public function playersCareerAwards(array $playerIds): array
    {
        if (empty($playerIds)) return [];

        // OOTP awards (wiped/recreated on every import)
        $rows = $this->safeQuery(fn () =>
            DB::table('players_awards')
                ->whereIn('player_id', $playerIds)
                ->whereIn('award_id', array_keys(self::AWARD_NAMES))
                ->orderByDesc('year')
                ->orderBy('award_id')
                ->get()
        ) ?? collect();

        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row->player_id][] = $row;
        }

        // League-managed awards (survive OOTP imports — stored in Laravel DB)
        $leagueRows = DB::table('league_player_awards')
            ->whereIn('player_id', $playerIds)
            ->get();

        foreach ($leagueRows as $row) {
            // Normalise to same shape the blade expects: award_id, finish, year
            $result[(int)$row->player_id][] = (object)[
                'player_id' => $row->player_id,
                'award_id'  => 'league_' . $row->award_slug,
                'award_name'=> $row->award_name,
                'year'      => $row->year,
                'finish'    => 1,
            ];
        }

        return $result;
    }

    /**
     * Load awards for a list of players in a given year.
     * Returns array keyed by player_id → array of award objects.
     */
    public function playerAwardsByYear(array $playerIds, int $year): array
    {
        if (empty($playerIds)) return [];

        $rows = $this->safeQuery(fn () =>
            DB::table('players_awards')
                ->whereIn('player_id', $playerIds)
                ->where('year', $year)
                ->whereIn('award_id', array_keys(self::AWARD_NAMES))
                ->orderBy('award_id')
                ->get()
        ) ?? collect();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->player_id][] = $row;
        }
        return $result;
    }

    /** Top Hall-of-Fame position players by career HR. */
    public function featuredBatters(int $limit = 5): \Illuminate\Support\Collection
    {
        return $this->safeQuery(fn () =>
            DB::table('players_career_batting_stats as s')
                ->join('players as p', 'p.player_id', '=', 's.player_id')
                ->leftJoin('teams as t', 't.team_id', '=', 'p.team_id')
                ->where('p.hall_of_fame', 1)
                ->where('p.position', '>=', 3)
                ->select(
                    'p.player_id', 'p.first_name', 'p.last_name', 'p.nick_name', 'p.position',
                    't.name as team_name', 't.abbr as team_abbr',
                    DB::raw('SUM(s.hr)  as career_hr'),
                    DB::raw('SUM(s.rbi) as career_rbi'),
                    DB::raw('SUM(s.h)   as career_h'),
                    DB::raw('SUM(s.ab)  as career_ab'),
                )
                ->groupBy('p.player_id', 'p.first_name', 'p.last_name', 'p.nick_name', 'p.position', 't.name', 't.abbr')
                ->orderByDesc('career_hr')
                ->limit($limit)
                ->get()
        ) ?? collect();
    }

    /** Top Hall-of-Fame pitchers by career wins. */
    public function featuredPitchers(int $limit = 5): \Illuminate\Support\Collection
    {
        return $this->safeQuery(fn () =>
            DB::table('players_career_pitching_stats as s')
                ->join('players as p', 'p.player_id', '=', 's.player_id')
                ->leftJoin('teams as t', 't.team_id', '=', 'p.team_id')
                ->where('p.hall_of_fame', 1)
                ->where('p.position', '<=', 2)
                ->select(
                    'p.player_id', 'p.first_name', 'p.last_name', 'p.nick_name', 'p.position',
                    't.name as team_name', 't.abbr as team_abbr',
                    DB::raw('SUM(s.w)   as career_w'),
                    DB::raw('SUM(s.l)   as career_l'),
                    DB::raw('SUM(s.k)   as career_k'),
                    DB::raw('SUM(s.er)  as career_er'),
                    DB::raw('SUM(s.outs) as career_outs'),
                )
                ->groupBy('p.player_id', 'p.first_name', 'p.last_name', 'p.nick_name', 'p.position', 't.name', 't.abbr')
                ->orderByDesc('career_w')
                ->limit($limit)
                ->get()
        ) ?? collect();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Safely execute a DB query — returns null if the table doesn't exist yet
     * (i.e. before the first OOTP import).
     */
    private function safeQuery(callable $query): mixed
    {
        try {
            return $query();
        } catch (\Throwable) {
            return null;
        }
    }
}
