@extends('layouts.public')

@section('title', $game->away_abbr . ' @ ' . $game->home_abbr . ' Game Log — ' . \Carbon\Carbon::parse($game->date)->format('M j, Y'))

@section('content')
@php
    $played    = (bool) $game->played;
    $awayId    = (int)  $game->away_team;
    $homeId    = (int)  $game->home_team;
    $results   = \App\Services\OotpService::AT_BAT_RESULTS;
    $awayWon   = $played && (int)$game->runs0 > (int)$game->runs1;
    $homeWon   = $played && (int)$game->runs1 > (int)$game->runs0;

    $ordinals = [1=>'1st',2=>'2nd',3=>'3rd',4=>'4th',5=>'5th',6=>'6th',7=>'7th',8=>'8th',9=>'9th',
                 10=>'10th',11=>'11th',12=>'12th',13=>'13th',14=>'14th',15=>'15th',16=>'16th',17=>'17th',18=>'18th'];
    $innLabel = fn(int $n) => $ordinals[$n] ?? $n.'th';
@endphp

{{-- ── GAME HEADER ── --}}
@php
    $awayRec = $homeAwayRecs[$awayId] ?? null;
    $homeRec = $homeAwayRecs[$homeId] ?? null;
    $awayOvr = $awayRec ? ($awayRec['home_w']+$awayRec['road_w']).'-'.($awayRec['home_l']+$awayRec['road_l']) : null;
    $homeOvr = $homeRec ? ($homeRec['home_w']+$homeRec['road_w']).'-'.($homeRec['home_l']+$homeRec['road_l']) : null;
    $awayLogo = $teamLogos[$awayId] ?? null;
    $homeLogo = $teamLogos[$homeId] ?? null;
@endphp
<div class="bg-gray-900 border-b border-gray-800">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
        <p class="text-xs text-gray-600 text-center mb-4">
            {{ \Carbon\Carbon::parse($game->date)->format('l, F j, Y') }}
            @if($played && $game->innings && (int)$game->innings > 9)
                &nbsp;·&nbsp; <span class="text-yellow-500">{{ $game->innings }} Innings</span>
            @endif
        </p>
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-3 flex-1 justify-end">
                <div class="text-right">
                    <a href="{{ route('team', $awayId) }}" class="text-lg font-extrabold {{ $awayWon ? 'text-white' : 'text-gray-400' }} hover:text-red-400 transition leading-tight block">{{ $game->away_name }}</a>
                    @if($awayOvr)<span class="text-xs text-gray-600">{{ $awayOvr }}</span>@endif
                </div>
                @if($awayLogo)
                    <img src="/images/logos/{{ $awayLogo }}" alt="{{ $game->away_abbr }}" class="w-10 h-10 object-contain flex-shrink-0 {{ $awayWon ? '' : 'opacity-50' }}">
                @endif
            </div>
            <div class="flex items-center gap-3 px-4 flex-shrink-0">
                @if($played)
                    <span class="text-4xl font-black tabular-nums {{ $awayWon ? 'text-white' : 'text-gray-500' }}">{{ $game->runs0 }}</span>
                    <div class="text-center">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">Final</p>
                    </div>
                    <span class="text-4xl font-black tabular-nums {{ $homeWon ? 'text-white' : 'text-gray-500' }}">{{ $game->runs1 }}</span>
                @endif
            </div>
            <div class="flex items-center gap-3 flex-1 justify-start">
                @if($homeLogo)
                    <img src="/images/logos/{{ $homeLogo }}" alt="{{ $game->home_abbr }}" class="w-10 h-10 object-contain flex-shrink-0 {{ $homeWon ? '' : 'opacity-50' }}">
                @endif
                <div class="text-left">
                    <a href="{{ route('team', $homeId) }}" class="text-lg font-extrabold {{ $homeWon ? 'text-white' : 'text-gray-400' }} hover:text-red-400 transition leading-tight block">{{ $game->home_name }}</a>
                    @if($homeOvr)<span class="text-xs text-gray-600">{{ $homeOvr }}</span>@endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── NAV TABS ── --}}
<div class="bg-gray-900 border-b border-gray-800">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 flex gap-6">
        <a href="{{ route('game', $game->game_id) }}"
           class="py-2.5 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-300 transition">Box Score</a>
        <a href="{{ route('game.logs', $game->game_id) }}"
           class="py-2.5 text-sm font-semibold border-b-2 border-red-500 text-white">Game Log</a>
    </div>
