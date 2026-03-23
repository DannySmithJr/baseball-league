<?php

namespace App\Http\Controllers;

use App\Services\OotpService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PlayerController extends Controller
{
    /** Shared base data for header + nav on all player tabs. */
    private function baseData(int $id, OotpService $ootp): array
    {
        $player = $ootp->playerWithTeam($id);
        if (!$player) abort(404);

        $isPitcher = (int)$player->position === 1;
        $seasonBatting  = !$isPitcher ? $ootp->playerSeasonBatting($id)  : null;
        $seasonPitching = $isPitcher  ? $ootp->playerSeasonPitching($id) : null;
        $headerRanks    = $ootp->playerHeaderRanks($id, $isPitcher);
        $careerBatting  = !$isPitcher ? $ootp->playerCareerBatting($id)  : collect();
        $careerPitching = $isPitcher  ? $ootp->playerCareerPitching($id) : collect();
        $postseasonBatting  = $ootp->playerCareerBatting($id, 21);
        $postseasonPitching = $ootp->playerCareerPitching($id, 21);
        $careerFielding     = $ootp->playerCareerFielding($id);
        $awards         = $ootp->playerAllAwards($id);
        $contract       = $ootp->playerContract($id);

        return compact(
            'player', 'isPitcher', 'seasonBatting', 'seasonPitching',
            'headerRanks', 'careerBatting', 'careerPitching',
            'postseasonBatting', 'postseasonPitching',
            'careerFielding', 'awards', 'contract',
        );
    }

    /** Overview tab — season stats + career stats. */
    public function show(int $id, OotpService $ootp)
    {
        $html = Cache::remember("playerPage.{$id}", 3600, fn () => $this->_playerShow($id, $ootp)->render());
        return response($html);
    }

    private function _playerShow(int $id, OotpService $ootp)
    {
        $base = $this->baseData($id, $ootp);

        $mlbLeaders = $ootp->mlbLeaderValues();
        $years = $base['isPitcher']
            ? $base['careerPitching']->pluck('year')->unique()->toArray()
            : $base['careerBatting']->pluck('year')->unique()->toArray();
        $mlbLeadersByYear = $ootp->mlbLeaderValuesByYear($years);

        return view('players.overview', array_merge($base, [
            'activeTab'        => 'player',
            'mlbLeaders'       => $mlbLeaders,
            'mlbLeadersByYear' => $mlbLeadersByYear,
        ]));
    }

    /** News tab. */
    public function news(int $id, OotpService $ootp)
    {
        $base = $this->baseData($id, $ootp);
        $news = $ootp->playerNews($id, 30);

        return view('players.news', array_merge($base, [
            'activeTab' => 'player.news',
            'news'      => $news,
        ]));
    }

    /** Stats tab — full season + career tables. */
    public function stats(int $id, OotpService $ootp)
    {
        $type = request('type', '');
        $level = (int) request('level', 1);
        $html = Cache::remember("playerStats.{$id}.{$type}.{$level}", 3600, fn () => $this->_playerStats($id, $ootp)->render());
        return response($html);
    }

    private function _playerStats(int $id, OotpService $ootp)
    {
        $base = $this->baseData($id, $ootp);

        // Determine stat type from query param, default based on player position
        $statType = request('type');
        if (!in_array($statType, ['batting', 'pitching', 'fielding'])) {
            $statType = $base['isPitcher'] ? 'pitching' : 'batting';
        }

        // Level filter: 1=MLB, 2=AAA, 3=AA, 4=A+, 5=A, 6=Rk, etc.
        $levelFilter = (int) request('level', 1);
        $levelLabelsMap = [1 => 'MLB', 2 => 'AAA', 3 => 'AA', 4 => 'A+', 5 => 'A', 6 => 'Short A', 7 => 'Rk', 8 => 'College', 9 => 'HS'];

        // Determine which levels this player has data at
        $allData = collect()
            ->merge($base['careerBatting'])->merge($base['careerPitching'])->merge($base['careerFielding']);
        $availableLevels = $allData->pluck('team_level')->filter()->unique()->sort()->values()->toArray();

        // Filter career data by selected level
        $filterByLevel = fn($rows) => $rows->filter(fn($r) => (int)($r->team_level ?? 0) === $levelFilter);

        $mlbLeaders = $ootp->mlbLeaderValues();
        $years = $base['careerPitching']->isNotEmpty()
            ? $base['careerPitching']->pluck('year')->merge($base['careerBatting']->pluck('year'))->unique()->toArray()
            : $base['careerBatting']->pluck('year')->unique()->toArray();
        $mlbLeadersByYear = $ootp->mlbLeaderValuesByYear($years);

        return view('players.stats', array_merge($base, [
            'activeTab'        => 'player.stats',
            'statType'         => $statType,
            'levelFilter'      => $levelFilter,
            'levelLabelsMap'   => $levelLabelsMap,
            'availableLevels'  => $availableLevels,
            'filteredBatting'  => $filterByLevel($base['careerBatting']),
            'filteredPitching' => $filterByLevel($base['careerPitching']),
            'filteredFielding' => $filterByLevel($base['careerFielding']),
            'mlbLeaders'       => $mlbLeaders,
            'mlbLeadersByYear' => $mlbLeadersByYear,
        ]));
    }

    /** Ratings tab. */
    public function ratings(int $id, OotpService $ootp)
    {
        $html = Cache::remember("playerRatings.{$id}", 3600, fn () => $this->_playerRatings($id, $ootp)->render());
        return response($html);
    }

    private function _playerRatings(int $id, OotpService $ootp)
    {
        $base = $this->baseData($id, $ootp);
        $ratings = DB::table('players_scouted_ratings')->where('player_id', $id)->first();

        return view('players.ratings', array_merge($base, [
            'activeTab' => 'player.ratings',
            'ratings'   => $ratings,
        ]));
    }

    /** Bio tab — awards, contract, personal info. */
    public function bio(int $id, OotpService $ootp)
    {
        $base = $this->baseData($id, $ootp);

        return view('players.bio', array_merge($base, [
            'activeTab' => 'player.bio',
        ]));
    }

    /** Contract tab. */
    public function contract(int $id, OotpService $ootp)
    {
        $base = $this->baseData($id, $ootp);
        $contract = $base['contract'];
        $player   = $base['player'];

        $salaryRanks = null;
        if ($contract && (int)($contract->salary0 ?? 0) > 0) {
            $mySalary = (int)$contract->salary0;
            $myPos    = (int)$player->position;

            // All MLB salaries
            $allSalaries = DB::table('players_contract as c')
                ->join('teams as t', 't.team_id', '=', 'c.team_id')
                ->join('players as p', 'p.player_id', '=', 'c.player_id')
                ->where('t.level', 1)
                ->select('c.player_id', 'c.salary0', 'c.team_id', 'p.position')
                ->get();

            $overallRank = $allSalaries->sortByDesc('salary0')->values()
                ->search(fn($r) => (int)$r->player_id === $id) + 1;

            $posRank = $allSalaries->where('position', $myPos)->sortByDesc('salary0')->values()
                ->search(fn($r) => (int)$r->player_id === $id) + 1;

            $teamRank = $allSalaries->where('team_id', (int)$player->team_id)->sortByDesc('salary0')->values()
                ->search(fn($r) => (int)$r->player_id === $id) + 1;

            $posCount    = $allSalaries->where('position', $myPos)->count();
            $teamCount   = $allSalaries->where('team_id', (int)$player->team_id)->count();
            $totalCount  = $allSalaries->count();

            $ord = fn($n) => $n . match((int)$n % 10) {
                1 => (int)$n % 100 === 11 ? 'th' : 'st',
                2 => (int)$n % 100 === 12 ? 'th' : 'nd',
                3 => (int)$n % 100 === 13 ? 'th' : 'rd',
                default => 'th',
            };

            $salaryRanks = [
                'overall'    => $ord($overallRank) . ' of ' . $totalCount,
                'position'   => $ord($posRank) . ' of ' . $posCount,
                'team'       => $ord($teamRank) . ' of ' . $teamCount,
            ];
        }

        return view('players.contract', array_merge($base, [
            'activeTab'    => 'player.contract',
            'salaryRanks'  => $salaryRanks,
        ]));
    }

    /** Game Log tab. */
    public function gamelog(int $id, OotpService $ootp)
    {
        $base = $this->baseData($id, $ootp);

        return view('players.gamelog', array_merge($base, [
            'activeTab' => 'player.gamelog',
        ]));
    }
}
