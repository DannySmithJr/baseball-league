@extends('layouts.public')
@section('title', $player->first_name . ' ' . $player->last_name . ' — Contract')

@php
use App\Services\OotpService;
$posName = OotpService::POSITIONS[(int)$player->position] ?? '?';
@endphp

@section('content')

@include('players._header')

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">

    @if($contract)
    @php
        $totalYears  = (int)$contract->years;
        $currentYear = (int)$contract->current_year;
        $salaryYears = collect(range(0, 14))->filter(fn($i) => (int)($contract->{'salary'.$i} ?? 0) > 0);
        $yrsLeft     = $salaryYears->count();
        $totalValue  = $salaryYears->sum(fn($i) => (int)$contract->{'salary'.$i});
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Salary History --}}
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-800">
                <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wider">Salary History</h3>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                        <th class="text-left py-2.5 px-5">Year</th>
                        <th class="text-right py-2.5 px-5">Salary</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($salaryYears as $i)
                    @php $sal = (int)$contract->{'salary'.$i}; @endphp
                    <tr class="border-t border-gray-800/40 {{ $i === 0 ? 'bg-gray-800/20' : '' }}">
                        <td class="py-2.5 px-5 {{ $i === 0 ? 'text-white font-bold' : 'text-gray-400' }}">
                            {{ $contract->season_year + $i }}
                        </td>
                        <td class="py-2.5 px-5 text-right {{ $i === 0 ? 'text-green-400 font-bold' : 'text-gray-300' }}">
                            ${{ number_format($sal) }}
                        </td>
                    </tr>
                    @endforeach
                    <tr class="border-t-2 border-gray-700 bg-gray-800/30 font-bold">
                        <td class="py-2.5 px-5 text-white">Total</td>
                        <td class="py-2.5 px-5 text-right text-green-400">${{ number_format($totalValue) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Contract Summary --}}
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-800">
                <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wider">Contract Summary</h3>
            </div>
            <div class="p-5 space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">Current Salary</span>
                    <span class="text-green-400 font-bold text-base">${{ number_format((int)$contract->salary0) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Contract Length</span>
                    <span class="text-white font-semibold">{{ $totalYears }} {{ $totalYears === 1 ? 'year' : 'years' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Years Remaining</span>
                    <span class="text-white font-semibold">{{ $yrsLeft }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Total Value</span>
                    <span class="text-white font-semibold">${{ number_format($totalValue) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">AAV</span>
                    <span class="text-white font-semibold">${{ $yrsLeft > 0 ? number_format($totalValue / $yrsLeft) : '—' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Signed With</span>
                    <span class="text-white font-semibold">{{ DB::table('teams')->where('team_id', $contract->contract_team_id)->value('name') ?? '—' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Contract Type</span>
                    <span class="text-white font-semibold">{{ $contract->is_major ? 'Major League' : 'Minor League' }}</span>
                </div>

                {{-- Clauses --}}
                <div class="border-t border-gray-800 pt-2 mt-2">
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Clauses</p>
                    <div class="space-y-1">
                        <div class="flex justify-between">
                            <span class="text-gray-500">No-Trade Clause</span>
                            <span class="{{ $contract->no_trade ? 'text-yellow-400 font-bold' : 'text-gray-600' }}">{{ $contract->no_trade ? 'Yes' : 'No' }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Opt-Out</span>
                            <span class="{{ $contract->opt_out ? 'text-yellow-400 font-bold' : 'text-gray-600' }}">{{ $contract->opt_out ? 'Yes' : 'No' }}</span>
                        </div>
                        @if($contract->last_year_team_option)
                        <div class="flex justify-between">
                            <span class="text-gray-500">Team Option</span>
                            <span class="text-yellow-400 font-bold">Final Year (buyout ${{ number_format($contract->last_year_option_buyout) }})</span>
                        </div>
                        @endif
                        @if($contract->last_year_player_option)
                        <div class="flex justify-between">
                            <span class="text-gray-500">Player Option</span>
                            <span class="text-yellow-400 font-bold">Final Year</span>
                        </div>
                        @endif
                        @if($contract->last_year_vesting_option)
                        <div class="flex justify-between">
                            <span class="text-gray-500">Vesting Option</span>
                            <span class="text-yellow-400 font-bold">Final Year</span>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Bonuses --}}
                @php
                    $hasBonuses = ($contract->mvp_bonus ?? 0) || ($contract->cyyoung_bonus ?? 0)
                        || ($contract->allstar_bonus ?? 0) || ($contract->minimum_pa_bonus ?? 0)
                        || ($contract->minimum_ip_bonus ?? 0);
                @endphp
                @if($hasBonuses)
                <div class="border-t border-gray-800 pt-2 mt-2">
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Bonuses</p>
                    <div class="space-y-1">
                        @if($contract->mvp_bonus)
                        <div class="flex justify-between"><span class="text-gray-500">MVP Bonus</span><span class="text-green-400">${{ number_format($contract->mvp_bonus) }}</span></div>
                        @endif
                        @if($contract->cyyoung_bonus)
                        <div class="flex justify-between"><span class="text-gray-500">Cy Young Bonus</span><span class="text-green-400">${{ number_format($contract->cyyoung_bonus) }}</span></div>
                        @endif
                        @if($contract->allstar_bonus)
                        <div class="flex justify-between"><span class="text-gray-500">All-Star Bonus</span><span class="text-green-400">${{ number_format($contract->allstar_bonus) }}</span></div>
                        @endif
                        @if($contract->minimum_pa_bonus)
                        <div class="flex justify-between"><span class="text-gray-500">PA Bonus ({{ $contract->minimum_pa }}+ PA)</span><span class="text-green-400">${{ number_format($contract->minimum_pa_bonus) }}</span></div>
                        @endif
                        @if($contract->minimum_ip_bonus)
                        <div class="flex justify-between"><span class="text-gray-500">IP Bonus ({{ $contract->minimum_ip }}+ IP)</span><span class="text-green-400">${{ number_format($contract->minimum_ip_bonus) }}</span></div>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>

        {{-- Salary Rankings --}}
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-800">
                <h3 class="text-sm font-bold text-gray-400 uppercase tracking-wider">Salary Rankings</h3>
            </div>
            <div class="p-5 space-y-4">
                @if($salaryRanks)
                {{-- vs Position --}}
                <div>
                    <p class="text-[10px] text-gray-600 uppercase tracking-wider mb-1">vs {{ $posName }} Position</p>
                    <p class="text-lg font-black text-white">{{ $salaryRanks['position'] }}</p>
                </div>
                {{-- vs Team --}}
                <div>
                    <p class="text-[10px] text-gray-600 uppercase tracking-wider mb-1">vs {{ $player->team_abbr ?? '' }} Roster</p>
                    <p class="text-lg font-black text-white">{{ $salaryRanks['team'] }}</p>
                </div>
                {{-- vs League --}}
                <div>
                    <p class="text-[10px] text-gray-600 uppercase tracking-wider mb-1">vs All MLB Players</p>
                    <p class="text-lg font-black text-white">{{ $salaryRanks['overall'] }}</p>
                </div>
                @else
                <p class="text-gray-500 text-sm">No salary data available.</p>
                @endif
            </div>
        </div>

    </div>

    @else
    <div class="bg-gray-900 border border-gray-800 rounded-xl p-12 text-center text-gray-500 text-sm">
        No contract information available.
    </div>
    @endif

</div>
@endsection