</div>

{{-- ── GAME LOG ── --}}
@if(!empty($atBats))
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-12">
    <h2 class="text-xs font-bold tracking-widest text-red-500 uppercase mb-5">Game Log</h2>

    @php
        $abIdx  = 0;

        $pitchLogByKey = [];
        foreach ($atBatLogs as $_abl) {
            $_pid2 = $_abl['player_id'] ?? null;
            if ($_pid2 === null) continue;
            $pitchLogByKey[$_pid2 . '_' . $_abl['inning'] . '_' . $_abl['half']] = $_abl['pitches'];
        }

        $_seenAbKeys = [];
        foreach ($atBats as $_h) {
            $_hHalf = $_h['half'];
            foreach ($_h['atbats'] as $_ab) {
                $_seenAbKeys[(int)$_ab->player_id . '_' . (int)$_ab->inning . '_' . $_hHalf] = true;
            }
        }
        $_spotByPlayer  = [];
        $_lastAbByHalf  = [];
        foreach ($atBats as $_h) {
            $_hKey = $_h['inning'] . '_' . $_h['half'];
            foreach ($_h['atbats'] as $_ab) {
                $_spotByPlayer[(int)$_ab->player_id] = $_ab->spot;
                $_lastAbByHalf[$_hKey] = $_ab;
            }
        }
        $_toBase = ['first'=>1,'second'=>2,'third'=>3];
        foreach ($atBats as &$_halfRef) {
            $_runners = [1 => null, 2 => null, 3 => null];
            foreach ($_halfRef['atbats'] as &$_abRef) {
                $_abRef->_runner1 = $_runners[1];
                $_abRef->_runner2 = $_runners[2];
                $_abRef->_runner3 = $_runners[3];

                $_abPitches = $pitchLogByKey[(int)$_abRef->player_id . '_' . (int)$_abRef->inning . '_' . $_halfRef['half']] ?? [];
                $_fcReached = false;
                foreach ($_abPitches as $_p) {
                    if (preg_match('/^\d+-\d+:/i', $_p)) {
                        if (preg_match('/fielders? choice/i', $_p)) $_fcReached = true;
                        continue;
                    }
                    if      (preg_match('/caught stealing 2nd|picked off 1st/i', $_p))  { $_runners[1] = null; }
                    elseif  (preg_match('/caught stealing 3rd|picked off 2nd/i', $_p))  { $_runners[2] = null; }
                    elseif  (preg_match('/caught stealing home|picked off 3rd/i', $_p)) { $_runners[3] = null; }
                    elseif  (preg_match('/steals 2nd/i', $_p))  { $_runners[2] = $_runners[1]; $_runners[1] = null; }
                    elseif  (preg_match('/steals 3rd/i', $_p))  { $_runners[3] = $_runners[2]; $_runners[2] = null; }
                    elseif  (preg_match('/steals home/i', $_p)) { $_runners[3] = null; }
                    elseif  (preg_match('/^(.+?)\s+to\s+(first|second|third)/i', $_p, $_pm)) {
                        $_mn = trim($_pm[1]);
                        $_tb = $_toBase[strtolower($_pm[2])] ?? null;
                        if ($_tb) {
                            foreach ([1,2,3] as $_b) { if ($_runners[$_b] === $_mn) { $_runners[$_b] = null; break; } }
                            $_runners[$_tb] = $_mn;
                        }
                    }
                    elseif (preg_match('/^(.+?)\s+scores/i', $_p, $_pm)) {
                        $_mn = trim($_pm[1]);
                        foreach ([1,2,3] as $_b) { if ($_runners[$_b] === $_mn) { $_runners[$_b] = null; break; } }
                    }
                }

                $_rn   = (int)$_abRef->result;
                $_abName = trim(($_abRef->batter_first ?? '') . ' ' . ($_abRef->batter_last ?? ''));
                if      ($_rn === 9)              { $_runners[1] = $_runners[2] = $_runners[3] = null; }
                elseif  (in_array($_rn, [2, 10])) { $_runners[1] = $_abName; }
                elseif  ($_rn === 6)              { $_runners[1] = $_abName; }
                elseif  ($_rn === 7)              { $_runners[2] = $_abName; }
                elseif  ($_rn === 8)              { $_runners[3] = $_abName; }
                elseif  ($_fcReached)             { $_runners[1] = $_abName; }
                $_abRef->_fcReached = $_fcReached;
            }
            unset($_abRef);
        }
        unset($_halfRef);

        $orphansByHalf = [];
        foreach ($atBatLogs as $_abl) {
            $pid = $_abl['player_id'] ?? null;
            if ($pid === null) continue;
            $_abKey = $pid . '_' . $_abl['inning'] . '_' . $_abl['half'];
            if (!isset($_seenAbKeys[$_abKey])) {
                $_hKey   = $_abl['inning'] . '_' . $_abl['half'];
                $_prevAb = $_lastAbByHalf[$_hKey] ?? null;
                $_orphOuts = $_prevAb
                    ? (int)$_prevAb->outs + (in_array((int)$_prevAb->result, [1,4,5]) ? 1 : 0)
                    : null;
                $_r1 = $_prevAb ? (int)$_prevAb->base1 : 0;
                $_r2 = $_prevAb ? (int)$_prevAb->base2 : 0;
                $_r3 = $_prevAb ? (int)$_prevAb->base3 : 0;
                if ($_prevAb) {
                    $_pr = (int)$_prevAb->result;
                    if (in_array($_pr, [2,10])) { $_r3=($_r1&&$_r2)?1:$_r3; $_r2=$_r1?1:$_r2; $_r1=1; }
                    elseif ($_pr===6) { $_r3=$_r2; $_r2=$_r1; $_r1=1; }
                    elseif ($_pr===7) { $_r3=$_r1; $_r2=1; $_r1=0; }
                    elseif ($_pr===8) { $_r3=1; $_r2=0; $_r1=0; }
                    elseif ($_pr===9) { $_r1=$_r2=$_r3=0; }
                }
                $_n1 = $_n2 = $_n3 = null;
                if ($_prevAb) {
                    $_pn = [1 => $_prevAb->_runner1 ?? null, 2 => $_prevAb->_runner2 ?? null, 3 => $_prevAb->_runner3 ?? null];
                    $_prevPitches = $pitchLogByKey[(int)$_prevAb->player_id . '_' . (int)$_prevAb->inning . '_' . $_abl['half']] ?? [];
                    $_prevFcReached = false;
                    foreach ($_prevPitches as $_pp) {
                        if (preg_match('/^\d+-\d+:/i', $_pp)) {
                            if (preg_match('/fielders? choice/i', $_pp)) $_prevFcReached = true;
                            continue;
                        }
                        if      (preg_match('/caught stealing 2nd|picked off 1st/i', $_pp))  { $_pn[1] = null; }
                        elseif  (preg_match('/caught stealing 3rd|picked off 2nd/i', $_pp))  { $_pn[2] = null; }
                        elseif  (preg_match('/caught stealing home|picked off 3rd/i', $_pp)) { $_pn[3] = null; }
                        elseif  (preg_match('/steals 2nd/i', $_pp))  { $_pn[2] = $_pn[1]; $_pn[1] = null; }
                        elseif  (preg_match('/steals 3rd/i', $_pp))  { $_pn[3] = $_pn[2]; $_pn[2] = null; }
                        elseif  (preg_match('/steals home/i', $_pp)) { $_pn[3] = null; }
                        elseif  (preg_match('/^(.+?)\s+to\s+(first|second|third)/i', $_pp, $_pm2)) {
                            $_mn2 = trim($_pm2[1]);
                            $_tb2 = $_toBase[strtolower($_pm2[2])] ?? null;
                            if ($_tb2) {
                                foreach ([1,2,3] as $_b2) { if ($_pn[$_b2] === $_mn2) { $_pn[$_b2] = null; break; } }
                                $_pn[$_tb2] = $_mn2;
                            }
                        }
                        elseif (preg_match('/^(.+?)\s+scores/i', $_pp, $_pm2)) {
                            $_mn2 = trim($_pm2[1]);
                            foreach ([1,2,3] as $_b2) { if ($_pn[$_b2] === $_mn2) { $_pn[$_b2] = null; break; } }
                        }
                    }
                    $_prevName = trim(($_prevAb->batter_first ?? '') . ' ' . ($_prevAb->batter_last ?? ''));
                    $_prevResult = (int)$_prevAb->result;
                    if      ($_prevResult === 9)                    { $_pn[1] = $_pn[2] = $_pn[3] = null; }
                    elseif  (in_array($_prevResult, [2, 10]))       { $_pn[1] = $_prevName; }
                    elseif  ($_prevResult === 6)                    { $_pn[1] = $_prevName; }
                    elseif  ($_prevResult === 7)                    { $_pn[2] = $_prevName; }
                    elseif  ($_prevResult === 8)                    { $_pn[3] = $_prevName; }
                    elseif  ($_prevFcReached)                       { $_pn[1] = $_prevName; }
                    $_n1 = $_pn[1]; $_n2 = $_pn[2]; $_n3 = $_pn[3];
                }
                $orphansByHalf[$_hKey][] = [
                    'name'    => $_abl['batter_name'] ?? '—',
                    'spot'    => $_spotByPlayer[$pid] ?? '',
                    'pitcher' => $_abl['pitcher'] ?? null,
                    'pitches' => $_abl['pitches'],
                    'outs'    => $_orphOuts,
                    'base1'   => $_r1, 'base2' => $_r2, 'base3' => $_r3,
                    'runner1' => $_n1, 'runner2' => $_n2, 'runner3' => $_n3,
                ];
            }
        }

        $flatAbs = [];
        foreach ($atBats as $h) {
            foreach ($h['atbats'] as $_ab) {
                $flatAbs[] = ['team_id' => (int)$h['team_id'], 'run_diff' => (int)$_ab->run_diff];
            }
        }
        $awayFinal  = array_sum($lineScore['away']);
        $homeFinal  = array_sum($lineScore['home']);
        $scoreAfterArr = [];
        $trackAway  = 0;
        $trackHome  = 0;
        for ($__i = 0; $__i < count($flatAbs); $__i++) {
            $__nextDiff = ($__i + 1 < count($flatAbs))
                ? $flatAbs[$__i + 1]['run_diff']
                : ($homeFinal - $awayFinal);
            $__delta = $__nextDiff - $flatAbs[$__i]['run_diff'];
            if ($__delta < 0) $trackAway += (-$__delta);
            if ($__delta > 0) $trackHome += $__delta;
            $scoreAfterArr[$__i] = [$trackAway, $trackHome];
        }
    @endphp

    @foreach($atBats as $half)
    @php
        $halfLabel = $half['half'] === 'top' ? 'Top' : 'Bot';
        $innStr    = $innLabel($half['inning']);
        $teamAbbr  = $half['team_id'] === $awayId ? $game->away_abbr : $game->home_abbr;
        $halfRuns  = $half['half'] === 'top'
            ? ($lineScore['away'][$half['inning']] ?? 0)
            : ($lineScore['home'][$half['inning']] ?? 0);
    @endphp

    <div class="mb-3">
        <div class="flex items-center gap-2 py-1.5 border-b border-gray-800 mb-0.5">
            <span class="text-xs font-bold tracking-widest text-gray-500 uppercase">{{ $halfLabel }} {{ $innStr }}</span>
            <span class="text-gray-700 text-xs">{{ $teamAbbr }}</span>
            @if($halfRuns > 0)
                <span class="bg-red-600/20 text-red-400 text-xs font-bold px-1.5 py-0.5 rounded">
                    {{ $halfRuns }} {{ $halfRuns === 1 ? 'Run' : 'Runs' }}
                </span>
            @endif
        </div>

        @foreach($half['atbats'] as $ab)
        @php
            $isHR        = (int)$ab->result === 9;
            $autoExpand  = $abIdx === 0 || $isHR;
            $resultLabel = ($ab->_fcReached ?? false) ? "Fielder's Choice" : ($results[(int)$ab->result] ?? 'At-Bat');
            $batterName  = trim($ab->batter_first . ' ' . $ab->batter_last) ?: '—';
            $pitcherName = trim(($ab->pitcher_first ?? '') . ' ' . ($ab->pitcher_last ?? '')) ?: '—';
            $b1 = ($ab->_runner1 ?? null) ? 1 : 0;
            $b2 = ($ab->_runner2 ?? null) ? 1 : 0;
            $b3 = ($ab->_runner3 ?? null) ? 1 : 0;
            [$__away, $__home] = $scoreAfterArr[$abIdx] ?? [0, 0];
            $scoreAfter = $game->away_abbr . ' ' . $__away . ' — ' . $__home . ' ' . $game->home_abbr;
            $__pid    = (int)$ab->player_id;
            $__half   = (int)$ab->team_id === $awayId ? 'top' : 'bottom';
            $__abKey  = $__pid . '_' . (int)$ab->inning . '_' . $__half;
            $abPitches = $pitchLogByKey[$__abKey] ?? [];
            $abIdx++;
        @endphp

        <div class="ab-item border-b border-gray-800/30 last:border-0">
            <button onclick="toggleAB(this)"
                class="w-full text-left flex items-center gap-2 px-2 py-2 hover:bg-gray-800/30 transition group">
                <span class="ab-arrow text-gray-700 text-xs w-2.5 shrink-0 group-hover:text-gray-500">{{ $autoExpand ? '▼' : '▶' }}</span>
                @if($ab->pinch)
                    <span class="text-amber-500 text-xs font-bold w-3.5 shrink-0">PH</span>
                @else
                    <span class="text-gray-600 text-xs w-3.5 shrink-0 text-right">{{ $ab->spot ?? '' }}</span>
                @endif
                <span class="flex-1 text-sm font-medium {{ $isHR ? 'text-yellow-300' : ($ab->pinch ? 'text-amber-200/70 italic' : 'text-gray-200') }}">
                    {{ $batterName }}
                    <span class="ml-1.5 font-semibold
                        {{ $isHR ? 'text-yellow-400' : (in_array((int)$ab->result,[6,7,8]) ? 'text-green-400' : (in_array((int)$ab->result,[2,10]) ? 'text-blue-400' : 'text-gray-500')) }}">
                        {{ $resultLabel }}
                        @if((int)$ab->rbi > 0)<span class="text-gray-600 font-normal ml-1">{{ $ab->rbi }} RBI</span>@endif
                    </span>
                </span>
                <span class="text-gray-300 text-sm font-semibold shrink-0 hidden sm:block" style="font-family:'Roboto',sans-serif">{{ $scoreAfter }}</span>
            </button>
            <div class="ab-detail {{ $autoExpand ? '' : 'hidden' }} bg-gray-950/60 px-8 py-2.5 text-sm text-gray-500">
                <div class="flex gap-8 mb-2.5 overflow-x-auto">
                    <div class="shrink-0"><span class="text-gray-700 text-xs uppercase tracking-wider">Count</span><br><span class="text-gray-400">{{ $ab->balls }}-{{ $ab->strikes }}</span></div>
                    <div class="shrink-0"><span class="text-gray-700 text-xs uppercase tracking-wider">Outs</span><br><span class="text-gray-400">{{ $ab->outs }}</span></div>
                    <div class="shrink-0"><span class="text-gray-700 text-xs uppercase tracking-wider">RBI / R</span><br><span class="text-gray-400">{{ $ab->rbi }} / {{ $ab->r }}</span></div>
                    <div class="shrink-0">
                        <span class="text-gray-700 text-xs uppercase tracking-wider block mb-1">Runners</span>
                        <div class="base-diamond">
                            <div class="diamond third-base" style="background:{{ $b3 ? '#3b82f6' : '#374151' }}"
                                @if($b3 && ($ab->_runner3 ?? null)) title="{{ $ab->_runner3 }}" @endif></div>
                            <div class="base-diamond-center">
                                <div class="diamond" style="background:{{ $b2 ? '#3b82f6' : '#374151' }}"
                                    @if($b2 && ($ab->_runner2 ?? null)) title="{{ $ab->_runner2 }}" @endif></div>
                                <div style="height:8px"></div>
                            </div>
                            <div class="diamond first-base" style="background:{{ $b1 ? '#3b82f6' : '#374151' }}"
                                @if($b1 && ($ab->_runner1 ?? null)) title="{{ $ab->_runner1 }}" @endif></div>
                        </div>
                    </div>
                    @if($ab->exit_velo)
                    <div class="shrink-0"><span class="text-gray-700 text-xs uppercase tracking-wider">Exit Velo</span><br><span class="text-gray-400">{{ number_format($ab->exit_velo,1) }} mph</span></div>
                    @endif
                    @if($ab->launch_angle)
                    <div class="shrink-0"><span class="text-gray-700 text-xs uppercase tracking-wider">Launch Angle</span><br><span class="text-gray-400">{{ $ab->launch_angle }}°</span></div>
                    @endif
                    <div class="shrink-0"><span class="text-gray-700 text-xs uppercase tracking-wider">Pitcher</span><br><span class="text-gray-400">{{ $pitcherName }}</span></div>
                </div>
                @if(!empty($abPitches))
                <div class="border-t border-gray-800/40 pt-2 space-y-0.5">
                    @foreach($abPitches as $pitch)
                    <p class="text-xs text-gray-400" style="font-family:'Roboto',sans-serif">{{ $pitch }}</p>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
        @endforeach

        @php $_orphanKey = $half['inning'] . '_' . $half['half']; @endphp
        @foreach($orphansByHalf[$_orphanKey] ?? [] as $_orph)
        @php
            $_orphLastPlay = !empty($_orph['pitches']) ? end($_orph['pitches']) : null;
            $_orphPlayLabel = $_orphLastPlay ? preg_replace('/^\d+-\d+:\s*/', '', $_orphLastPlay) : null;
            $_orphCount = '—';
            foreach (array_reverse($_orph['pitches']) as $_pp) {
                if (preg_match('/^(\d+)-(\d+):/', $_pp, $_cm)) { $_orphCount = $_cm[1] . '-' . $_cm[2]; break; }
            }
        @endphp
        <div class="ab-item border-b border-gray-800/30 last:border-0">
            <button onclick="toggleAB(this)"
                class="w-full text-left flex items-center gap-2 px-2 py-2 hover:bg-gray-800/30 transition group">
                <span class="ab-arrow text-gray-700 text-xs w-2.5 shrink-0 group-hover:text-gray-500">▶</span>
                <span class="text-gray-600 text-xs w-3.5 shrink-0 text-right">{{ $_orph['spot'] }}</span>
                <span class="flex-1 text-sm font-medium text-gray-200">
                    {{ $_orph['name'] }}
                    @if($_orphPlayLabel)
                        <span class="ml-1.5 font-semibold text-gray-500">{{ $_orphPlayLabel }}</span>
                    @endif
                </span>
            </button>
            <div class="ab-detail hidden bg-gray-950/60 px-8 py-2.5 text-sm text-gray-500">
                <div class="flex gap-8 mb-2.5 overflow-x-auto">
                    <div class="shrink-0"><span class="text-gray-700 text-xs uppercase tracking-wider">Count</span><br><span class="text-gray-400">{{ $_orphCount }}</span></div>
                    @if($_orph['outs'] !== null)
                    <div class="shrink-0"><span class="text-gray-700 text-xs uppercase tracking-wider">Outs</span><br><span class="text-gray-400">{{ $_orph['outs'] }}</span></div>
                    @endif
                    <div class="shrink-0">
                        <span class="text-gray-700 text-xs uppercase tracking-wider block mb-1">Runners</span>
                        <div class="base-diamond">
                            <div class="diamond third-base" style="background:{{ $_orph['base3'] ? '#3b82f6' : '#374151' }}"
                                @if($_orph['base3'] && $_orph['runner3']) title="{{ $_orph['runner3'] }}" @endif></div>
                            <div class="base-diamond-center">
                                <div class="diamond" style="background:{{ $_orph['base2'] ? '#3b82f6' : '#374151' }}"
                                    @if($_orph['base2'] && $_orph['runner2']) title="{{ $_orph['runner2'] }}" @endif></div>
                                <div style="height:8px"></div>
                            </div>
                            <div class="diamond first-base" style="background:{{ $_orph['base1'] ? '#3b82f6' : '#374151' }}"
                                @if($_orph['base1'] && $_orph['runner1']) title="{{ $_orph['runner1'] }}" @endif></div>
                        </div>
                    </div>
                    @if($_orph['pitcher'])
                    <div class="shrink-0"><span class="text-gray-700 text-xs uppercase tracking-wider">Pitcher</span><br><span class="text-gray-400">{{ $_orph['pitcher'] }}</span></div>
                    @endif
                </div>
                @if(!empty($_orph['pitches']))
                <div class="border-t border-gray-800/40 pt-2 space-y-0.5">
                    @foreach($_orph['pitches'] as $_p)
                    <p class="text-xs text-gray-400" style="font-family:'Roboto',sans-serif">{{ $_p }}</p>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endforeach

</div>
@endif

@push('scripts')
<script>
function toggleAB(btn) {
    var detail = btn.nextElementSibling;
    var arrow  = btn.querySelector('.ab-arrow');
    if (detail.classList.contains('hidden')) {
        detail.classList.remove('hidden');
        arrow.textContent = '▼';
    } else {
        detail.classList.add('hidden');
        arrow.textContent = '▶';
    }
}
</script>
@endpush

@endsection
