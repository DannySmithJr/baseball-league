@extends('layouts.public')
@section('title', 'Schedule')

@section('content')

{{-- Page header --}}
<div class="border-b border-gray-800 bg-gray-900/40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h1 class="text-2xl font-extrabold tracking-tight">Schedule</h1>
            <form method="GET" action="{{ route('schedule') }}">
                <input type="hidden" name="date" value="{{ $date }}">
                <select name="team" onchange="this.form.submit()"
                    class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-2 text-sm text-gray-200
                           focus:outline-none focus:border-red-500 transition min-w-[200px]">
                    <option value="0" {{ !$teamId ? 'selected' : '' }}>All Teams</option>
                    @foreach($allTeams as $t)
                        <option value="{{ $t->team_id }}" {{ $teamId == $t->team_id ? 'selected' : '' }}>
                            {{ $t->name }} {{ $t->nickname ?? '' }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">

    {{-- Date navigation --}}
    <div class="flex items-center gap-2 mb-5 flex-wrap">
        <a href="{{ route('schedule', ['date' => $prevDate, 'team' => $teamId]) }}"
           class="flex items-center gap-1 px-3 py-1.5 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800 transition text-sm font-medium whitespace-nowrap">
            &lsaquo; {{ \Carbon\Carbon::parse($prevDate)->format('M j') }}
        </a>
        <a href="{{ route('schedule', ['date' => $todayDate, 'team' => $teamId]) }}"
           class="px-3 py-1.5 rounded-lg text-sm font-semibold transition whitespace-nowrap
                  {{ $date === $todayDate ? 'bg-red-600 text-white' : 'border border-gray-700 text-gray-300 hover:bg-gray-800' }}">
            Today
        </a>
        <a href="{{ route('schedule', ['date' => $nextDate, 'team' => $teamId]) }}"
           class="flex items-center gap-1 px-3 py-1.5 rounded-lg border border-gray-700 text-gray-300 hover:bg-gray-800 transition text-sm font-medium whitespace-nowrap">
            {{ \Carbon\Carbon::parse($nextDate)->format('M j') }} &rsaquo;
        </a>
        <form method="GET" action="{{ route('schedule') }}" class="ml-1">
            <input type="hidden" name="team" value="{{ $teamId }}">
            <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()"
                   class="bg-gray-800 border border-gray-700 rounded-lg px-3 py-1.5 text-sm text-gray-300
                          focus:outline-none focus:border-red-500 transition cursor-pointer">
        </form>
    </div>

    {{-- Date heading --}}
    <div class="mb-4">
        <h2 class="text-lg font-bold text-white">{{ \Carbon\Carbon::parse($date)->format('l, F j, Y') }}</h2>
        <p class="text-xs text-gray-500 mt-0.5">{{ $games->count() }} {{ $games->count() === 1 ? 'game' : 'games' }} scheduled</p>
    </div>

    @if($games->isEmpty())
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-12 text-center text-gray-500 text-sm">
            No games scheduled for this date.
        </div>
    @else

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">

        @foreach($games as $game)
        @php
            $played   = (int)$game->played === 1;
            $awayRec  = $homeAwayRecs[$game->away_team] ?? null;
            $homeRec  = $homeAwayRecs[$game->home_team] ?? null;
            $awayLogo = $teamLogos[$game->away_team] ?? null;
            $homeLogo = $teamLogos[$game->home_team] ?? null;
            $stream   = $gameStreams[$game->game_id] ?? null;
            $s0       = ((int)($game->starter0 ?? 0) > 0) ? ($starterStats[(int)$game->starter0] ?? null) : null;
            $s1       = ((int)($game->starter1 ?? 0) > 0) ? ($starterStats[(int)$game->starter1] ?? null) : null;

            $etOffset = $teamTzOffsets[(int)$game->home_team] ?? 0;
            if ($game->time) {
                $lh      = intdiv((int)$game->time, 100) + $etOffset;
                $lm      = (int)$game->time % 100;
                $ampm    = $lh >= 12 ? 'PM' : 'AM';
                $h12     = $lh % 12 ?: 12;
                $timeStr = sprintf('%d:%02d %s ET', $h12, $lm, $ampm);
            } else {
                $timeStr = 'TBD';
            }

            $awayOvr  = $awayRec ? ($awayRec['road_w'] + $awayRec['home_w']).'-'.($awayRec['road_l'] + $awayRec['home_l']) : null;
            $homeOvr  = $homeRec ? ($homeRec['home_w'] + $homeRec['road_w']).'-'.($homeRec['home_l'] + $homeRec['road_l']) : null;

            $parkLocation = implode(', ', array_filter([$game->park_city ?? null, $game->park_state ?? null]));
            $awayWon = $played && (int)$game->runs0 > (int)$game->runs1;
            $homeWon = $played && (int)$game->runs1 > (int)$game->runs0;
            $odds = $gameOdds[$game->game_id] ?? null;

            // Team batting/rpg stats for unplayed card
            $awayBat = $teamBatting[$game->away_team] ?? null;
            $homeBat = $teamBatting[$game->home_team] ?? null;
            $awayRpg = $runsPerGame[$game->away_team]['rpg'] ?? null;
            $homeRpg = $runsPerGame[$game->home_team]['rpg'] ?? null;

            // Starters for the component (controller clears these beyond 7 days)
            $awayStarterData = $game->starter0_name
                ? ['name' => $game->starter0_name, 'w' => $s0['w'] ?? 0, 'l' => $s0['l'] ?? 0, 'era' => $s0['era'] ?? '-.--', 'k' => $s0['k'] ?? 0]
                : null;
            $homeStarterData = $game->starter1_name
                ? ['name' => $game->starter1_name, 'w' => $s1['w'] ?? 0, 'l' => $s1['l'] ?? 0, 'era' => $s1['era'] ?? '-.--', 'k' => $s1['k'] ?? 0]
                : null;
        @endphp

        @if($played)
        {{-- ── PLAYED CARD ── --}}
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden flex flex-col">
            @php
                $extraInnings = $game->innings && (int)$game->innings !== 9;
                $finalLabel   = 'Final' . ($extraInnings ? ' /' . $game->innings : '');
                $wpRec  = $game->winning_pitcher ? ($starterStats[(int)$game->winning_pitcher] ?? null) : null;
                $lpRec  = $game->losing_pitcher  ? ($starterStats[(int)$game->losing_pitcher]  ?? null) : null;
                $svpRec = $game->save_pitcher    ? ($starterStats[(int)$game->save_pitcher]    ?? null) : null;
                $decisionLine = '';
                if ($game->wp_name) {
                    $decisionLine .= 'W: ' . $game->wp_name . ($wpRec ? ' (' . $wpRec['w'] . '-' . $wpRec['l'] . ')' : '');
                }
                if ($game->lp_name) {
                    $decisionLine .= ($decisionLine ? '   ' : '') . 'L: ' . $game->lp_name . ($lpRec ? ' (' . $lpRec['w'] . '-' . $lpRec['l'] . ')' : '');
                }
                if ($game->svp_name) {
                    $decisionLine .= ($decisionLine ? '   ' : '') . 'SV: ' . $game->svp_name . ($svpRec ? ' (' . $svpRec['sv'] . ')' : '');
                }
            @endphp

            {{-- Final header + R H E labels --}}
            <div class="px-4 pt-3 pb-1 flex items-center justify-between">
                <span class="text-xs font-bold text-gray-500 uppercase tracking-wide">{{ $finalLabel }}</span>
                <div class="flex gap-4 text-[10px] font-bold text-gray-600 uppercase tracking-wider pr-1">
                    <span class="w-4 text-center">R</span>
                    <span class="w-4 text-center">H</span>
                    <span class="w-4 text-center">E</span>
                </div>
            </div>

            {{-- Away team row --}}
            <div class="px-4 pt-1 flex items-center justify-between gap-2">
                <div class="flex items-center gap-1.5 min-w-0">
                    @if($awayWon)
                        <span class="text-red-500 text-xs font-bold leading-none">&#9658;</span>
                    @else
                        <span class="w-2.5 flex-shrink-0 inline-block"></span>
                    @endif
                    @if($awayLogo)
                        <img src="/images/logos/{{ $awayLogo }}" alt="{{ $game->away_abbr }}" class="w-7 h-7 object-contain flex-shrink-0">
                    @else
                        <div class="w-7 h-7 flex-shrink-0"></div>
                    @endif
                    <div class="min-w-0 leading-tight">
                        <span class="text-sm font-bold {{ $awayWon ? 'text-white' : 'text-gray-500' }}">{{ $game->away_name }} {{ $game->away_nickname }}</span>
                        @if($awayOvr)
                            <span class="text-[11px] text-gray-600 ml-1">{{ $awayOvr }}</span>
                        @endif
                    </div>
                </div>
                <div class="flex gap-4 flex-shrink-0 pr-1">
                    <span class="w-4 text-center text-sm font-bold {{ $awayWon ? 'text-white' : 'text-gray-600' }}">{{ $game->runs0 }}</span>
                    <span class="w-4 text-center text-sm {{ $awayWon ? 'text-gray-300' : 'text-gray-600' }}">{{ $game->hits0 ?? '-' }}</span>
                    <span class="w-4 text-center text-sm text-gray-600">{{ $game->errors0 ?? '-' }}</span>
                </div>
            </div>

            {{-- Home team row --}}
            <div class="px-4 pt-1 pb-2 flex items-center justify-between gap-2">
                <div class="flex items-center gap-1.5 min-w-0">
                    @if($homeWon)
                        <span class="text-red-500 text-xs font-bold leading-none">&#9658;</span>
                    @else
                        <span class="w-2.5 flex-shrink-0 inline-block"></span>
                    @endif
                    @if($homeLogo)
                        <img src="/images/logos/{{ $homeLogo }}" alt="{{ $game->home_abbr }}" class="w-7 h-7 object-contain flex-shrink-0">
                    @else
                        <div class="w-7 h-7 flex-shrink-0"></div>
                    @endif
                    <div class="min-w-0 leading-tight">
                        <span class="text-sm font-bold {{ $homeWon ? 'text-white' : 'text-gray-500' }}">{{ $game->home_name }} {{ $game->home_nickname }}</span>
                        @if($homeOvr)
                            <span class="text-[11px] text-gray-600 ml-1">{{ $homeOvr }}</span>
                        @endif
                    </div>
                </div>
                <div class="flex gap-4 flex-shrink-0 pr-1">
                    <span class="w-4 text-center text-sm font-bold {{ $homeWon ? 'text-white' : 'text-gray-600' }}">{{ $game->runs1 }}</span>
                    <span class="w-4 text-center text-sm {{ $homeWon ? 'text-gray-300' : 'text-gray-600' }}">{{ $game->hits1 ?? '-' }}</span>
                    <span class="w-4 text-center text-sm text-gray-600">{{ $game->errors1 ?? '-' }}</span>
                </div>
            </div>

            {{-- Location --}}
            <div class="px-4 pb-2">
                <p class="text-xs text-gray-600 leading-snug">
                    {{ implode(', ', array_filter([$game->park_name ?? null, $parkLocation ?: null])) }}
                </p>
            </div>

            {{-- W / L / SV decisions --}}
            <div class="px-4 pb-3 border-b border-gray-800/60">
                <p class="text-xs text-gray-500 leading-snug">{{ $decisionLine ?: '&nbsp;' }}</p>
            </div>

            {{-- Box score / Game log links --}}
            <div class="px-4 py-2.5 mt-auto text-center flex justify-center gap-4">
                <a href="{{ route('game', $game->game_id) }}"
                   class="text-sm font-semibold text-red-400 hover:text-red-300 transition">
                    Box Score
                </a>
                <span class="text-gray-700">|</span>
                <a href="{{ route('game.logs', $game->game_id) }}"
                   class="text-sm font-semibold text-gray-500 hover:text-gray-300 transition">
                    Game Log
                </a>
            </div>

        </div>

        @else
        {{-- ── UNPLAYED CARD (shared component) ── --}}
        @php $card = $matchupCardsByGame[$game->game_id] ?? null; @endphp
        @if($card)
        <x-game-matchup
            :time="$card['time']"
            :away="$card['away']"
            :home="$card['home']"
            :awayStarter="$card['awayStarter']"
            :homeStarter="$card['homeStarter']"
            :odds="$card['odds']"
            :location="$card['location']"
            :stream="$card['stream']"
        />
        @endif
        @endif

        @endforeach

    </div>
    @endif

</div>

@endsection
