@extends('layouts.public')
@section('title', ($team->name ?? 'Team') . ' — History')

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

    @if($history->isEmpty())
    <div class="text-center py-24 text-gray-500">
        <p class="text-lg">No historical data available yet.</p>
    </div>
    @else

    {{-- Career summary cards --}}
    @php
        $totalW = $history->sum('w');
        $totalL = $history->sum('l');
        $totalG = $totalW + $totalL;
        $careerPct = $totalG > 0 ? $totalW / $totalG : 0;
        $pctFmt = $careerPct >= 1 ? '1.000' : '.'.str_pad((string)round($careerPct*1000), 3, '0', STR_PAD_LEFT);
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-4 text-center">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Seasons</p>
            <p class="text-2xl font-bold text-white">{{ $history->count() }}</p>
        </div>
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-4 text-center">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Career W</p>
            <p class="text-2xl font-bold text-green-400">{{ $totalW }}</p>
        </div>
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-4 text-center">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Career L</p>
            <p class="text-2xl font-bold text-red-400">{{ $totalL }}</p>
        </div>
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-4 text-center">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Career PCT</p>
            <p class="text-2xl font-bold text-yellow-400">{{ $pctFmt }}</p>
        </div>
    </div>

    {{-- Year-by-year table --}}
    <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-800">
            <h2 class="font-semibold">Season-by-Season Record</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-800">
                        <th class="text-left px-5 py-2">Year</th>
                        <th class="text-center px-4 py-2">W</th>
                        <th class="text-center px-4 py-2">L</th>
                        <th class="text-center px-4 py-2">G</th>
                        <th class="text-center px-4 py-2">PCT</th>
                        @if($history->first() && isset($history->first()->finish))
                            <th class="text-center px-4 py-2">Finish</th>
                        @endif
                        @if($history->first() && isset($history->first()->rs))
                            <th class="text-center px-4 py-2">RS</th>
                            <th class="text-center px-4 py-2">RA</th>
                            <th class="text-center px-4 py-2">RD</th>
                        @endif
                        @if($history->first() && isset($history->first()->playoff_result))
                            <th class="text-left px-4 py-2">Playoffs</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/50">
                    @foreach($history as $yr)
                    @php
                        $g   = (int)($yr->w ?? 0) + (int)($yr->l ?? 0);
                        $pct = $g > 0 ? ($yr->w / $g) : 0;
                        $pctFmt = $pct >= 1 ? '1.000' : '.'.str_pad((string)round($pct*1000), 3, '0', STR_PAD_LEFT);
                        $isWinning = $yr->w > $yr->l;
                    @endphp
                    <tr class="hover:bg-gray-800/30 transition">
                        <td class="px-5 py-3 font-semibold text-white">{{ $yr->year ?? '—' }}</td>
                        <td class="text-center px-4 py-3 font-semibold {{ $isWinning ? 'text-green-400' : 'text-gray-300' }}">{{ $yr->w ?? 0 }}</td>
                        <td class="text-center px-4 py-3 {{ !$isWinning ? 'text-red-400' : 'text-gray-400' }}">{{ $yr->l ?? 0 }}</td>
                        <td class="text-center px-4 py-3 text-gray-500">{{ $g ?: ($yr->g ?? '—') }}</td>
                        <td class="text-center px-4 py-3 font-mono text-gray-300">{{ $pctFmt }}</td>
                        @if(isset($yr->finish))
                            <td class="text-center px-4 py-3 text-gray-400">{{ $yr->finish }}</td>
                        @endif
                        @if(isset($yr->rs))
                        <td class="text-center px-4 py-3 text-gray-400">{{ $yr->rs ?? '—' }}</td>
                        <td class="text-center px-4 py-3 text-gray-400">{{ $yr->ra ?? '—' }}</td>
                        @php $rd = ($yr->rs ?? 0) - ($yr->ra ?? 0); @endphp
                        <td class="text-center px-4 py-3 {{ $rd > 0 ? 'text-green-400' : ($rd < 0 ? 'text-red-400' : 'text-gray-500') }}">
                            {{ $rd > 0 ? '+' : '' }}{{ $rd }}
                        </td>
                        @endif
                        @if(isset($yr->playoff_result))
                            <td class="px-4 py-3 text-gray-400 text-xs">{{ $yr->playoff_result ?: '—' }}</td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @endif
        </div>
    </div>
</div>
@endsection
