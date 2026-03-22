@extends('layouts.public')
@section('title', $player->first_name . ' ' . $player->last_name)

@php
use App\Services\OotpService;
$fmtAvg = function($v) { $v = (float)$v; if ($v <= 0) return '.000'; if ($v >= 1) return '1.000'; return '.' . str_pad((string)round($v * 1000), 3, '0', STR_PAD_LEFT); };
$fmtWar = function($v) { return number_format((float)$v, 1); };
$awardNames = OotpService::AWARD_NAMES;
$awardsByYear = [];
foreach ($awards as $aw) { $awardsByYear[(int)$aw->year][] = $aw; }
krsort($awardsByYear);
@endphp

@section('content')

@include('players._header')

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-10">

    {{-- Season Stats --}}
    @if($seasonBatting || $seasonPitching)
    <section>
        <h2 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-3">
            {{ $seasonBatting->year ?? $seasonPitching->year }} Season
        </h2>
        @if($seasonBatting)
        @php $s = $seasonBatting; @endphp
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-x-auto">
            <table class="w-full text-sm text-right">
                <thead>
                    <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                        <th class="py-2.5 px-3 text-left">Team</th>
                        <th class="py-2.5 px-3">G</th><th class="py-2.5 px-3">AB</th>
                        <th class="py-2.5 px-3">R</th><th class="py-2.5 px-3">H</th>
                        <th class="py-2.5 px-3">2B</th><th class="py-2.5 px-3">3B</th>
                        <th class="py-2.5 px-3">HR</th><th class="py-2.5 px-3">RBI</th>
                        <th class="py-2.5 px-3">BB</th><th class="py-2.5 px-3">K</th>
                        <th class="py-2.5 px-3">SB</th>
                        <th class="py-2.5 px-3">AVG</th><th class="py-2.5 px-3">OBP</th>
                        <th class="py-2.5 px-3">SLG</th><th class="py-2.5 px-3">OPS</th>
                        <th class="py-2.5 px-3">WAR</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $sHLead   = (int)$s->h > 0 && (int)$s->h >= ($mlbLeaders['h'] ?? 999);
                        $sHrLead  = (int)$s->hr > 0 && (int)$s->hr >= ($mlbLeaders['hr'] ?? 999);
                        $sRbiLead = (int)$s->rbi > 0 && (int)$s->rbi >= ($mlbLeaders['rbi'] ?? 999);
                        $sSbLead  = (int)$s->sb > 0 && (int)$s->sb >= ($mlbLeaders['sb'] ?? 999);
                        $sAvgLead = (int)$s->ab >= 100 && abs($s->avg - ($mlbLeaders['avg'] ?? 0)) < 0.001;
                    @endphp
                    <tr class="border-t border-gray-800/50">
                        <td class="py-2.5 px-3 text-left text-gray-400 font-medium">{{ $player->team_abbr ?? '—' }}</td>
                        <td class="py-2.5 px-3 text-gray-300">{{ (int)$s->g }}</td>
                        <td class="py-2.5 px-3 text-gray-300">{{ (int)$s->ab }}</td>
                        <td class="py-2.5 px-3 text-gray-300">{{ (int)$s->r }}</td>
                        <td class="py-2.5 px-3 {{ $sHLead ? 'text-yellow-400 font-bold' : 'text-gray-300' }}">{{ (int)$s->h }}</td>
                        <td class="py-2.5 px-3 text-gray-300">{{ (int)$s->d }}</td>
                        <td class="py-2.5 px-3 text-gray-300">{{ (int)$s->t_triples }}</td>
                        <td class="py-2.5 px-3 {{ $sHrLead ? 'text-yellow-400 font-bold' : 'text-gray-300' }}">{{ (int)$s->hr }}</td>
                        <td class="py-2.5 px-3 {{ $sRbiLead ? 'text-yellow-400 font-bold' : 'text-gray-300' }}">{{ (int)$s->rbi }}</td>
                        <td class="py-2.5 px-3 text-gray-300">{{ (int)$s->bb }}</td>
                        <td class="py-2.5 px-3 text-gray-300">{{ (int)$s->k }}</td>
                        <td class="py-2.5 px-3 {{ $sSbLead ? 'text-yellow-400 font-bold' : 'text-gray-300' }}">{{ (int)$s->sb }}</td>
                        <td class="py-2.5 px-3 {{ $sAvgLead ? 'text-yellow-400 font-bold' : 'text-white font-semibold' }}">{{ $fmtAvg($s->avg) }}</td>
                        <td class="py-2.5 px-3 text-white font-semibold">{{ $fmtAvg($s->obp) }}</td>
                        <td class="py-2.5 px-3 text-white font-semibold">{{ $fmtAvg($s->slg) }}</td>
                        <td class="py-2.5 px-3 font-semibold text-white">{{ $fmtAvg($s->ops) }}</td>
                        <td class="py-2.5 px-3 font-semibold text-white">{{ $fmtWar($s->war) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        @endif

        @if($seasonPitching)
        @php $s = $seasonPitching; @endphp
        <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-x-auto">
            <table class="w-full text-sm text-right">
                <thead>
                    <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                        <th class="py-2.5 px-3 text-left">Team</th>
                        <th class="py-2.5 px-3">G</th><th class="py-2.5 px-3">GS</th>
                        <th class="py-2.5 px-3">W</th><th class="py-2.5 px-3">L</th>
                        <th class="py-2.5 px-3">SV</th><th class="py-2.5 px-3">HLD</th>
                        <th class="py-2.5 px-3">IP</th>
                        <th class="py-2.5 px-3">H</th><th class="py-2.5 px-3">ER</th>
                        <th class="py-2.5 px-3">BB</th><th class="py-2.5 px-3">K</th>
                        <th class="py-2.5 px-3">HR</th>
                        <th class="py-2.5 px-3">ERA</th><th class="py-2.5 px-3">WHIP</th>
                        <th class="py-2.5 px-3">WAR</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $sWLead   = (int)$s->w > 0 && (int)$s->w >= ($mlbLeaders['w'] ?? 999);
                        $sKLead   = (int)$s->k > 0 && (int)$s->k >= ($mlbLeaders['k'] ?? 999);
                        $sSvLead  = (int)$s->sv > 0 && (int)$s->sv >= ($mlbLeaders['sv'] ?? 999);
                        $sEraLead = is_numeric($s->era) && (int)$s->outs >= 45 && (float)$s->era <= ($mlbLeaders['era'] ?? 0) + 0.005;
                    @endphp
                    <tr class="border-t border-gray-800/50">
                        <td class="py-2.5 px-3 text-left text-gray-400 font-medium">{{ $player->team_abbr ?? '—' }}</td>
                        <td class="py-2.5 px-3 text-gray-300">{{ (int)$s->g }}</td>
                        <td class="py-2.5 px-3 text-gray-300">{{ (int)$s->gs }}</td>
                        <td class="py-2.5 px-3 {{ $sWLead ? 'text-yellow-400 font-bold' : 'text-gray-300' }}">{{ (int)$s->w }}</td>
                        <td class="py-2.5 px-3 text-gray-300">{{ (int)$s->l }}</td>
                        <td class="py-2.5 px-3 {{ $sSvLead ? 'text-yellow-400 font-bold' : 'text-gray-300' }}">{{ (int)$s->sv }}</td>
                        <td class="py-2.5 px-3 text-gray-300">{{ (int)$s->hld }}</td>
                        <td class="py-2.5 px-3 text-white font-semibold">{{ $s->ip_display }}</td>
                        <td class="py-2.5 px-3 text-gray-300">{{ (int)$s->h }}</td>
                        <td class="py-2.5 px-3 text-gray-300">{{ (int)$s->er }}</td>
                        <td class="py-2.5 px-3 text-gray-300">{{ (int)$s->bb }}</td>
                        <td class="py-2.5 px-3 {{ $sKLead ? 'text-yellow-400 font-bold' : 'text-gray-300' }}">{{ (int)$s->k }}</td>
                        <td class="py-2.5 px-3 text-gray-300">{{ (int)$s->hr }}</td>
                        <td class="py-2.5 px-3 font-semibold {{ $sEraLead ? 'text-yellow-400' : 'text-white' }}">{{ $s->era }}</td>
                        <td class="py-2.5 px-3 text-white font-semibold">{{ $s->whip }}</td>
                        <td class="py-2.5 px-3 font-semibold text-white">{{ $fmtWar($s->war) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        @endif
    </section>
    @endif

    {{-- Awards summary --}}
    @if(!empty($awardsByYear))
    <section>
        <h2 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-3">Awards</h2>
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
            <div class="space-y-2">
                @foreach($awardsByYear as $year => $yearAwards)
                <div class="flex items-start gap-3">
                    <span class="text-sm font-bold text-white shrink-0 w-10">{{ $year }}</span>
                    <div class="flex flex-wrap gap-2">
                        @foreach($yearAwards as $aw)
                        <span class="text-xs bg-gray-800 text-gray-300 px-2 py-1 rounded">{{ $awardNames[$aw->award_id] ?? 'Award #'.$aw->award_id }}</span>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

</div>
@endsection
