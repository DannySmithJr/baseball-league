@extends('layouts.public')
@section('title', $player->first_name . ' ' . $player->last_name . ' — Bio')

@section('content')
@php $activeTab = 'player.bio'; @endphp
@include('players._header')

@php
    use App\Services\OotpService;

    $fmtAvg = function($v) {
        $v = (float) $v;
        if ($v <= 0) return '.000';
        if ($v >= 1) return '1.000';
        return '.' . str_pad((string) round($v * 1000), 3, '0', STR_PAD_LEFT);
    };

    $awardNames = OotpService::AWARD_NAMES;

    // Group career awards by year for display
    $awardsByYear = [];
    foreach ($awards as $aw) {
        $awardsByYear[(int)$aw->year][] = $aw;
    }
    krsort($awardsByYear);

    $posName = OotpService::POSITIONS[(int)$player->position] ?? '?';

    $batLabel = match((int)$player->bats ?? 0) {
        1 => 'R', 2 => 'L', 3 => 'S', default => '?'
    };
    $throwLabel = match((int)$player->throws ?? 0) {
        1 => 'R', 2 => 'L', default => '?'
    };

    $feet = (int)($player->height ?? 0) > 0 ? intdiv((int)$player->height, 12) : null;
    $inches = (int)($player->height ?? 0) > 0 ? (int)$player->height % 12 : null;
    $dob = $player->date_of_birth ? \Carbon\Carbon::parse($player->date_of_birth) : null;
    $birthplace = implode(', ', array_filter([$player->birth_city ?? null, $player->birth_state ?? null, $player->birth_country ?? null]));

    $draftInfo = null;
    if ($player->draft_year ?? null) {
        $round = $player->draft_round ?? '?';
        $pick  = $player->draft_pick ?? '?';
        $team  = $player->draft_team_name ?? null;
        $draftInfo = "{$player->draft_year}, Round {$round}, Pick {$pick}";
        if ($team) $draftInfo .= " ({$team})";
    }
