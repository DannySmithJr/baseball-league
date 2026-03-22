<?php

namespace App\Http\Controllers;

use App\Services\OotpService;

class StandingsController extends Controller
{
    public function __invoke(OotpService $ootp)
    {
        $playoff    = $ootp->playoffConfig();
        $divWinners = $playoff['division_winners'];
        $wildcards  = $playoff['wildcards'];

        $standingsByDivision = $ootp->standingsByDivision();

        // Mark playoff spots per sub-league
        foreach ($standingsByDivision as &$subLeague) {
            $wildCandidates = collect();

            foreach ($subLeague['divisions'] as &$division) {
                foreach ($division['teams'] as $i => &$team) {
                    $team->playoff_div  = $divWinners && $i === 0;
                    $team->playoff_wild = false;
                    if ($i > 0 && $wildcards > 0) {
                        $wildCandidates->push($team);
                    }
                }
                unset($team);
            }
            unset($division);

            if ($wildcards > 0) {
                $sorted = $wildCandidates->sortByDesc(fn ($t) =>
                    ($t->w + $t->l) > 0 ? $t->w / ($t->w + $t->l) : 0
                )->values();

                foreach ($sorted->take($wildcards) as $wt) {
                    $wt->playoff_wild = true;
                }
            }

            foreach ($subLeague['divisions'] as &$division) {
                foreach ($division['teams'] as &$team) {
                    $team->playoff_spot = $team->playoff_div || $team->playoff_wild;
                }
                unset($team);
            }
            unset($division);
        }
        unset($subLeague);

        return view('standings.index', compact('standingsByDivision', 'divWinners', 'wildcards'));
    }
}
