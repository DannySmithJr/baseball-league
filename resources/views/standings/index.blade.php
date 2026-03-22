@extends('layouts.public')
@section('title', 'Standings')

@section('content')

{{-- Page header --}}
<div class="border-b border-gray-800 bg-gray-900/40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-extrabold tracking-tight">Standings</h1>
                @if($ootp->gameDate())
                    <p class="text-gray-400 text-sm mt-1">
                        Through {{ \Carbon\Carbon::parse($ootp->gameDate())->format('F j, Y') }}
                        @if($ootp->seasonYear()) &mdash; {{ $ootp->seasonYear() }} Season @endif
                    </p>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

    @if(empty($standingsByDivision))
        <div class="text-center py-24 text-gray-500">
            <p class="text-lg">No standings data available yet.</p>
            <p class="text-sm mt-2">Import OOTP data from the admin panel to populate standings.</p>
        </div>
    @else

        @foreach($standingsByDivision as $subLeague)
        <div class="mb-12">

            {{-- Sub-league heading --}}
            <h2 class="text-xl font-bold text-red-500 uppercase tracking-wider mb-6">
                {{ $subLeague['name'] }}
            </h2>

            {{-- Divisions grid (1 col mobile, 2 col lg+) --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                @foreach($subLeague['divisions'] as $division)
                <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">

                    {{-- Division header --}}
                    <div class="bg-gray-800/60 px-5 py-3 border-b border-gray-700">
                        <h3 class="font-semibold text-gray-100 text-sm tracking-wide uppercase">
                            {{ $division['name'] }}
                        </h3>
                    </div>

                    {{-- Standings table --}}
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-gray-500 uppercase border-b border-gray-800">
                                <th class="text-left px-5 py-2 font-medium w-full">Team</th>
                                <th class="text-center px-3 py-2 font-medium whitespace-nowrap">W</th>
                                <th class="text-center px-3 py-2 font-medium whitespace-nowrap">L</th>
                                <th class="text-center px-3 py-2 font-medium whitespace-nowrap">PCT</th>
                                <th class="text-center px-3 py-2 font-medium whitespace-nowrap">GB</th>
                                <th class="text-center px-3 py-2 font-medium whitespace-nowrap">RD</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-800/60">
                            @foreach($division['teams'] as $i => $team)
                            @php
                                $rowBg     = '';
                                $leftBorder = '';
                                if ($team->playoff_div ?? false) {
                                    $rowBg      = 'bg-red-900/30';
                                    $leftBorder = 'border-l-2 border-l-red-500';
                                } elseif ($team->playoff_wild ?? false) {
                                    $rowBg      = 'bg-red-900/20';
                                    $leftBorder = 'border-l-2 border-l-red-700';
                                }
                            @endphp
                            <tr class="transition {{ $rowBg }} {{ $leftBorder }}">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-1.5">
                                        <a href="{{ route('team', $team->team_id) }}"
                                           class="font-medium text-white hover:text-red-400 transition">
                                            {{ $team->name }} {{ $team->nickname ?? '' }}
                                        </a>
                                        <span class="text-gray-600 text-xs">{{ $team->abbr }}</span>
                                        @if($team->playoff_wild ?? false)
                                            <span class="text-[9px] font-bold text-red-700 uppercase tracking-wider ml-1">Wildcard</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="text-center px-3 py-3 font-semibold tabular-nums">{{ $team->w }}</td>
                                <td class="text-center px-3 py-3 text-gray-400 tabular-nums">{{ $team->l }}</td>
                                <td class="text-center px-3 py-3 text-gray-300 tabular-nums">{{ $team->pct }}</td>
                                <td class="text-center px-3 py-3 text-gray-400 tabular-nums whitespace-nowrap">{{ $team->gb }}</td>
                                <td class="text-center px-3 py-3 tabular-nums
                                    {{ $team->rd > 0 ? 'text-green-400' : ($team->rd < 0 ? 'text-red-400' : 'text-gray-500') }}">
                                    {{ $team->rd > 0 ? '+' : '' }}{{ $team->rd }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endforeach
            </div>

        </div>
        @endforeach

        <div class="flex flex-wrap items-center gap-4 mt-4 text-xs text-gray-600">
            <span>RD = Run Differential &nbsp;&bull;&nbsp; GB = Games Behind division leader</span>
            @if($divWinners)
                <span class="flex items-center gap-1.5"><span class="inline-block w-2.5 h-2.5 rounded-sm bg-red-900/50 border-l-2 border-red-500"></span> Division Leader</span>
            @endif
            @if($wildcards > 0)
                <span class="flex items-center gap-1.5"><span class="inline-block w-2.5 h-2.5 rounded-sm bg-red-900/20 border-l-2 border-red-800"></span> Wild Card ({{ $wildcards }} per league)</span>
            @endif
        </div>

    @endif
</div>

@endsection
