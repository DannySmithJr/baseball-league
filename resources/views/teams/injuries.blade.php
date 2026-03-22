@extends('layouts.public')
@section('title', ($team->name ?? 'Team') . ' — Injuries')

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

    {{-- Current Injuries / IL --}}
    <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-800 flex items-center gap-3">
            <h2 class="font-semibold">Injured List</h2>
            @if($current->isNotEmpty())
                <span class="text-xs bg-red-900/40 text-red-400 px-2 py-0.5 rounded-full font-medium">
                    {{ $current->count() }} player{{ $current->count() !== 1 ? 's' : '' }}
                </span>
            @endif
        </div>

        @if($current->isEmpty())
        <div class="px-5 py-10 text-center text-gray-500 text-sm">
            No players currently on the injured list.
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-800">
                        <th class="text-left px-5 py-2">Player</th>
                        <th class="text-center px-3 py-2">Pos</th>
                        <th class="text-center px-3 py-2">Age</th>
                        @if(isset($current->first()->injury_length))
                            <th class="text-center px-3 py-2">Est. Return</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/50">
                    @foreach($current as $p)
                    <tr class="hover:bg-gray-800/30 transition">
                        <td class="px-5 py-3">
                            <a href="{{ route('player', $p->player_id) }}"
                               class="text-white hover:text-red-400 transition font-medium">
                                {{ $p->first_name }} {{ $p->last_name }}
                            </a>
                        </td>
                        <td class="text-center px-3 py-3 text-gray-400">
                            {{ \App\Services\OotpService::POSITIONS[$p->position] ?? '?' }}
                        </td>
                        <td class="text-center px-3 py-3 text-gray-400">{{ $p->age ?? '—' }}</td>
                        @if(isset($p->injury_length))
                            <td class="text-center px-3 py-3 text-gray-400">
                                {{ $p->injury_length > 0 ? $p->injury_length . ' days' : '—' }}
                            </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- Injury History --}}
    @if($history->isNotEmpty())
    <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-800">
            <h2 class="font-semibold">Injury History</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-800">
                        <th class="text-left px-5 py-2">Player</th>
                        <th class="text-center px-3 py-2">Pos</th>
                        <th class="text-center px-3 py-2">Date</th>
                        <th class="text-center px-3 py-2">Length</th>
                        <th class="text-center px-3 py-2">Type</th>
                        <th class="text-center px-3 py-2">Setbacks</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/50">
                    @foreach($history as $inj)
                    @php
                        $bodyParts = [
                            1=>'Head', 2=>'Neck', 3=>'Shoulder', 4=>'Elbow', 5=>'Forearm',
                            6=>'Wrist', 7=>'Hand', 8=>'Finger', 9=>'Back', 10=>'Abdomen',
                            11=>'Groin', 12=>'Hip', 13=>'Thigh', 14=>'Knee', 15=>'Calf',
                            16=>'Ankle', 17=>'Foot', 18=>'Toe', 0=>'General',
                        ];
                        $bodyPart = $bodyParts[$inj->body_part ?? 0] ?? 'Unknown';
                    @endphp
                    <tr class="hover:bg-gray-800/30 transition">
                        <td class="px-5 py-2.5">
                            <a href="{{ route('player', $inj->player_id) }}"
                               class="text-white hover:text-red-400 transition font-medium">
                                {{ $inj->first_name }} {{ $inj->last_name }}
                            </a>
                        </td>
                        <td class="text-center px-3 py-2.5 text-gray-400">
                            {{ \App\Services\OotpService::POSITIONS[$inj->position] ?? '?' }}
                        </td>
                        <td class="text-center px-3 py-2.5 text-gray-400">
                            {{ $inj->date ? \Carbon\Carbon::parse($inj->date)->format('M j, Y') : '—' }}
                        </td>
                        <td class="text-center px-3 py-2.5 text-gray-400">
                            {{ $inj->day_to_day ? 'Day-to-Day' : (($inj->length ?? 0).' days') }}
                        </td>
                        <td class="text-center px-3 py-2.5 text-gray-400">{{ $bodyPart }}</td>
                        <td class="text-center px-3 py-2.5 {{ ($inj->setbacks ?? 0) > 0 ? 'text-red-400' : 'text-gray-600' }}">
                            {{ $inj->setbacks ?? 0 }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="text-center py-12 text-gray-500 text-sm">No injury history on file.</div>
    @endif

        </div>
    </div>
</div>
@endsection
