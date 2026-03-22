@extends('layouts.public')

@php
    use Carbon\Carbon;
    $fmtDate  = fn(string $d) => Carbon::parse($d)->format('M j');
    $fmtIni   = fn($f, $l)    => ($f ? mb_substr($f,0,1).'. ' : '') . $l;
    $fmtIp    = function(int $outs): string {
        $inn = intdiv($outs, 3);
        $rem = $outs % 3;
        return $inn . ($rem ? '.' . $rem : '.0');
    };
    $fmtAvg   = fn($n) => $n >= 1 ? number_format($n, 3) : ltrim(number_format($n, 3), '0');
@endphp

@section('title', 'Sim Preview · ' . \Carbon\Carbon::parse($firstUpcoming)->format('M j') . '–' . \Carbon\Carbon::parse($lastUpcoming)->format('M j, Y'))

@section('content')

{{-- ── PAGE HEADER ── --}}
<div class="bg-gray-900 border-b border-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <p class="text-xs font-bold tracking-widest text-red-500 uppercase mb-1">Sim Preview</p>
        <h1 class="text-2xl font-black text-white">
            {{ \Carbon\Carbon::parse($firstUpcoming)->format('F j') }} – {{ \Carbon\Carbon::parse($lastUpcoming)->format('F j, Y') }}
        </h1>
        <p class="text-xs text-gray-600 mt-1">Based on results through {{ \Carbon\Carbon::parse($lastPlayed)->format('F j, Y') }}</p>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-12">

{{-- ── FEATURED MATCHUPS ── --}}
<section>
    <h2 class="text-xs font-bold tracking-widest text-red-500 uppercase mb-1">Featured Matchups</h2>
    <p class="text-xs text-gray-600 mb-5">Top 12 series by competitive interest — closeness, quality, star power & momentum</p>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach(array_values($featuredMatchups) as $fi => $fm)
        @php
            $fAway    = $teamRecords[$fm['away']] ?? null;
            $fHome    = $teamRecords[$fm['home']] ?? null;
            $fDateStr = $fmtDate($fm['date']);
            $fAwayMom = $fm['awayMom'];
            $fHomeMom = $fm['homeMom'];
        @endphp
        @if($fAway && $fHome)
        <x-game-matchup
            :date="$fDateStr"
            :away="[
                'abbr'     => $fAway['abbr'],
                'name'     => $fAway['nickname'],
                'logo'     => $fAway['logo'],
                'bgColor'  => $fAway['bgColor'],
                'record'   => $fAway['w'].'-'.$fAway['l'],
                'rpg'      => $fAway['rpg'],
                'avg'      => $fAway['teamAvg'],
                'hr'       => $fAway['teamHr'],
                'momentum' => $fAwayMom,
            ]"
            :home="[
                'abbr'     => $fHome['abbr'],
                'name'     => $fHome['nickname'],
                'logo'     => $fHome['logo'],
                'bgColor'  => $fHome['bgColor'],
                'record'   => $fHome['w'].'-'.$fHome['l'],
                'rpg'      => $fHome['rpg'],
                'avg'      => $fHome['teamAvg'],
                'hr'       => $fHome['teamHr'],
                'momentum' => $fHomeMom,
            ]"
            :awayStarter="$fm['awaySpName'] ? array_merge(['name' => $fm['awaySpName']], $fm['awaySpStats'] ?? ['w'=>0,'l'=>0,'era'=>'-.--','k'=>0]) : null"
            :homeStarter="$fm['homeSpName'] ? array_merge(['name' => $fm['homeSpName']], $fm['homeSpStats'] ?? ['w'=>0,'l'=>0,'era'=>'-.--','k'=>0]) : null"
        />
        @endif
        @endforeach
    </div>
</section>