@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-10">

    {{-- ── Awards & Honors ──────────────────────────────────────────────────── --}}
    @if($awards->isNotEmpty())
    <section>
        <h2 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-4">Awards &amp; Honors</h2>
        <div class="space-y-4">
            @foreach($awardsByYear as $yr => $yearAwards)
            <div class="flex gap-4 items-start">
                <div class="shrink-0 w-12 text-right">
                    <span class="text-sm font-bold text-gray-400">{{ $yr }}</span>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach($yearAwards as $aw)
                    @php
                        $awName  = $awardNames[(int)$aw->award_id] ?? ('Award #' . $aw->award_id);
                        $isWin   = (int)$aw->finish === 1;
                        $finish  = (int)$aw->finish;

                        $colorClass = match(true) {
                            in_array((int)$aw->award_id, [4,5,6]) =>
                                'bg-yellow-500/20 border-yellow-500/50 text-yellow-300',
                            (int)$aw->award_id === 7 =>
                                'bg-blue-500/20 border-blue-500/50 text-blue-300',
                            (int)$aw->award_id === 11 =>
                                'bg-sky-500/20 border-sky-500/50 text-sky-300',
                            (int)$aw->award_id === 9 =>
                                'bg-green-500/20 border-green-500/50 text-green-300',
                            in_array((int)$aw->award_id, [14,15]) =>
                                'bg-red-500/20 border-red-500/50 text-red-300',
                            (int)$aw->award_id === 13 =>
                                'bg-purple-500/20 border-purple-500/50 text-purple-300',
                            default =>
                                'bg-gray-700/50 border-gray-600 text-gray-300',
                        };

                        $suffix = match($finish) {
                            1 => '', 2 => '2nd', 3 => '3rd', default => $finish.'th'
                        };
                    @endphp
                    <span class="inline-flex flex-col items-center border rounded-lg px-3 py-1.5 {{ $colorClass }}">
                        <span class="text-xs font-bold">{{ $awName }}</span>
                        @if(!$isWin)
                            <span class="text-[10px] opacity-70">{{ $suffix }} place</span>
                        @endif
                    </span>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </section>
    @endif

    {{-- ── Contract Details ──────────────────────────────────────────────────── --}}
    @if($player->contract_salary ?? null)
    <section>
        <h2 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-3">Contract</h2>
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-800/40">
                    @if($player->contract_salary ?? null)
                    <tr><td class="py-2 px-4 text-gray-500 w-40">Salary</td><td class="py-2 px-4 text-white font-semibold">${{ number_format((float)$player->contract_salary) }}</td></tr>
                    @endif
                    @if($player->contract_years_remaining ?? null)
                    <tr><td class="py-2 px-4 text-gray-500 w-40">Years Remaining</td><td class="py-2 px-4 text-white font-semibold">{{ $player->contract_years_remaining }}</td></tr>
                    @endif
                    @if($player->contract_type ?? null)
                    <tr><td class="py-2 px-4 text-gray-500 w-40">Contract Type</td><td class="py-2 px-4 text-white font-semibold">{{ $player->contract_type }}</td></tr>
                    @endif
                    @if($player->contract_no_trade ?? null)
                    <tr><td class="py-2 px-4 text-gray-500 w-40">No-Trade Clause</td><td class="py-2 px-4 text-white font-semibold">{{ $player->contract_no_trade ? 'Yes' : 'No' }}</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </section>
    @endif

    {{-- ── Personal Info ─────────────────────────────────────────────────────── --}}
    <section>
        <h2 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-3">Personal Information</h2>
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <tbody class="divide-y divide-gray-800/40">
                    <tr><td class="py-2 px-4 text-gray-500 w-40">Full Name</td><td class="py-2 px-4 text-white font-semibold">{{ $player->first_name }} {{ $player->last_name }}</td></tr>
                    @if($player->nick_name)
                    <tr><td class="py-2 px-4 text-gray-500 w-40">Nickname</td><td class="py-2 px-4 text-white font-semibold">"{{ $player->nick_name }}"</td></tr>
                    @endif
                    <tr><td class="py-2 px-4 text-gray-500 w-40">Position</td><td class="py-2 px-4 text-white font-semibold">{{ $posName }}</td></tr>
                    <tr><td class="py-2 px-4 text-gray-500 w-40">Bats / Throws</td><td class="py-2 px-4 text-white font-semibold">{{ $batLabel }} / {{ $throwLabel }}</td></tr>
                    @if($feet)
                    <tr><td class="py-2 px-4 text-gray-500 w-40">Height / Weight</td><td class="py-2 px-4 text-white font-semibold">{{ $feet }}'{{ $inches }}", {{ $player->weight ?? '?' }} lbs</td></tr>
                    @endif
                    @if($dob)
                    <tr><td class="py-2 px-4 text-gray-500 w-40">Date of Birth</td><td class="py-2 px-4 text-white font-semibold">{{ $dob->format('F j, Y') }} (Age {{ $player->age }})</td></tr>
                    @endif
                    @if($birthplace)
                    <tr><td class="py-2 px-4 text-gray-500 w-40">Birthplace</td><td class="py-2 px-4 text-white font-semibold">{{ $birthplace }}</td></tr>
                    @endif
                    @if($player->nationality ?? null)
                    <tr><td class="py-2 px-4 text-gray-500 w-40">Nationality</td><td class="py-2 px-4 text-white font-semibold">{{ $player->nationality }}</td></tr>
                    @endif
                    @if($draftInfo)
                    <tr><td class="py-2 px-4 text-gray-500 w-40">Draft</td><td class="py-2 px-4 text-white font-semibold">{{ $draftInfo }}</td></tr>
                    @endif
                    @if($player->pro_debut_date ?? null)
                    <tr><td class="py-2 px-4 text-gray-500 w-40">Pro Debut</td><td class="py-2 px-4 text-white font-semibold">{{ \Carbon\Carbon::parse($player->pro_debut_date)->format('F j, Y') }}</td></tr>
                    @endif
                    @if($player->experience ?? null)
                    <tr><td class="py-2 px-4 text-gray-500 w-40">Experience</td><td class="py-2 px-4 text-white font-semibold">{{ $player->experience }} year{{ (int)$player->experience !== 1 ? 's' : '' }}</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </section>

</div>
@endsection
