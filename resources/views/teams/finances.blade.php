@extends('layouts.public')
@section('title', ($team->name ?? 'Team') . ' — Finances')

@section('content')

{{-- Header --}}
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

    @if(!$fin)
    <div class="text-center py-24 text-gray-500">Financial data not available yet.</div>
    @else

    {{-- Financial Overview Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @php
        $finCards = [
            ['label' => 'Total Revenue',  'val' => $fin->total_revenue  ?? 0, 'color' => 'text-green-400'],
            ['label' => 'Total Expenses', 'val' => $fin->total_expenses ?? 0, 'color' => 'text-red-400'],
            ['label' => 'Balance',        'val' => $fin->financial_balance ?? 0,
                'color' => ($fin->financial_balance ?? 0) >= 0 ? 'text-green-400' : 'text-red-400'],
            ['label' => 'Player Payroll', 'val' => $fin->player_payroll ?? 0, 'color' => 'text-yellow-400'],
        ];
        @endphp
        @foreach($finCards as $card)
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">{{ $card['label'] }}</p>
            <p class="text-xl font-bold {{ $card['color'] }}">
                ${{ number_format($card['val']) }}
            </p>
        </div>
        @endforeach
    </div>

    {{-- Revenue Breakdown --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-800">
                <h2 class="font-semibold">Revenue Breakdown</h2>
            </div>
            <div class="p-5 space-y-3 text-sm">
                @foreach([
                    'Gate Revenue'        => $fin->gate_revenue ?? 0,
                    'Season Tickets'      => $fin->season_ticket_revenue ?? 0,
                    'Media Revenue'       => $fin->media_revenue ?? 0,
                    'Merchandising'       => $fin->merchandising_revenue ?? 0,
                    'Revenue Sharing'     => $fin->revenue_sharing ?? 0,
                    'Playoff Revenue'     => $fin->playoff_revenue ?? 0,
                ] as $label => $val)
                <div class="flex justify-between">
                    <span class="text-gray-400">{{ $label }}</span>
                    <span class="text-gray-200 font-mono">${{ number_format($val) }}</span>
                </div>
                @endforeach
                <div class="border-t border-gray-700 pt-2 flex justify-between font-semibold">
                    <span class="text-gray-300">Total Revenue</span>
                    <span class="text-green-400 font-mono">${{ number_format($fin->total_revenue ?? 0) }}</span>
                </div>
            </div>
        </div>

        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-800">
                <h2 class="font-semibold">Expense Breakdown</h2>
            </div>
            <div class="p-5 space-y-3 text-sm">
                @foreach([
                    'Player Expenses'  => $fin->player_expenses ?? 0,
                    'Staff Expenses'   => $fin->staff_expenses ?? 0,
                    'Stadium Expenses' => $fin->stadium_expenses ?? 0,
                    'Scouting Budget'  => $fin->scouting_budget ?? 0,
                    'Development'      => $fin->development_budget ?? 0,
                    'Draft Budget'     => $fin->draft_budget ?? 0,
                ] as $label => $val)
                <div class="flex justify-between">
                    <span class="text-gray-400">{{ $label }}</span>
                    <span class="text-gray-200 font-mono">${{ number_format($val) }}</span>
                </div>
                @endforeach
                <div class="border-t border-gray-700 pt-2 flex justify-between font-semibold">
                    <span class="text-gray-300">Total Expenses</span>
                    <span class="text-red-400 font-mono">${{ number_format($fin->total_expenses ?? 0) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Additional Info --}}
    @if(isset($fin->attendance) || isset($fin->fan_interest))
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @if(isset($fin->attendance))
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Attendance</p>
            <p class="text-xl font-bold text-white">{{ number_format($fin->attendance) }}</p>
        </div>
        @endif
        @if(isset($fin->fan_interest))
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Fan Interest</p>
            <p class="text-xl font-bold text-white">{{ $fin->fan_interest }}/100</p>
        </div>
        @endif
        @if(isset($fin->fan_loyalty))
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Fan Loyalty</p>
            <p class="text-xl font-bold text-white">{{ $fin->fan_loyalty }}/100</p>
        </div>
        @endif
        @if(isset($fin->budget))
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wider mb-1">Budget</p>
            <p class="text-xl font-bold text-white">${{ number_format($fin->budget) }}</p>
        </div>
        @endif
    </div>
    @endif

    @endif {{-- end if $fin --}}

    {{-- Major League Contracts --}}
    @if(!empty($contracts['major']))
    <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-800 flex items-center justify-between">
            <h2 class="font-semibold">Major League Contracts</h2>
            <span class="text-sm text-gray-400">
                Total Payroll: <strong class="text-yellow-400">${{ number_format($contracts['totalPayroll']) }}</strong>
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-800">
                        <th class="text-left px-5 py-2">Player</th>
                        <th class="text-center px-3 py-2">Pos</th>
                        <th class="text-center px-3 py-2">Age</th>
                        <th class="text-right px-4 py-2">Salary</th>
                        <th class="text-center px-3 py-2">Yrs</th>
                        <th class="text-center px-3 py-2">NTC</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/50">
                    @foreach($contracts['major'] as $c)
                    <tr class="hover:bg-gray-800/30 transition">
                        <td class="px-5 py-2.5">
                            <a href="{{ route('player', $c->player_id) }}"
                               class="text-white hover:text-red-400 transition font-medium">
                                {{ $c->first_name }} {{ $c->last_name }}
                            </a>
                        </td>
                        <td class="text-center px-3 py-2.5 text-gray-400">
                            {{ \App\Services\OotpService::POSITIONS[$c->position] ?? '?' }}
                        </td>
                        <td class="text-center px-3 py-2.5 text-gray-400">{{ $c->age }}</td>
                        <td class="text-right px-4 py-2.5 font-mono font-semibold text-yellow-400">
                            ${{ number_format($c->salary0) }}
                        </td>
                        <td class="text-center px-3 py-2.5 text-gray-400">{{ $c->years }}</td>
                        <td class="text-center px-3 py-2.5">
                            @if($c->no_trade)
                                <span class="text-xs bg-red-900/40 text-red-400 px-2 py-0.5 rounded-full">NTC</span>
                            @else
                                <span class="text-gray-700">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Minor League Contracts --}}
    @if(!empty($contracts['minor']))
    <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-800">
            <h2 class="font-semibold">Minor League Contracts</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 uppercase tracking-wider border-b border-gray-800">
                        <th class="text-left px-5 py-2">Player</th>
                        <th class="text-center px-3 py-2">Pos</th>
                        <th class="text-center px-3 py-2">Age</th>
                        <th class="text-right px-4 py-2">Salary</th>
                        <th class="text-center px-3 py-2">Yrs</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/50">
                    @foreach($contracts['minor'] as $c)
                    <tr class="hover:bg-gray-800/30 transition">
                        <td class="px-5 py-2.5">
                            <a href="{{ route('player', $c->player_id) }}"
                               class="text-white hover:text-red-400 transition font-medium">
                                {{ $c->first_name }} {{ $c->last_name }}
                            </a>
                        </td>
                        <td class="text-center px-3 py-2.5 text-gray-400">
                            {{ \App\Services\OotpService::POSITIONS[$c->position] ?? '?' }}
                        </td>
                        <td class="text-center px-3 py-2.5 text-gray-400">{{ $c->age }}</td>
                        <td class="text-right px-4 py-2.5 font-mono text-gray-300">
                            ${{ number_format($c->salary0) }}
                        </td>
                        <td class="text-center px-3 py-2.5 text-gray-400">{{ $c->years }}</td>
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
