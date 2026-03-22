@extends('layouts.public')
@section('title', 'Teams')

@section('content')

<div class="border-b border-gray-800 bg-gray-900/40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-3xl font-extrabold tracking-tight">Teams</h1>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">

    @if(empty($standingsByDivision) || collect($standingsByDivision)->isEmpty())
        <div class="text-center py-24 text-gray-500">No team data available yet.</div>
    @else

    @foreach($standingsByDivision as $subLeague)
    <div class="mb-12">
        <h2 class="text-xl font-bold text-red-500 uppercase tracking-wider mb-6">
            {{ $subLeague['name'] }}
        </h2>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            @foreach($subLeague['divisions'] as $division)
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <div class="bg-gray-800/60 px-5 py-3 border-b border-gray-700">
                    <h3 class="font-semibold text-gray-100 text-sm uppercase tracking-wide">
                        {{ $division['name'] }}
                    </h3>
                </div>
                <div class="divide-y divide-gray-800/50">
                    @foreach($division['teams'] as $i => $team)
                    <a href="{{ route('team', $team->team_id) }}"
                       class="flex items-center justify-between px-5 py-3.5 hover:bg-gray-800/40 transition group {{ $i === 0 ? 'border-l-2 border-l-red-500' : '' }}">
                        <div>
                            <span class="font-semibold text-white group-hover:text-red-400 transition">
                                {{ $team->name }}
                            </span>
                            <span class="text-gray-500 text-xs ml-2">{{ $team->abbr }}</span>
                        </div>
                        <div class="flex items-center gap-4 text-sm">
                            <span class="font-semibold tabular-nums text-white">{{ $team->w }}-{{ $team->l }}</span>
                            <span class="text-gray-500 tabular-nums font-mono text-xs">{{ $team->pct }}</span>
                            @if($team->gb !== '—')
                                <span class="text-gray-600 tabular-nums text-xs">{{ $team->gb }} GB</span>
                            @endif
                        </div>
                    </a>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach

    @endif
</div>
@endsection