{{-- ── TWO COLUMN: STREAKS ── --}}
<section>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

        {{-- Team Streaks --}}
        <div>
            <h2 class="text-xs font-bold tracking-widest text-red-500 uppercase mb-4">Team Streaks</h2>
            @if(empty($teamStreaks))
                <p class="text-sm text-gray-600">No streaks of 3+ games.</p>
            @else
            <div class="space-y-2">
                @foreach($teamStreaks as $ts)
                <div class="flex items-center gap-3 bg-gray-900 border border-gray-800 rounded-lg px-4 py-3">
                    @if($ts['logo'])
                        <img src="/images/logos/{{ $ts['logo'] }}" class="w-8 h-8 object-contain shrink-0">
                    @else
                        <div class="w-8 h-8 rounded-full shrink-0" style="background:{{ $ts['bgColor'] }}"></div>
                    @endif
                    <div class="flex-1 min-w-0">
                        <span class="text-sm font-bold text-white">{{ $ts['name'] }}</span>
                        <span class="text-xs text-gray-500 ml-2">{{ $ts['w'] }}-{{ $ts['l'] }}</span>
                    </div>
                    <span class="text-sm font-black shrink-0 {{ $ts['type'] === 'W' ? 'text-green-400' : 'text-red-400' }}">
                        {{ $ts['type'] }}{{ $ts['streak'] }}
                    </span>
                </div>
                @endforeach
            </div>
            @endif
        </div>

        {{-- Hitting Streaks --}}
        <div>
            <h2 class="text-xs font-bold tracking-widest text-red-500 uppercase mb-4">Hitting Streaks</h2>
            <div class="overflow-x-auto">
                <table class="text-xs w-full">
                    <thead>
                        <tr class="text-gray-600 border-b border-gray-800 uppercase tracking-wider">
                            <th class="text-left py-1.5 font-medium">Player</th>
                            <th class="text-center px-2 py-1.5 font-medium">Team</th>
                            <th class="text-center px-2 py-1.5 font-medium">G</th>
                            <th class="text-left px-2 py-1.5 font-medium">Since</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40">
                        @foreach($hittingStreaks as $hs)
                        <tr class="hover:bg-gray-800/20">
                            <td class="py-1.5 pr-2">
                                <a href="{{ route('player', $hs->player_id) }}" class="text-gray-200 hover:text-red-400 transition font-medium">
                                    {{ $fmtIni($hs->first_name, $hs->last_name) }}
                                </a>
                            </td>
                            <td class="text-center px-2 py-1.5 text-gray-500">{{ $hs->abbr }}</td>
                            <td class="text-center px-2 py-1.5 font-black text-white">{{ $hs->value }}</td>
                            <td class="px-2 py-1.5 text-gray-600">{{ $fmtDate($hs->started) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</section>

{{-- ── BATTER WATCH ── --}}
<section>
    <h2 class="text-xs font-bold tracking-widest text-red-500 uppercase mb-1">Batter Watch</h2>
    <p class="text-xs text-gray-600 mb-5">Last 7 days — {{ $fmtDate($periodStart) }}–{{ $fmtDate($lastPlayed) }} · min. 15 AB</p>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

        {{-- Hot --}}
        <div>
            <p class="text-xs font-bold text-green-500 uppercase tracking-wider mb-3">Hot</p>
            <div class="overflow-x-auto">
                <table class="text-xs w-full">
                    <thead>
                        <tr class="text-gray-600 border-b border-gray-800 uppercase tracking-wider">
                            <th class="text-left py-1.5 font-medium">Player</th>
                            <th class="text-center px-1 py-1.5 font-medium">G</th>
                            <th class="text-center px-1 py-1.5 font-medium">AB</th>
                            <th class="text-center px-1 py-1.5 font-medium">H</th>
                            <th class="text-center px-1 py-1.5 font-medium">HR</th>
                            <th class="text-center px-1 py-1.5 font-medium">RBI</th>
                            <th class="text-center px-1 py-1.5 font-medium">AVG</th>
                            <th class="text-center px-1 py-1.5 font-medium">OPS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40">
                        @foreach($hotBatters as $b)
                        @php $ops = $b->obp + $b->slg; @endphp
                        <tr class="hover:bg-gray-800/20">
                            <td class="py-1.5 pr-2">
                                <a href="{{ route('player', $b->player_id) }}" class="text-gray-200 hover:text-red-400 transition font-medium">{{ $fmtIni($b->first_name, $b->last_name) }}</a>
                                <span class="text-gray-600 ml-1">{{ $b->abbr }}</span>
                            </td>
                            <td class="text-center px-1 py-1.5 text-gray-400">{{ $b->gp }}</td>
                            <td class="text-center px-1 py-1.5 text-gray-400">{{ $b->ab }}</td>
                            <td class="text-center px-1 py-1.5 text-gray-300 font-semibold">{{ $b->h }}</td>
                            <td class="text-center px-1 py-1.5 {{ $b->hr > 0 ? 'text-yellow-400 font-bold' : 'text-gray-600' }}">{{ $b->hr ?: '·' }}</td>
                            <td class="text-center px-1 py-1.5 text-gray-400">{{ $b->rbi }}</td>
                            <td class="text-center px-1 py-1.5 text-green-400 font-bold">{{ $fmtAvg($b->avg) }}</td>
                            <td class="text-center px-1 py-1.5 text-gray-400">{{ $fmtAvg($ops) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Cold --}}
        <div>
            <p class="text-xs font-bold text-blue-400 uppercase tracking-wider mb-3">Cold · min. 18 AB</p>
            <div class="overflow-x-auto">
                <table class="text-xs w-full">
                    <thead>
                        <tr class="text-gray-600 border-b border-gray-800 uppercase tracking-wider">
                            <th class="text-left py-1.5 font-medium">Player</th>
                            <th class="text-center px-1 py-1.5 font-medium">G</th>
                            <th class="text-center px-1 py-1.5 font-medium">AB</th>
                            <th class="text-center px-1 py-1.5 font-medium">H</th>
                            <th class="text-center px-1 py-1.5 font-medium">HR</th>
                            <th class="text-center px-1 py-1.5 font-medium">RBI</th>
                            <th class="text-center px-1 py-1.5 font-medium">AVG</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40">
                        @foreach($coldBatters as $b)
                        <tr class="hover:bg-gray-800/20">
                            <td class="py-1.5 pr-2">
                                <a href="{{ route('player', $b->player_id) }}" class="text-gray-200 hover:text-red-400 transition font-medium">{{ $fmtIni($b->first_name, $b->last_name) }}</a>
                                <span class="text-gray-600 ml-1">{{ $b->abbr }}</span>
                            </td>
                            <td class="text-center px-1 py-1.5 text-gray-400">{{ $b->gp }}</td>
                            <td class="text-center px-1 py-1.5 text-gray-400">{{ $b->ab }}</td>
                            <td class="text-center px-1 py-1.5 text-gray-400">{{ $b->h }}</td>
                            <td class="text-center px-1 py-1.5 {{ $b->hr > 0 ? 'text-yellow-400 font-bold' : 'text-gray-600' }}">{{ $b->hr ?: '·' }}</td>
                            <td class="text-center px-1 py-1.5 text-gray-400">{{ $b->rbi }}</td>
                            <td class="text-center px-1 py-1.5 text-blue-400 font-bold">{{ $fmtAvg($b->avg) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</section>

{{-- ── PITCHER WATCH ── --}}
<section>
    <h2 class="text-xs font-bold tracking-widest text-red-500 uppercase mb-1">Pitcher Watch</h2>
    <p class="text-xs text-gray-600 mb-5">Starters — last 7 days · min. 9 outs</p>
    <div class="overflow-x-auto">
        <table class="text-xs w-full max-w-3xl">
            <thead>
                <tr class="text-gray-600 border-b border-gray-800 uppercase tracking-wider">
                    <th class="text-left py-1.5 font-medium">Pitcher</th>
                    <th class="text-center px-1.5 py-1.5 font-medium">GS</th>
                    <th class="text-center px-1.5 py-1.5 font-medium">IP</th>
                    <th class="text-center px-1.5 py-1.5 font-medium">H</th>
                    <th class="text-center px-1.5 py-1.5 font-medium">R</th>
                    <th class="text-center px-1.5 py-1.5 font-medium">ER</th>
                    <th class="text-center px-1.5 py-1.5 font-medium">BB</th>
                    <th class="text-center px-1.5 py-1.5 font-medium">K</th>
                    <th class="text-center px-1.5 py-1.5 font-medium">HR</th>
                    <th class="text-center px-1.5 py-1.5 font-medium">ERA</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800/40">
                @foreach($pitcherWatch as $p)
                <tr class="hover:bg-gray-800/20">
                    <td class="py-1.5 pr-2">
                        <a href="{{ route('player', $p->player_id) }}" class="text-gray-200 hover:text-red-400 transition font-medium">{{ $fmtIni($p->first_name, $p->last_name) }}</a>
                        <span class="text-gray-600 ml-1">{{ $p->abbr }}</span>
                    </td>
                    <td class="text-center px-1.5 py-1.5 text-gray-400">{{ $p->gs }}</td>
                    <td class="text-center px-1.5 py-1.5 text-gray-300 font-semibold">{{ $fmtIp($p->total_outs) }}</td>
                    <td class="text-center px-1.5 py-1.5 text-gray-400">{{ $p->h }}</td>
                    <td class="text-center px-1.5 py-1.5 text-gray-400">{{ $p->r }}</td>
                    <td class="text-center px-1.5 py-1.5 text-gray-400">{{ $p->er }}</td>
                    <td class="text-center px-1.5 py-1.5 text-gray-400">{{ $p->bb }}</td>
                    <td class="text-center px-1.5 py-1.5 {{ $p->k >= 7 ? 'text-yellow-400 font-semibold' : 'text-gray-400' }}">{{ $p->k }}</td>
                    <td class="text-center px-1.5 py-1.5 {{ $p->hr > 0 ? 'text-red-400' : 'text-gray-600' }}">{{ $p->hr ?: '·' }}</td>
                    <td class="text-center px-1.5 py-1.5 font-bold {{ $p->era <= 2.00 ? 'text-green-400' : ($p->era >= 5.00 ? 'text-red-400' : 'text-gray-300') }}">
                        {{ number_format($p->era, 2) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

</div>
@endsection
