<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Services\OotpService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function __invoke(OotpService $ootp)
    {
        $stats = [
            'teams'   => $ootp->teams()->count(),
            'gms'     => User::where('role', 'gm')->count(),
            'ccps'    => User::where('role', 'ccp')->count(),
            'members' => User::count(),
        ];

        $currentYear = $ootp->seasonYear();
        $legendYear  = $currentYear ? $currentYear - 1 : null;

        // Cache key includes ootp_last_import — changes on every import, auto-invalidating the cache.
        $lastImport  = Setting::instance()->ootp_last_import ?? 'none';
        $cacheKey    = "home.legends.{$legendYear}.{$lastImport}";

        $legendsJson = Cache::remember($cacheKey, 86400, function () use ($ootp, $legendYear) {
            if ($legendYear) {
                $batters  = $ootp->seasonTopBatters($legendYear, 10);
                $pitchers = $ootp->seasonTopPitchers($legendYear, 10);
            } else {
                $batters  = $ootp->featuredBatters(10);
                $pitchers = $ootp->featuredPitchers(10);
            }

            $allIds     = $batters->pluck('player_id')->merge($pitchers->pluck('player_id'))->unique()->toArray();
            $batterIds  = $batters->pluck('player_id')->toArray();
            $pitcherIds = $pitchers->pluck('player_id')->toArray();

            return json_encode([
                'batters'        => $batters->values()->all(),
                'pitchers'       => $pitchers->values()->all(),
                'careerBatting'  => $ootp->playersCareerBattingTotals($batterIds),
                'careerPitching' => $ootp->playersCareerPitchingTotals($pitcherIds),
                'careerAwards'   => $ootp->playersCareerAwards($allIds),
            ]);
        });

        $legends = json_decode($legendsJson);

        // careerBatting/Pitching are keyed by player_id — restore as associative arrays of objects
        $batters        = collect($legends->batters);
        $pitchers       = collect($legends->pitchers);
        $careerBatting  = (array) $legends->careerBatting;
        $careerPitching = (array) $legends->careerPitching;
        $careerAwards   = array_map(fn($v) => (array) $v, (array) $legends->careerAwards);

        // Twitch usernames for any CCP players on the cards
        $allIds = $batters->pluck('player_id')->merge($pitchers->pluck('player_id'))->unique()->toArray();
        $ccpTwitch = DB::table('ccps')
            ->join('users', 'users.id', '=', 'ccps.user_id')
            ->whereIn('ccps.player_id', $allIds)
            ->whereNotNull('users.twitch_username')
            ->pluck('users.twitch_username', 'ccps.player_id')
            ->toArray();

        return view('welcome', compact(
            'stats', 'batters', 'pitchers', 'legendYear',
            'careerBatting', 'careerPitching', 'careerAwards', 'ccpTwitch',
        ));
    }
}
