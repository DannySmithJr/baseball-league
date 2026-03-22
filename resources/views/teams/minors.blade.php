@extends('layouts.public')
@section('title', ($team->name ?? 'Team') . ' — Farm System')

@section('content')

<div class="border-b border-gray-800 bg-gray-900/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-3xl font-extrabold tracking-tight">{{ $team->name }} {{ $team->nickname ?? '' }}</h1>
            @if($record)
                <span class="text-lg font-semibold text-gray-400">{{ $record->w }}-{{ $record->l }}</span>
            @endif
        </div>
    </div>
</div>

@include('teams._nav')

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-[220px_1fr] gap-6">
        <div class="space-y-5">
            @include('teams._sidebar')
        </div>
        <div class="space-y-6">

    @if($affiliates->isEmpty())
    <div class="text-center py-24 text-gray-500">
        <p class="text-lg">No minor league affiliates found.</p>
    </div>
    @else

    {{-- Affiliate overview cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-10">
        @foreach($affiliates as $aff)
        @php $recClass = !empty($aff->leads_division) ? 'text-yellow-400' : ($aff->w > $aff->l ? 'text-green-400' : ($aff->w < $aff->l ? 'text-red-400' : 'text-gray-400')); @endphp
        <a href="{{ route('team', $aff->team_id) }}"
           class="bg-gray-900 border border-gray-800 hover:border-red-500/50 rounded-xl p-5 flex items-center justify-between transition group">
            <div>
                <div class="text-xs text-red-500 font-bold uppercase tracking-wider mb-1">{{ $aff->level_label }}</div>
                <div class="font-semibold text-white group-hover:text-red-400 transition">
                    {{ $aff->name }} {{ $aff->nickname ?? '' }}
                </div>
                <div class="text-xs text-gray-500 mt-0.5">{{ $aff->league_name ?? '' }}</div>
            </div>
            <div class="text-right">
                <div class="text-xl font-bold {{ $recClass }}">{{ $aff->w }}-{{ $aff->l }}</div>
                <div class="text-xs text-gray-600 mt-0.5">{{ $aff->abbr }}</div>
            </div>
        </a>
        @endforeach
    </div>

    {{-- Per-affiliate roster tables --}}
    @foreach($affiliates as $aff)
    @php
        $affData   = $affiliateRosters[$aff->team_id] ?? null;
        $affRoster = $affData ? $affData['roster'] : collect();
        $bStats    = $affData ? $affData['batter_stats'] : [];
        $pStats    = $affData ? $affData['pitcher_stats'] : [];
        $batters   = $affRoster->whereNotIn('position', [1, 2]);
        $pitchers  = $affRoster->whereIn('position', [1, 2]);
    @endphp
    <div class="mb-10">
        <div class="flex items-center gap-3 mb-4">
            <h2 class="text-xl font-bold">
                <a href="{{ route('team', $aff->team_id) }}" class="hover:text-red-400 transition">
                    {{ $aff->name }} {{ $aff->nickname ?? '' }}
                </a>
            </h2>
            <span class="text-sm text-red-500 font-semibold">{{ $aff->level_label }}</span>
            <span class="text-gray-400 text-sm">{{ $aff->w }}-{{ $aff->l }}</span>
        </div>

        @if($affRoster->isEmpty())
        <p class="text-gray-600 text-sm pl-1">No roster data available.</p>
        @else

        {{-- Position Players --}}
        @if($batters->isNotEmpty())
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden mb-4">
            <div class="px-4 py-2.5 border-b border-gray-800">
                <h3 class="text-sm font-semibold text-gray-300">Position Players</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="text-left px-4 py-2">Player</th>
                            <th class="text-center px-2 py-2">B</th>
                            <th class="text-center px-2 py-2">Age</th>
                            <th class="text-center px-2 py-2">G</th>
                            <th class="text-center px-2 py-2">AB</th>
                            <th class="text-center px-2 py-2">H</th>
                            <th class="text-center px-2 py-2">HR</th>
                            <th class="text-center px-2 py-2">RBI</th>
                            <th class="text-center px-2 py-2">BB</th>
                            <th class="text-center px-2 py-2">K</th>
                            <th class="text-center px-3 py-2">AVG</th>
                            <th class="text-center px-3 py-2">OPS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40">
                        @foreach($batters as $p)
                        @php
                            $s   = $bStats[$p->player_id] ?? null;
                            $pos = \App\Services\OotpService::POSITIONS[$p->position] ?? '?';
                            $bat = match((int)($p->bats ?? 0)) { 1=>'R', 2=>'L', 3=>'S', default=>'-' };
                        @endphp
                        <tr class="hover:bg-gray-800/30 transition">
                            <td class="px-4 py-2 whitespace-nowrap">
                                <a href="{{ route('player', $p->player_id) }}"
                                   class="text-white hover:text-red-400 transition font-medium">
                                    {{ $p->first_name[0] }}. {{ $p->last_name }}
                                </a>
                                <span class="text-gray-600 ml-1">{{ $pos }}</span>
                            </td>
                            <td class="text-center px-2 py-2 text-gray-500">{{ $bat }}</td>
                            <td class="text-center px-2 py-2 text-gray-500">{{ $p->age }}</td>
                            @if($s)
                            <td class="text-center px-2 py-2 text-gray-400">{{ $s->g }}</td>
                            <td class="text-center px-2 py-2 text-gray-400">{{ $s->ab }}</td>
                            <td class="text-center px-2 py-2 text-white">{{ $s->h }}</td>
                            <td class="text-center px-2 py-2 text-gray-400">{{ $s->hr }}</td>
                            <td class="text-center px-2 py-2 text-gray-400">{{ $s->rbi }}</td>
                            <td class="text-center px-2 py-2 text-gray-400">{{ $s->bb }}</td>
                            <td class="text-center px-2 py-2 text-gray-400">{{ $s->k }}</td>
                            @php
                                $avgFmt = $s->avg >= 1 ? '1.000' : '.'.str_pad((string)round($s->avg*1000), 3, '0', STR_PAD_LEFT);
                                $opsFmt = $s->ops >= 1 ? '1.000' : '.'.str_pad((string)round($s->ops*1000), 3, '0', STR_PAD_LEFT);
                            @endphp
                            <td class="text-center px-3 py-2 font-mono text-gray-300">{{ $avgFmt }}</td>
                            <td class="text-center px-3 py-2 font-mono text-gray-300">{{ $opsFmt }}</td>
                            @else
                            <td colspan="9" class="text-center py-2 text-gray-700">—</td>
                            @endif
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Pitchers --}}
        @if($pitchers->isNotEmpty())
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
            <div class="px-4 py-2.5 border-b border-gray-800">
                <h3 class="text-sm font-semibold text-gray-300">Pitchers</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="text-left px-4 py-2">Player</th>
                            <th class="text-center px-2 py-2">T</th>
                            <th class="text-center px-2 py-2">Age</th>
                            <th class="text-center px-2 py-2">G</th>
                            <th class="text-center px-2 py-2">GS</th>
                            <th class="text-center px-2 py-2">W</th>
                            <th class="text-center px-2 py-2">L</th>
                            <th class="text-center px-2 py-2">SV</th>
                            <th class="text-center px-3 py-2">IP</th>
                            <th class="text-center px-2 py-2">K</th>
                            <th class="text-center px-2 py-2">BB</th>
                            <th class="text-center px-3 py-2">ERA</th>
                            <th class="text-center px-3 py-2">WHIP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40">
                        @foreach($pitchers as $p)
                        @php
                            $s   = $pStats[$p->player_id] ?? null;
                            $thr = match((int)($p->throws ?? 0)) { 1=>'R', 2=>'L', default=>'-' };
                            $eraNum = $s ? (float)$s->era : 0;
                        @endphp
                        <tr class="hover:bg-gray-800/30 transition">
                            <td class="px-4 py-2 whitespace-nowrap">
                                <a href="{{ route('player', $p->player_id) }}"
                                   class="text-white hover:text-red-400 transition font-medium">
                                    {{ $p->first_name[0] }}. {{ $p->last_name }}
                                </a>
                            </td>
                            <td class="text-center px-2 py-2 text-gray-500">{{ $thr }}</td>
                            <td class="text-center px-2 py-2 text-gray-500">{{ $p->age }}</td>
                            @if($s)
                            <td class="text-center px-2 py-2 text-gray-400">{{ $s->g }}</td>
                            <td class="text-center px-2 py-2 text-gray-400">{{ $s->gs }}</td>
                            <td class="text-center px-2 py-2 text-white font-medium">{{ $s->w }}</td>
                            <td class="text-center px-2 py-2 text-gray-400">{{ $s->l }}</td>
                            <td class="text-center px-2 py-2 text-gray-400">{{ $s->sv }}</td>
                            <td class="text-center px-3 py-2 text-gray-300">{{ $s->ip_display }}</td>
                            <td class="text-center px-2 py-2 text-gray-400">{{ $s->k }}</td>
                            <td class="text-center px-2 py-2 text-gray-400">{{ $s->bb }}</td>
                            <td class="text-center px-3 py-2 font-mono text-gray-300">{{ $s->era }}</td>
                            <td class="text-center px-3 py-2 font-mono text-gray-300">{{ $s->whip }}</td>
                            @else
                            <td colspan="10" class="text-center py-2 text-gray-700">—</td>
                            @endif
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @endif {{-- end if roster not empty --}}
    </div>
    @endforeach

    @endif
        </div>
    </div>
</div>
@endsection
