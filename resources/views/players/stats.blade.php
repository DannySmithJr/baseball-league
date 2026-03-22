@extends('layouts.public')
@section('title', $player->first_name . ' ' . $player->last_name . ' — Stats')

@section('content')
@php $activeTab = 'player.stats'; @endphp
@include('players._header')

@php
    $fmtAvg = function($v) {
        $v = (float) $v;
        if ($v <= 0) return '.000';
        if ($v >= 1) return '1.000';
        return '.' . str_pad((string) round($v * 1000), 3, '0', STR_PAD_LEFT);
    };

    $fmtWar = function($v) {
        return number_format((float)$v, 1);
    };

    $levelLabels = [1 => 'MLB', 2 => 'AAA', 3 => 'AA', 4 => 'A+', 5 => 'A', 6 => 'Rk'];

    // Use filtered data from controller (filtered by selected level)
    $mlbBatting    = $filteredBatting ?? collect();
    $mlbPitching   = $filteredPitching ?? collect();
    $mlbFielding   = $filteredFielding ?? collect();
    $levelName     = $levelLabelsMap[$levelFilter ?? 1] ?? 'MLB';

    // Totals helper
    $batTotals = function ($rows, $label = 'Career') use ($fmtAvg, $fmtWar) {
        $g=$rows->sum(fn($r)=>(int)$r->g); $ab=$rows->sum(fn($r)=>(int)$r->ab);
        $r2=$rows->sum(fn($r)=>(int)$r->r); $h=$rows->sum(fn($r)=>(int)$r->h);
        $d=$rows->sum(fn($r)=>(int)$r->d); $t=$rows->sum(fn($r)=>(int)$r->t_triples);
        $hr=$rows->sum(fn($r)=>(int)$r->hr); $rbi=$rows->sum(fn($r)=>(int)$r->rbi);
        $bb=$rows->sum(fn($r)=>(int)$r->bb); $k=$rows->sum(fn($r)=>(int)$r->k);
        $sb=$rows->sum(fn($r)=>(int)$r->sb); $cs=$rows->sum(fn($r)=>(int)($r->cs ?? 0));
        $hp=$rows->sum(fn($r)=>(int)$r->hp); $sf=$rows->sum(fn($r)=>(int)$r->sf);
        $war=$rows->sum('war');
        $avg=$ab>0?$h/$ab:0; $obp=($ab+$bb+$hp+$sf)>0?($h+$bb+$hp)/($ab+$bb+$hp+$sf):0;
        $slg=$ab>0?(($h-$d-$t-$hr)+2*$d+3*$t+4*$hr)/$ab:0; $ops=$obp+$slg;
        return compact('label','g','ab','r2','h','d','t','hr','rbi','bb','hp','k','sb','cs','war','avg','obp','slg','ops');
    };

    $pitTotals = function ($rows, $label = 'Career') use ($fmtWar) {
        $g=$rows->sum(fn($r)=>(int)$r->g); $gs=$rows->sum(fn($r)=>(int)$r->gs);
        $w=$rows->sum(fn($r)=>(int)$r->w); $l=$rows->sum(fn($r)=>(int)$r->l);
        $sv=$rows->sum(fn($r)=>(int)$r->sv); $hld=$rows->sum(fn($r)=>(int)$r->hld);
        $bs=$rows->sum(fn($r)=>(int)($r->bs ?? 0)); $r2=$rows->sum(fn($r)=>(int)($r->r ?? 0));
        $outs=$rows->sum(fn($r)=>(int)$r->outs); $h=$rows->sum(fn($r)=>(int)$r->h);
        $er=$rows->sum(fn($r)=>(int)$r->er); $bb=$rows->sum(fn($r)=>(int)$r->bb);
        $k=$rows->sum(fn($r)=>(int)$r->k); $hr=$rows->sum(fn($r)=>(int)$r->hr);
        $war=$rows->sum('war'); $ip=$outs/3;
        $ipD=floor($outs/3).'.'.($outs%3);
        $era=$ip>0?number_format(($er/$ip)*9,2):'—';
        $whip=$ip>0?number_format(($h+$bb)/$ip,2):'—';
        $wpct=($w+$l)>0?number_format($w/($w+$l),3):'—';
        $kbb=$bb>0?number_format($k/$bb,1):'—';
        return compact('label','g','gs','w','l','sv','hld','bs','r2','outs','ipD','h','er','bb','k','hr','war','era','whip','wpct','kbb');
    };

@endphp

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-10">

    {{-- Stat Type Selector --}}
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-bold text-white">Stats</h2>
        <div class="flex items-center gap-3">
            {{-- Level selector --}}
            <div class="relative">
                <select onchange="window.location.href=this.value"
                        class="bg-gray-800 border border-gray-700 text-white text-sm rounded-lg px-4 py-2 pr-8 appearance-none cursor-pointer hover:border-gray-600 focus:border-red-500 focus:ring-1 focus:ring-red-500 focus:outline-none">
                    @foreach($availableLevels as $lvl)
                    <option value="{{ route('player.stats', $player->player_id) }}?type={{ $statType }}&level={{ $lvl }}" {{ $levelFilter === $lvl ? 'selected' : '' }}>
                        {{ $levelLabelsMap[$lvl] ?? 'Level '.$lvl }}
                    </option>
                    @endforeach
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
            </div>
            {{-- Stat type selector --}}
            <div class="relative">
                <select onchange="window.location.href=this.value"
                        class="bg-gray-800 border border-gray-700 text-white text-sm rounded-lg px-4 py-2 pr-8 appearance-none cursor-pointer hover:border-gray-600 focus:border-red-500 focus:ring-1 focus:ring-red-500 focus:outline-none">
                    <option value="{{ route('player.stats', $player->player_id) }}?type=batting&level={{ $levelFilter }}" {{ $statType === 'batting' ? 'selected' : '' }}>Batting</option>
                    <option value="{{ route('player.stats', $player->player_id) }}?type=pitching&level={{ $levelFilter }}" {{ $statType === 'pitching' ? 'selected' : '' }}>Pitching</option>
                    <option value="{{ route('player.stats', $player->player_id) }}?type=fielding&level={{ $levelFilter }}" {{ $statType === 'fielding' ? 'selected' : '' }}>Fielding</option>
                </select>
                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                    <svg class="h-4 w-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Career Stats — split MLB / Minors ────────────────────────────────── --}}
    @if($careerBatting->isNotEmpty() || $careerPitching->isNotEmpty())
    <section class="space-y-6">

    @if($statType === 'batting')
        {{-- BATTING CAREER — MLB then Minors then Level Totals --}}
        @foreach([
            ['label' => 'Career Batting — ' . $levelName, 'rows' => $mlbBatting],
        ] as $careerSection)
        @if($careerSection['rows']->isNotEmpty())
        @php
            $batYearGroups = $careerSection['rows']->groupBy('year');
        @endphp
        <div>
            <h2 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-3">{{ $careerSection['label'] }}</h2>
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead>
                        <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="py-2.5 px-3 text-left">Season</th>
                            <th class="py-2.5 px-3 text-left">Team</th>
                            <th class="py-2.5 px-3">GP</th><th class="py-2.5 px-3">AB</th>
                            <th class="py-2.5 px-3">R</th><th class="py-2.5 px-3">H</th>
                            <th class="py-2.5 px-3">2B</th><th class="py-2.5 px-3">3B</th>
                            <th class="py-2.5 px-3">HR</th><th class="py-2.5 px-3">RBI</th>
                            <th class="py-2.5 px-3">BB</th><th class="py-2.5 px-3">HBP</th>
                            <th class="py-2.5 px-3">K</th>
                            <th class="py-2.5 px-3">SB</th><th class="py-2.5 px-3">CS</th>
                            <th class="py-2.5 px-3">AVG</th><th class="py-2.5 px-3">OBP</th>
                            <th class="py-2.5 px-3">SLG</th>
                            <th class="py-2.5 px-3">OPS</th>
                            <th class="py-2.5 px-3">WAR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($batYearGroups as $yr => $yearRows)
                        @php $multiTeam = $yearRows->count() > 1; @endphp
                        @foreach($yearRows as $row)
                        @php
                            $isRowMlb = (int)($row->team_level ?? 0) === 1;
                            $yrLdr = $isRowMlb ? ($mlbLeadersByYear[$row->year] ?? []) : [];
                            $yrHrLead  = $isRowMlb && (int)$row->hr > 0 && (int)$row->hr >= ($yrLdr['hr'] ?? 999);
                            $yrRbiLead = $isRowMlb && (int)$row->rbi > 0 && (int)$row->rbi >= ($yrLdr['rbi'] ?? 999);
                            $yrHLead   = $isRowMlb && (int)$row->h > 0 && (int)$row->h >= ($yrLdr['h'] ?? 999);
                            $yrSbLead  = $isRowMlb && (int)$row->sb > 0 && (int)$row->sb >= ($yrLdr['sb'] ?? 999);
                            $yrAvgLead = $isRowMlb && (int)$row->ab >= 100 && abs($row->avg - ($yrLdr['avg'] ?? 0)) < 0.001;
                        @endphp
                        <tr class="border-t border-gray-800/50 hover:bg-gray-800/30 transition">
                            <td class="py-2 px-3 font-semibold text-white">{{ $row->year }}</td>
                            <td class="py-2 px-3 text-left text-gray-400">{{ $row->team_abbr ?: '—' }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->g }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->ab }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->r }}</td>
                            <td class="py-2 px-3 {{ $yrHLead ? 'text-yellow-400 font-bold' : 'text-gray-300' }}">{{ (int)$row->h }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->d }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->t_triples }}</td>
                            <td class="py-2 px-3 {{ $yrHrLead ? 'text-yellow-400 font-bold' : 'text-gray-300' }}">{{ (int)$row->hr }}</td>
                            <td class="py-2 px-3 {{ $yrRbiLead ? 'text-yellow-400 font-bold' : 'text-gray-300' }}">{{ (int)$row->rbi }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->bb }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)($row->hp ?? 0) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->k }}</td>
                            <td class="py-2 px-3 {{ $yrSbLead ? 'text-yellow-400 font-bold' : 'text-gray-300' }}">{{ (int)$row->sb }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)($row->cs ?? 0) }}</td>
                            <td class="py-2 px-3 {{ $yrAvgLead ? 'text-yellow-400 font-bold' : 'text-white font-semibold' }}">{{ $fmtAvg($row->avg) }}</td>
                            <td class="py-2 px-3 text-white font-semibold">{{ $fmtAvg($row->obp) }}</td>
                            <td class="py-2 px-3 text-white font-semibold">{{ $fmtAvg($row->slg) }}</td>
                            <td class="py-2 px-3 font-semibold text-white">{{ $fmtAvg($row->ops) }}</td>
                            <td class="py-2 px-3 font-semibold text-gray-300">{{ $fmtWar($row->war) }}</td>
                        </tr>
                        @endforeach
                        {{-- Combined year total if multiple teams --}}
                        @if($multiTeam)
                        @php $yrTot = $batTotals($yearRows, $yr . ' Total'); @endphp
                        <tr class="border-t border-gray-700/50 bg-gray-800/20 font-semibold text-white">
                            <td class="py-2 px-3 font-bold">{{ $yr }}</td>
                            <td class="py-2 px-3 text-left text-gray-400 text-xs italic">Combined</td>
                            <td class="py-2 px-3">{{ $yrTot['g'] }}</td><td class="py-2 px-3">{{ $yrTot['ab'] }}</td>
                            <td class="py-2 px-3">{{ $yrTot['r2'] }}</td><td class="py-2 px-3">{{ $yrTot['h'] }}</td>
                            <td class="py-2 px-3">{{ $yrTot['d'] }}</td><td class="py-2 px-3">{{ $yrTot['t'] }}</td>
                            <td class="py-2 px-3">{{ $yrTot['hr'] }}</td><td class="py-2 px-3">{{ $yrTot['rbi'] }}</td>
                            <td class="py-2 px-3">{{ $yrTot['bb'] }}</td><td class="py-2 px-3">{{ $yrTot['hp'] }}</td>
                            <td class="py-2 px-3">{{ $yrTot['k'] }}</td>
                            <td class="py-2 px-3">{{ $yrTot['sb'] }}</td><td class="py-2 px-3">{{ $yrTot['cs'] }}</td>
                            <td class="py-2 px-3">{{ $fmtAvg($yrTot['avg']) }}</td><td class="py-2 px-3">{{ $fmtAvg($yrTot['obp']) }}</td>
                            <td class="py-2 px-3">{{ $fmtAvg($yrTot['slg']) }}</td><td class="py-2 px-3">{{ $fmtAvg($yrTot['ops']) }}</td>
                            <td class="py-2 px-3">{{ $fmtWar($yrTot['war']) }}</td>
                        </tr>
                        @endif
                        @endforeach
                        {{-- Career totals --}}
                        @php $tot = $batTotals($careerSection['rows'], 'Career'); @endphp
                        <tr class="border-t-2 border-gray-700 bg-gray-800/30 font-bold text-white">
                            <td class="py-2.5 px-3 text-left text-xs text-gray-400 uppercase tracking-wider" colspan="2">Career</td>
                            <td class="py-2.5 px-3">{{ $tot['g'] }}</td><td class="py-2.5 px-3">{{ $tot['ab'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['r2'] }}</td><td class="py-2.5 px-3">{{ $tot['h'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['d'] }}</td><td class="py-2.5 px-3">{{ $tot['t'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['hr'] }}</td><td class="py-2.5 px-3">{{ $tot['rbi'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['bb'] }}</td><td class="py-2.5 px-3">{{ $tot['hp'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['k'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['sb'] }}</td><td class="py-2.5 px-3">{{ $tot['cs'] }}</td>
                            <td class="py-2.5 px-3">{{ $fmtAvg($tot['avg']) }}</td><td class="py-2.5 px-3">{{ $fmtAvg($tot['obp']) }}</td>
                            <td class="py-2.5 px-3">{{ $fmtAvg($tot['slg']) }}</td><td class="py-2.5 px-3">{{ $fmtAvg($tot['ops']) }}</td>
                            <td class="py-2.5 px-3">{{ $fmtWar($tot['war']) }}</td>
                        </tr>
                        {{-- Season Averages --}}
                        @php
                            $seasonCount = $batYearGroups->count();
                            if ($seasonCount > 1) {
                                $savg = [
                                    'g' => round($tot['g']/$seasonCount),
                                    'ab' => round($tot['ab']/$seasonCount),
                                    'r2' => round($tot['r2']/$seasonCount),
                                    'h' => round($tot['h']/$seasonCount),
                                    'd' => round($tot['d']/$seasonCount),
                                    't' => round($tot['t']/$seasonCount),
                                    'hr' => round($tot['hr']/$seasonCount),
                                    'rbi' => round($tot['rbi']/$seasonCount),
                                    'bb' => round($tot['bb']/$seasonCount),
                                    'hp' => round($tot['hp']/$seasonCount),
                                    'k' => round($tot['k']/$seasonCount),
                                    'sb' => round($tot['sb']/$seasonCount),
                                    'cs' => round($tot['cs']/$seasonCount),
                                ];
                            }
                        @endphp
                        @if($seasonCount > 1)
                        <tr class="border-t border-gray-700 bg-gray-800/20 font-semibold text-gray-300">
                            <td class="py-2.5 px-3 text-left text-xs text-gray-400 uppercase tracking-wider" colspan="2">Season Averages</td>
                            <td class="py-2.5 px-3">{{ $savg['g'] }}</td><td class="py-2.5 px-3">{{ $savg['ab'] }}</td>
                            <td class="py-2.5 px-3">{{ $savg['r2'] }}</td><td class="py-2.5 px-3">{{ $savg['h'] }}</td>
                            <td class="py-2.5 px-3">{{ $savg['d'] }}</td><td class="py-2.5 px-3">{{ $savg['t'] }}</td>
                            <td class="py-2.5 px-3">{{ $savg['hr'] }}</td><td class="py-2.5 px-3">{{ $savg['rbi'] }}</td>
                            <td class="py-2.5 px-3">{{ $savg['bb'] }}</td><td class="py-2.5 px-3">{{ $savg['hp'] }}</td>
                            <td class="py-2.5 px-3">{{ $savg['k'] }}</td>
                            <td class="py-2.5 px-3">{{ $savg['sb'] }}</td><td class="py-2.5 px-3">{{ $savg['cs'] }}</td>
                            <td class="py-2.5 px-3">{{ $fmtAvg($tot['avg']) }}</td><td class="py-2.5 px-3">{{ $fmtAvg($tot['obp']) }}</td>
                            <td class="py-2.5 px-3">{{ $fmtAvg($tot['slg']) }}</td><td class="py-2.5 px-3">{{ $fmtAvg($tot['ops']) }}</td>
                            <td class="py-2.5 px-3">—</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
        @endif
        @endforeach

        {{-- POSTSEASON BATTING --}}
        @if($postseasonBatting->isNotEmpty())
        <div>
            <h2 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-3">Postseason Batting</h2>
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead>
                        <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="py-2.5 px-3 text-left">Season</th>
                            <th class="py-2.5 px-3 text-left">Team</th>
                            <th class="py-2.5 px-3">GP</th><th class="py-2.5 px-3">AB</th>
                            <th class="py-2.5 px-3">R</th><th class="py-2.5 px-3">H</th>
                            <th class="py-2.5 px-3">2B</th><th class="py-2.5 px-3">3B</th>
                            <th class="py-2.5 px-3">HR</th><th class="py-2.5 px-3">RBI</th>
                            <th class="py-2.5 px-3">BB</th><th class="py-2.5 px-3">HBP</th>
                            <th class="py-2.5 px-3">K</th>
                            <th class="py-2.5 px-3">SB</th><th class="py-2.5 px-3">CS</th>
                            <th class="py-2.5 px-3">AVG</th><th class="py-2.5 px-3">OBP</th>
                            <th class="py-2.5 px-3">SLG</th>
                            <th class="py-2.5 px-3">OPS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($postseasonBatting as $row)
                        <tr class="border-t border-gray-800/50 hover:bg-gray-800/30 transition">
                            <td class="py-2 px-3 font-semibold text-white">{{ $row->year }}</td>
                            <td class="py-2 px-3 text-left text-gray-400">{{ $row->team_abbr ?: '—' }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->g }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->ab }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->r }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->h }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->d }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->t_triples }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->hr }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->rbi }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->bb }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)($row->hp ?? 0) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->k }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->sb }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)($row->cs ?? 0) }}</td>
                            <td class="py-2 px-3 text-white font-semibold">{{ $fmtAvg($row->avg) }}</td>
                            <td class="py-2 px-3 text-white font-semibold">{{ $fmtAvg($row->obp) }}</td>
                            <td class="py-2 px-3 text-white font-semibold">{{ $fmtAvg($row->slg) }}</td>
                            <td class="py-2 px-3 font-semibold text-white">{{ $fmtAvg($row->ops) }}</td>
                        </tr>
                        @endforeach
                        @if($postseasonBatting->count() > 1)
                        @php $psTot = $batTotals($postseasonBatting, 'Career'); @endphp
                        <tr class="border-t-2 border-gray-700 bg-gray-800/30 font-bold text-white">
                            <td class="py-2.5 px-3 text-left text-xs text-gray-400 uppercase tracking-wider" colspan="2">Career</td>
                            <td class="py-2.5 px-3">{{ $psTot['g'] }}</td><td class="py-2.5 px-3">{{ $psTot['ab'] }}</td>
                            <td class="py-2.5 px-3">{{ $psTot['r2'] }}</td><td class="py-2.5 px-3">{{ $psTot['h'] }}</td>
                            <td class="py-2.5 px-3">{{ $psTot['d'] }}</td><td class="py-2.5 px-3">{{ $psTot['t'] }}</td>
                            <td class="py-2.5 px-3">{{ $psTot['hr'] }}</td><td class="py-2.5 px-3">{{ $psTot['rbi'] }}</td>
                            <td class="py-2.5 px-3">{{ $psTot['bb'] }}</td><td class="py-2.5 px-3">{{ $psTot['hp'] }}</td>
                            <td class="py-2.5 px-3">{{ $psTot['k'] }}</td>
                            <td class="py-2.5 px-3">{{ $psTot['sb'] }}</td><td class="py-2.5 px-3">{{ $psTot['cs'] }}</td>
                            <td class="py-2.5 px-3">{{ $fmtAvg($psTot['avg']) }}</td><td class="py-2.5 px-3">{{ $fmtAvg($psTot['obp']) }}</td>
                            <td class="py-2.5 px-3">{{ $fmtAvg($psTot['slg']) }}</td><td class="py-2.5 px-3">{{ $fmtAvg($psTot['ops']) }}</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- EXPANDED BATTING --}}
        @if($mlbBatting->isNotEmpty())
        @php
            $expBatTotals = function($rows) {
                $pa = $rows->sum(fn($r) => (int)($r->pa ?? 0));
                $pi = $rows->sum(fn($r) => (int)($r->pitches_seen ?? 0));
                $h = $rows->sum(fn($r) => (int)$r->h); $d = $rows->sum(fn($r) => (int)$r->d);
                $t = $rows->sum(fn($r) => (int)$r->t_triples); $hr = $rows->sum(fn($r) => (int)$r->hr);
                $ab = $rows->sum(fn($r) => (int)$r->ab); $bb = $rows->sum(fn($r) => (int)$r->bb);
                $sb = $rows->sum(fn($r) => (int)$r->sb); $cs = $rows->sum(fn($r) => (int)($r->cs ?? 0));
                $singles = $h - $d - $t - $hr;
                $tb = $singles + 2*$d + 3*$t + 4*$hr;
                $xbh = $d + $t + $hr;
                return [
                    'pa' => $pa, 'pi' => $pi, 'ppa' => $pa > 0 ? $pi/$pa : 0,
                    'xbh' => $xbh, 'tb' => $tb,
                    'ibb' => $rows->sum(fn($r) => (int)($r->ibb ?? 0)),
                    'hp' => $rows->sum(fn($r) => (int)($r->hp ?? 0)),
                    'gdp' => $rows->sum(fn($r) => (int)($r->gdp ?? 0)),
                    'sh' => $rows->sum(fn($r) => (int)($r->sh ?? 0)),
                    'sf' => $rows->sum(fn($r) => (int)($r->sf ?? 0)),
                    'sb' => $sb, 'cs' => $cs,
                    'sbpct' => ($sb+$cs) > 0 ? $sb/($sb+$cs) : 0,
                ];
            };
        @endphp
        <div>
            <h2 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-3">Expanded Batting</h2>
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead>
                        <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="py-2.5 px-3 text-left">Season</th>
                            <th class="py-2.5 px-3 text-left">Team</th>
                            <th class="py-2.5 px-3">PA</th><th class="py-2.5 px-3">P</th>
                            <th class="py-2.5 px-3">P/PA</th>
                            <th class="py-2.5 px-3">XBH</th><th class="py-2.5 px-3">TB</th>
                            <th class="py-2.5 px-3">IBB</th><th class="py-2.5 px-3">HBP</th>
                            <th class="py-2.5 px-3">GIDP</th>
                            <th class="py-2.5 px-3">SH</th><th class="py-2.5 px-3">SF</th>
                            <th class="py-2.5 px-3">SB</th><th class="py-2.5 px-3">CS</th>
                            <th class="py-2.5 px-3">SB%</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($mlbBatting as $row)
                        <tr class="border-t border-gray-800/50 hover:bg-gray-800/30 transition">
                            <td class="py-2 px-3 font-semibold text-white">{{ $row->year }}</td>
                            <td class="py-2 px-3 text-left text-gray-400">{{ $row->team_abbr ?: '—' }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)($row->pa ?? 0) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ number_format((int)($row->pitches_seen ?? 0)) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $row->ppa > 0 ? number_format($row->ppa, 2) : '—' }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $row->xbh }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $row->tb }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)($row->ibb ?? 0) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)($row->hp ?? 0) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)($row->gdp ?? 0) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)($row->sh ?? 0) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)($row->sf ?? 0) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->sb }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)($row->cs ?? 0) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $row->sbpct > 0 ? number_format($row->sbpct * 100, 1) : '0.00' }}</td>
                        </tr>
                        @endforeach
                        @php $eTot = $expBatTotals($mlbBatting); @endphp
                        <tr class="border-t-2 border-gray-700 bg-gray-800/30 font-bold text-white">
                            <td class="py-2.5 px-3 text-left text-xs text-gray-400 uppercase tracking-wider" colspan="2">Career</td>
                            <td class="py-2.5 px-3">{{ $eTot['pa'] }}</td>
                            <td class="py-2.5 px-3">{{ number_format($eTot['pi']) }}</td>
                            <td class="py-2.5 px-3">{{ $eTot['ppa'] > 0 ? number_format($eTot['ppa'], 2) : '—' }}</td>
                            <td class="py-2.5 px-3">{{ $eTot['xbh'] }}</td>
                            <td class="py-2.5 px-3">{{ $eTot['tb'] }}</td>
                            <td class="py-2.5 px-3">{{ $eTot['ibb'] }}</td>
                            <td class="py-2.5 px-3">{{ $eTot['hp'] }}</td>
                            <td class="py-2.5 px-3">{{ $eTot['gdp'] }}</td>
                            <td class="py-2.5 px-3">{{ $eTot['sh'] }}</td>
                            <td class="py-2.5 px-3">{{ $eTot['sf'] }}</td>
                            <td class="py-2.5 px-3">{{ $eTot['sb'] }}</td>
                            <td class="py-2.5 px-3">{{ $eTot['cs'] }}</td>
                            <td class="py-2.5 px-3">{{ $eTot['sbpct'] > 0 ? number_format($eTot['sbpct'] * 100, 2) : '0.00' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- ADVANCED BATTING --}}
        @if($mlbBatting->isNotEmpty())
        @php
            $advBatTotals = function($rows) {
                $ab = $rows->sum(fn($r) => (int)$r->ab); $h = $rows->sum(fn($r) => (int)$r->h);
                $d = $rows->sum(fn($r) => (int)$r->d); $t = $rows->sum(fn($r) => (int)$r->t_triples);
                $hr = $rows->sum(fn($r) => (int)$r->hr); $bb = $rows->sum(fn($r) => (int)$r->bb);
                $hp = $rows->sum(fn($r) => (int)($r->hp ?? 0)); $sf = $rows->sum(fn($r) => (int)($r->sf ?? 0));
                $sb = $rows->sum(fn($r) => (int)$r->sb); $cs = $rows->sum(fn($r) => (int)($r->cs ?? 0));
                $k = $rows->sum(fn($r) => (int)$r->k); $pa = $rows->sum(fn($r) => (int)($r->pa ?? 0));
                $singles = $h - $d - $t - $hr; $tb = $singles + 2*$d + 3*$t + 4*$hr;
                $avg = $ab > 0 ? $h/$ab : 0;
                $obp = ($ab+$bb+$hp+$sf) > 0 ? ($h+$bb+$hp)/($ab+$bb+$hp+$sf) : 0;
                $slg = $ab > 0 ? $tb/$ab : 0;
                $rc = ($ab+$bb) > 0 ? ($h+$bb)*$tb/($ab+$bb) : 0;
                return [
                    'rc' => $rc, 'rc27' => ($ab-$h) > 0 ? $rc/(($ab-$h)/27) : 0,
                    'isop' => $slg - $avg, 'seca' => $ab > 0 ? ($tb-$h+$bb+$sb-$cs)/$ab : 0,
                    'abhr' => $hr > 0 ? $ab/$hr : 0, 'bbpa' => $pa > 0 ? $bb/$pa : 0,
                    'bbk' => $k > 0 ? $bb/$k : 0,
                ];
            };
        @endphp
        <div>
            <h2 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-3">Advanced Batting</h2>
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead>
                        <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="py-2.5 px-3 text-left">Season</th>
                            <th class="py-2.5 px-3 text-left">Team</th>
                            <th class="py-2.5 px-3">WAR</th>
                            <th class="py-2.5 px-3">RC</th><th class="py-2.5 px-3">RC/27</th>
                            <th class="py-2.5 px-3">ISOP</th><th class="py-2.5 px-3">SECA</th>
                            <th class="py-2.5 px-3">AB/HR</th>
                            <th class="py-2.5 px-3">BB/PA</th><th class="py-2.5 px-3">BB/K</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($mlbBatting as $row)
                        <tr class="border-t border-gray-800/50 hover:bg-gray-800/30 transition">
                            <td class="py-2 px-3 font-semibold text-white">{{ $row->year }}</td>
                            <td class="py-2 px-3 text-left text-gray-400">{{ $row->team_abbr ?: '—' }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ number_format((float)$row->war, 1) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ number_format($row->rc, 1) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ number_format($row->rc27, 1) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ number_format($row->isop, 3) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ number_format($row->seca, 3) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $row->abhr > 0 ? number_format($row->abhr, 1) : '—' }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ number_format($row->bbpa, 3) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ number_format($row->bbk, 2) }}</td>
                        </tr>
                        @endforeach
                        @php $aTot = $advBatTotals($mlbBatting); @endphp
                        <tr class="border-t-2 border-gray-700 bg-gray-800/30 font-bold text-white">
                            <td class="py-2.5 px-3 text-left text-xs text-gray-400 uppercase tracking-wider" colspan="2">Career</td>
                            <td class="py-2.5 px-3">—</td>
                            <td class="py-2.5 px-3">{{ number_format($aTot['rc'], 1) }}</td>
                            <td class="py-2.5 px-3">{{ number_format($aTot['rc27'], 1) }}</td>
                            <td class="py-2.5 px-3">{{ number_format($aTot['isop'], 3) }}</td>
                            <td class="py-2.5 px-3">{{ number_format($aTot['seca'], 3) }}</td>
                            <td class="py-2.5 px-3">{{ $aTot['abhr'] > 0 ? number_format($aTot['abhr'], 1) : '—' }}</td>
                            <td class="py-2.5 px-3">{{ number_format($aTot['bbpa'], 3) }}</td>
                            <td class="py-2.5 px-3">{{ number_format($aTot['bbk'], 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- BATTING GLOSSARY --}}
        <section>
            <h2 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-3">Glossary</h2>
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-1.5 text-sm">
                    @foreach([
                        '2B' => 'Doubles',
                        '3B' => 'Triples',
                        'AB' => 'At Bats',
                        'AB/HR' => 'At Bats Per Home Run',
                        'AVG' => 'Batting Average',
                        'BB' => 'Walks',
                        'BB/K' => 'Walk To Strikeout Ratio',
                        'BB/PA' => 'Walks Per Plate Appearance',
                        'CS' => 'Caught Stealing',
                        'FO' => 'Fly Balls',
                        'GIDP' => 'Ground Into Double Play',
                        'GO' => 'Ground Balls',
                        'GO/FO' => 'Ground To Fly Ball Ratio',
                        'GP' => 'Games Played',
                        'H' => 'Hits',
                        'HBP' => 'Hit By Pitch',
                        'HR' => 'Home Runs',
                        'IBB' => 'Intentional Walks',
                        'ISOP' => 'Isolated Power',
                        'OBP' => 'On Base Percentage',
                        'OPS' => 'OBP Pct + SLG Pct',
                        'OWAR' => 'Offensive Wins Above Replacement',
                        'P' => 'Pitches',
                        'P/PA' => 'Pitches Per Plate Appearance',
                        'PA' => 'Plate Appearances',
                        'R' => 'Runs',
                        'RBI' => 'Runs Batted In',
                        'RC' => 'Runs Created',
                        'RC/27' => 'Runs Created Per 27 Outs',
                        'SB' => 'Stolen Bases',
                        'SB%' => 'Stolen Base Percentage',
                        'SECA' => 'Secondary Average',
                        'SF' => 'Sacrifice Flies',
                        'SH' => 'Sacrifice Hit',
                        'SLG' => 'Slugging Percentage',
                        'SO' => 'Strikeouts',
                        'TB' => 'Total Bases',
                        'WAR' => 'Wins Above Replacement',
                        'XBH' => 'Extra Base Hits',
                    ] as $abbr => $desc)
                    <div>
                        <span class="text-white font-bold">{{ $abbr }}:</span>
                        <span class="text-gray-400">{{ $desc }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </section>

    @endif {{-- end batting --}}

    @if($statType === 'pitching')
        {{-- PITCHING CAREER --}}
        @foreach([
            ['label' => 'Career Pitching — ' . $levelName, 'rows' => $mlbPitching],
        ] as $careerSection)
        @if($careerSection['rows']->isNotEmpty())
        <div>
            <h2 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-3">{{ $careerSection['label'] }}</h2>
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead>
                        <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="py-2.5 px-3 text-left">Season</th>
                            <th class="py-2.5 px-3 text-left">Team</th>
                            <th class="py-2.5 px-3">GP</th><th class="py-2.5 px-3">GS</th>
                            <th class="py-2.5 px-3">W</th><th class="py-2.5 px-3">L</th>
                            <th class="py-2.5 px-3">W%</th>
                            <th class="py-2.5 px-3">WAR</th>
                            <th class="py-2.5 px-3">ERA</th><th class="py-2.5 px-3">WHIP</th>
                            <th class="py-2.5 px-3">IP</th>
                            <th class="py-2.5 px-3">K</th><th class="py-2.5 px-3">BB</th>
                            <th class="py-2.5 px-3">K/BB</th>
                            <th class="py-2.5 px-3">H</th><th class="py-2.5 px-3">R</th>
                            <th class="py-2.5 px-3">ER</th>
                            <th class="py-2.5 px-3">SV</th><th class="py-2.5 px-3">HLD</th>
                            <th class="py-2.5 px-3">BLSV</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $pitYearGroups = $careerSection['rows']->groupBy('year');
                        @endphp
                        @foreach($pitYearGroups as $yr => $yearRows)
                            @php $multiTeam = $yearRows->count() > 1; @endphp
                            @foreach($yearRows as $row)
                            @php
                                $isRowMlb = (int)($row->team_level ?? 0) === 1;
                                $yrLdr = $isRowMlb ? ($mlbLeadersByYear[$row->year] ?? []) : [];
                                $teamLabel = $row->team_abbr ?: '—';
                                $rwpct = ((int)$row->w + (int)$row->l) > 0 ? number_format((int)$row->w / ((int)$row->w + (int)$row->l), 3) : '—';
                                $rkbb = (int)$row->bb > 0 ? number_format((int)$row->k / (int)$row->bb, 1) : '—';
                            @endphp
                            <tr class="border-t border-gray-800/50 hover:bg-gray-800/30 transition">
                                <td class="py-2 px-3 font-semibold text-white">{{ $row->year }}</td>
                                <td class="py-2 px-3 text-left text-gray-400">{{ $teamLabel }}</td>
                                <td class="py-2 px-3">{{ (int)$row->g }}</td>
                                <td class="py-2 px-3">{{ (int)$row->gs }}</td>
                                <td class="py-2 px-3">{{ (int)$row->w }}</td>
                                <td class="py-2 px-3">{{ (int)$row->l }}</td>
                                <td class="py-2 px-3">{{ $rwpct }}</td>
                                <td class="py-2 px-3">{{ $fmtWar($row->war) }}</td>
                                <td class="py-2 px-3">{{ $row->era }}</td>
                                <td class="py-2 px-3">{{ $row->whip }}</td>
                                <td class="py-2 px-3">{{ $row->ip_display }}</td>
                                <td class="py-2 px-3">{{ (int)$row->k }}</td>
                                <td class="py-2 px-3">{{ (int)$row->bb }}</td>
                                <td class="py-2 px-3">{{ $rkbb }}</td>
                                <td class="py-2 px-3">{{ (int)$row->h }}</td>
                                <td class="py-2 px-3">{{ (int)($row->r ?? 0) }}</td>
                                <td class="py-2 px-3">{{ (int)$row->er }}</td>
                                <td class="py-2 px-3">{{ (int)$row->sv }}</td>
                                <td class="py-2 px-3">{{ (int)$row->hld }}</td>
                                <td class="py-2 px-3">{{ (int)($row->bs ?? 0) }}</td>
                            </tr>
                            @endforeach
                            {{-- Combined year total if multiple teams --}}
                            @if($multiTeam)
                            @php
                                $yrTot = $pitTotals($yearRows, $yr . ' Total');
                                $isRowMlb2 = $yearRows->first() ? (int)($yearRows->first()->team_level ?? 0) === 1 : false;
                                $yrLdr2 = $isRowMlb2 ? ($mlbLeadersByYear[$yr] ?? []) : [];
                                $yrWLead2   = $isRowMlb2 && $yrTot['w'] > 0 && $yrTot['w'] >= ($yrLdr2['w'] ?? 999);
                                $yrKLead2   = $isRowMlb2 && $yrTot['k'] > 0 && $yrTot['k'] >= ($yrLdr2['k'] ?? 999);
                                $yrSvLead2  = $isRowMlb2 && $yrTot['sv'] > 0 && $yrTot['sv'] >= ($yrLdr2['sv'] ?? 999);
                                $yrEraLead2 = $isRowMlb2 && is_numeric($yrTot['era']) && $yrTot['outs'] >= 45 && (float)$yrTot['era'] <= ($yrLdr2['era'] ?? 0) + 0.005;
                            @endphp
                            <tr class="border-t border-gray-700/50 bg-gray-800/20 font-semibold text-white">
                                <td class="py-2 px-3 font-bold">{{ $yr }}</td>
                                <td class="py-2 px-3 text-left text-gray-400 text-xs italic">Combined</td>
                                <td class="py-2 px-3">{{ $yrTot['g'] }}</td>
                                <td class="py-2 px-3">{{ $yrTot['gs'] }}</td>
                                <td class="py-2 px-3 {{ $yrWLead2 ? 'text-yellow-400 font-bold' : '' }}">{{ $yrTot['w'] }}</td>
                                <td class="py-2 px-3">{{ $yrTot['l'] }}</td>
                                <td class="py-2 px-3">{{ $yrTot['wpct'] }}</td>
                                <td class="py-2 px-3">{{ $fmtWar($yrTot['war']) }}</td>
                                <td class="py-2 px-3 {{ $yrEraLead2 ? 'text-yellow-400' : '' }}">{{ $yrTot['era'] }}</td>
                                <td class="py-2 px-3">{{ $yrTot['whip'] }}</td>
                                <td class="py-2 px-3">{{ $yrTot['ipD'] }}</td>
                                <td class="py-2 px-3 {{ $yrKLead2 ? 'text-yellow-400 font-bold' : '' }}">{{ $yrTot['k'] }}</td>
                                <td class="py-2 px-3">{{ $yrTot['bb'] }}</td>
                                <td class="py-2 px-3">{{ $yrTot['kbb'] }}</td>
                                <td class="py-2 px-3">{{ $yrTot['h'] }}</td>
                                <td class="py-2 px-3">{{ $yrTot['r2'] }}</td>
                                <td class="py-2 px-3">{{ $yrTot['er'] }}</td>
                                <td class="py-2 px-3 {{ $yrSvLead2 ? 'text-yellow-400 font-bold' : '' }}">{{ $yrTot['sv'] }}</td>
                                <td class="py-2 px-3">{{ $yrTot['hld'] }}</td>
                                <td class="py-2 px-3">{{ $yrTot['bs'] }}</td>
                            </tr>
                            @endif
                        @endforeach
                        {{-- Section totals --}}
                        @php $tot = $pitTotals($careerSection['rows'], 'Total'); @endphp
                        <tr class="border-t-2 border-gray-700 bg-gray-800/30 font-bold text-white">
                            <td class="py-2.5 px-3 text-left text-xs text-gray-400 uppercase tracking-wider" colspan="2">{{ $tot['label'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['g'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['gs'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['w'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['l'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['wpct'] }}</td>
                            <td class="py-2.5 px-3">{{ $fmtWar($tot['war']) }}</td>
                            <td class="py-2.5 px-3">{{ $tot['era'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['whip'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['ipD'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['k'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['bb'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['kbb'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['h'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['r2'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['er'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['sv'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['hld'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['bs'] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        @endif
        @endforeach

        {{-- POSTSEASON PITCHING --}}
        @if($postseasonPitching->isNotEmpty())
        <div>
            <h2 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-3">Postseason Pitching</h2>
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead>
                        <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="py-2.5 px-3 text-left">Season</th>
                            <th class="py-2.5 px-3 text-left">Team</th>
                            <th class="py-2.5 px-3">GP</th><th class="py-2.5 px-3">GS</th>
                            <th class="py-2.5 px-3">W</th><th class="py-2.5 px-3">L</th>
                            <th class="py-2.5 px-3">W%</th>
                            <th class="py-2.5 px-3">ERA</th><th class="py-2.5 px-3">WHIP</th>
                            <th class="py-2.5 px-3">IP</th>
                            <th class="py-2.5 px-3">K</th><th class="py-2.5 px-3">BB</th>
                            <th class="py-2.5 px-3">K/BB</th>
                            <th class="py-2.5 px-3">H</th><th class="py-2.5 px-3">R</th>
                            <th class="py-2.5 px-3">ER</th>
                            <th class="py-2.5 px-3">SV</th><th class="py-2.5 px-3">HLD</th>
                            <th class="py-2.5 px-3">BLSV</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($postseasonPitching as $row)
                        @php
                            $rwpct = ((int)$row->w + (int)$row->l) > 0 ? number_format((int)$row->w / ((int)$row->w + (int)$row->l), 3) : '—';
                            $rkbb = (int)$row->bb > 0 ? number_format((int)$row->k / (int)$row->bb, 1) : '—';
                        @endphp
                        <tr class="border-t border-gray-800/50 hover:bg-gray-800/30 transition">
                            <td class="py-2 px-3 font-semibold text-white">{{ $row->year }}</td>
                            <td class="py-2 px-3 text-left text-gray-400">{{ $row->team_abbr ?: '—' }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->g }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->gs }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->w }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->l }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $rwpct }}</td>
                            <td class="py-2 px-3 text-white font-semibold">{{ $row->era }}</td>
                            <td class="py-2 px-3 text-white font-semibold">{{ $row->whip }}</td>
                            <td class="py-2 px-3 text-white font-semibold">{{ $row->ip_display }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->k }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->bb }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $rkbb }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->h }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)($row->r ?? 0) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->er }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->sv }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->hld }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)($row->bs ?? 0) }}</td>
                        </tr>
                        @endforeach
                        @if($postseasonPitching->count() > 1)
                        @php $psTot = $pitTotals($postseasonPitching, 'Career'); @endphp
                        <tr class="border-t-2 border-gray-700 bg-gray-800/30 font-bold text-white">
                            <td class="py-2.5 px-3 text-left text-xs text-gray-400 uppercase tracking-wider" colspan="2">Career</td>
                            <td class="py-2.5 px-3">{{ $psTot['g'] }}</td>
                            <td class="py-2.5 px-3">{{ $psTot['gs'] }}</td>
                            <td class="py-2.5 px-3">{{ $psTot['w'] }}</td>
                            <td class="py-2.5 px-3">{{ $psTot['l'] }}</td>
                            <td class="py-2.5 px-3">{{ $psTot['wpct'] }}</td>
                            <td class="py-2.5 px-3">{{ $psTot['era'] }}</td>
                            <td class="py-2.5 px-3">{{ $psTot['whip'] }}</td>
                            <td class="py-2.5 px-3">{{ $psTot['ipD'] }}</td>
                            <td class="py-2.5 px-3">{{ $psTot['k'] }}</td>
                            <td class="py-2.5 px-3">{{ $psTot['bb'] }}</td>
                            <td class="py-2.5 px-3">{{ $psTot['kbb'] }}</td>
                            <td class="py-2.5 px-3">{{ $psTot['h'] }}</td>
                            <td class="py-2.5 px-3">{{ $psTot['r2'] }}</td>
                            <td class="py-2.5 px-3">{{ $psTot['er'] }}</td>
                            <td class="py-2.5 px-3">{{ $psTot['sv'] }}</td>
                            <td class="py-2.5 px-3">{{ $psTot['hld'] }}</td>
                            <td class="py-2.5 px-3">{{ $psTot['bs'] }}</td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- OPPONENT BATTING --}}
        @if($mlbPitching->isNotEmpty())
        @php
            $oppTotals = function($rows, $label = 'Career') {
                $ab = $rows->sum(fn($r) => (int)($r->opp_ab ?? 0));
                $h  = $rows->sum(fn($r) => (int)$r->h);
                $d  = $rows->sum(fn($r) => (int)($r->opp_2b ?? 0));
                $t  = $rows->sum(fn($r) => (int)($r->opp_3b ?? 0));
                $hr = $rows->sum(fn($r) => (int)$r->hr);
                $bb = $rows->sum(fn($r) => (int)$r->bb);
                $hbp = $rows->sum(fn($r) => (int)($r->opp_hbp ?? 0));
                $sf = $rows->sum(fn($r) => (int)($r->opp_sf ?? 0));
                $sh = $rows->sum(fn($r) => (int)($r->opp_sh ?? 0));
                $ibb = $rows->sum(fn($r) => (int)($r->opp_ibb ?? 0));
                $tbf = $rows->sum(fn($r) => (int)($r->opp_tbf ?? 0));
                $pi  = $rows->sum(fn($r) => (int)($r->opp_pitches ?? 0));
                $singles = $h - $d - $t - $hr;
                $tb = $singles + 2*$d + 3*$t + 4*$hr;
                $oba  = $ab > 0 ? number_format($h/$ab, 3) : '—';
                $oobp = ($ab+$bb+$hbp+$sf) > 0 ? number_format(($h+$bb+$hbp)/($ab+$bb+$hbp+$sf), 3) : '—';
                $oslg = $ab > 0 ? number_format($tb/$ab, 3) : '—';
                $oops = $ab > 0 ? number_format((float)$oobp + (float)$oslg, 3) : '—';
                $ptbf = $tbf > 0 ? number_format($pi/$tbf, 3) : '—';
                return compact('label','pi','tbf','ptbf','oba','oobp','oslg','oops','d','t','hr','tb','sh','sf','ibb');
            };
        @endphp
        <div>
            <h2 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-3">Opponent Batting</h2>
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead>
                        <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="py-2.5 px-3 text-left">Season</th>
                            <th class="py-2.5 px-3 text-left">Team</th>
                            <th class="py-2.5 px-3">P</th>
                            <th class="py-2.5 px-3">TBF</th>
                            <th class="py-2.5 px-3">P-TBF</th>
                            <th class="py-2.5 px-3">OBA</th>
                            <th class="py-2.5 px-3">OOBP</th>
                            <th class="py-2.5 px-3">OSLG</th>
                            <th class="py-2.5 px-3">OOPS</th>
                            <th class="py-2.5 px-3">2B</th>
                            <th class="py-2.5 px-3">3B</th>
                            <th class="py-2.5 px-3">HR</th>
                            <th class="py-2.5 px-3">TB</th>
                            <th class="py-2.5 px-3">SH</th>
                            <th class="py-2.5 px-3">SF</th>
                            <th class="py-2.5 px-3">IBB</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($mlbPitching as $row)
                        @php
                            $oAb = (int)($row->opp_ab ?? 0); $oH = (int)$row->h;
                            $oD = (int)($row->opp_2b ?? 0); $oT = (int)($row->opp_3b ?? 0); $oHr = (int)$row->hr;
                            $oBb = (int)$row->bb; $oHbp = (int)($row->opp_hbp ?? 0); $oSf = (int)($row->opp_sf ?? 0);
                            $oSingles = $oH - $oD - $oT - $oHr;
                            $oTb = $oSingles + 2*$oD + 3*$oT + 4*$oHr;
                            $rOba  = $oAb > 0 ? number_format($oH/$oAb, 3) : '—';
                            $rOobp = ($oAb+$oBb+$oHbp+$oSf) > 0 ? number_format(($oH+$oBb+$oHbp)/($oAb+$oBb+$oHbp+$oSf), 3) : '—';
                            $rOslg = $oAb > 0 ? number_format($oTb/$oAb, 3) : '—';
                            $rOops = $oAb > 0 ? number_format((float)$rOobp + (float)$rOslg, 3) : '—';
                            $oTbf = (int)($row->opp_tbf ?? 0); $oPi = (int)($row->opp_pitches ?? 0);
                            $rPtbf = $oTbf > 0 ? number_format($oPi/$oTbf, 3) : '—';
                        @endphp
                        <tr class="border-t border-gray-800/50 hover:bg-gray-800/30 transition">
                            <td class="py-2 px-3 font-semibold text-white">{{ $row->year }}</td>
                            <td class="py-2 px-3 text-left text-gray-400">{{ $row->team_abbr ?: '—' }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ number_format($oPi) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $oTbf }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $rPtbf }}</td>
                            <td class="py-2 px-3 text-white font-semibold">{{ $rOba }}</td>
                            <td class="py-2 px-3 text-white font-semibold">{{ $rOobp }}</td>
                            <td class="py-2 px-3 text-white font-semibold">{{ $rOslg }}</td>
                            <td class="py-2 px-3 text-white font-semibold">{{ $rOops }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $oD }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $oT }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $oHr }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $oTb }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)($row->opp_sh ?? 0) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $oSf }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)($row->opp_ibb ?? 0) }}</td>
                        </tr>
                        @endforeach
                        {{-- Career totals --}}
                        @php $oppTot = $oppTotals($mlbPitching); @endphp
                        <tr class="border-t-2 border-gray-700 bg-gray-800/30 font-bold text-white">
                            <td class="py-2.5 px-3 text-left text-xs text-gray-400 uppercase tracking-wider" colspan="2">Career</td>
                            <td class="py-2.5 px-3">{{ number_format($oppTot['pi']) }}</td>
                            <td class="py-2.5 px-3">{{ $oppTot['tbf'] }}</td>
                            <td class="py-2.5 px-3">{{ $oppTot['ptbf'] }}</td>
                            <td class="py-2.5 px-3">{{ $oppTot['oba'] }}</td>
                            <td class="py-2.5 px-3">{{ $oppTot['oobp'] }}</td>
                            <td class="py-2.5 px-3">{{ $oppTot['oslg'] }}</td>
                            <td class="py-2.5 px-3">{{ $oppTot['oops'] }}</td>
                            <td class="py-2.5 px-3">{{ $oppTot['d'] }}</td>
                            <td class="py-2.5 px-3">{{ $oppTot['t'] }}</td>
                            <td class="py-2.5 px-3">{{ $oppTot['hr'] }}</td>
                            <td class="py-2.5 px-3">{{ $oppTot['tb'] }}</td>
                            <td class="py-2.5 px-3">{{ $oppTot['sh'] }}</td>
                            <td class="py-2.5 px-3">{{ $oppTot['sf'] }}</td>
                            <td class="py-2.5 px-3">{{ $oppTot['ibb'] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- EXPANDED PITCHING --}}
        @if($mlbPitching->isNotEmpty())
        <div>
            <h2 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-3">Expanded Pitching</h2>
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead>
                        <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="py-2.5 px-3 text-left">Season</th>
                            <th class="py-2.5 px-3 text-left">Team</th>
                            <th class="py-2.5 px-3">P/S</th>
                            <th class="py-2.5 px-3">P/I</th>
                            <th class="py-2.5 px-3">K/9</th>
                            <th class="py-2.5 px-3">QS</th>
                            <th class="py-2.5 px-3">CG</th>
                            <th class="py-2.5 px-3">SHO</th>
                            <th class="py-2.5 px-3">GB%</th>
                            <th class="py-2.5 px-3">GB</th>
                            <th class="py-2.5 px-3">FB</th>
                            <th class="py-2.5 px-3">G/F</th>
                            <th class="py-2.5 px-3">IR</th>
                            <th class="py-2.5 px-3">IRS</th>
                            <th class="py-2.5 px-3">WP</th>
                            <th class="py-2.5 px-3">BK</th>
                            <th class="py-2.5 px-3">SB</th>
                            <th class="py-2.5 px-3">CS</th>
                            <th class="py-2.5 px-3">RSUP</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($mlbPitching as $row)
                        @php
                            $eIp  = (int)$row->outs / 3;
                            $ePi  = (int)($row->opp_pitches ?? 0);
                            $eGs  = (int)$row->gs;
                            $eGb  = (int)($row->gb ?? 0);
                            $eFb  = (int)($row->fb ?? 0);
                            $ePs  = ($eGs > 0 && $ePi > 0) ? number_format($ePi / $eGs, 1) : '—';
                            $ePi2 = ($eIp > 0 && $ePi > 0) ? number_format($ePi / $eIp, 1) : '—';
                            $eK9  = $eIp > 0 ? number_format(((int)$row->k / $eIp) * 9, 1) : '—';
                            $eGbPct = ($eGb + $eFb) > 0 ? number_format($eGb / ($eGb + $eFb) * 100, 1) : '—';
                            $eGf  = $eFb > 0 ? number_format($eGb / $eFb, 1) : '—';
                            $eRsup = $eGs > 0 ? number_format((int)($row->rs ?? 0) / $eGs, 1) : '—';
                        @endphp
                        <tr class="border-t border-gray-800/50 hover:bg-gray-800/30 transition">
                            <td class="py-2 px-3 font-semibold text-white">{{ $row->year }}</td>
                            <td class="py-2 px-3 text-left text-gray-400">{{ $row->team_abbr ?: '—' }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $ePs }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $ePi2 }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $eK9 }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)($row->qs ?? 0) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)($row->cg ?? 0) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)($row->sho ?? 0) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $eGbPct }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $eGb }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $eFb }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $eGf }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)($row->ir ?? 0) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)($row->irs ?? 0) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)($row->wp ?? 0) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)($row->bk ?? 0) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)($row->opp_sb ?? 0) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)($row->opp_cs ?? 0) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $eRsup }}</td>
                        </tr>
                        @endforeach
                        {{-- Career totals --}}
                        @php
                            $tOuts = $mlbPitching->sum(fn($r) => (int)$r->outs);
                            $tIp   = $tOuts / 3;
                            $tPi   = $mlbPitching->sum(fn($r) => (int)($r->opp_pitches ?? 0));
                            $tGs   = $mlbPitching->sum(fn($r) => (int)$r->gs);
                            $tGb   = $mlbPitching->sum(fn($r) => (int)($r->gb ?? 0));
                            $tFb   = $mlbPitching->sum(fn($r) => (int)($r->fb ?? 0));
                            $tK    = $mlbPitching->sum(fn($r) => (int)$r->k);
                            $tRs   = $mlbPitching->sum(fn($r) => (int)($r->rs ?? 0));
                        @endphp
                        <tr class="border-t-2 border-gray-700 bg-gray-800/30 font-bold text-white">
                            <td class="py-2.5 px-3 text-left text-xs text-gray-400 uppercase tracking-wider" colspan="2">Career</td>
                            <td class="py-2.5 px-3">{{ $tGs > 0 ? number_format($tPi/$tGs, 1) : '—' }}</td>
                            <td class="py-2.5 px-3">{{ $tIp > 0 ? number_format($tPi/$tIp, 1) : '—' }}</td>
                            <td class="py-2.5 px-3">{{ $tIp > 0 ? number_format(($tK/$tIp)*9, 1) : '—' }}</td>
                            <td class="py-2.5 px-3">{{ $mlbPitching->sum(fn($r) => (int)($r->qs ?? 0)) }}</td>
                            <td class="py-2.5 px-3">{{ $mlbPitching->sum(fn($r) => (int)($r->cg ?? 0)) }}</td>
                            <td class="py-2.5 px-3">{{ $mlbPitching->sum(fn($r) => (int)($r->sho ?? 0)) }}</td>
                            <td class="py-2.5 px-3">{{ ($tGb+$tFb) > 0 ? number_format($tGb/($tGb+$tFb)*100, 1) : '—' }}</td>
                            <td class="py-2.5 px-3">{{ $tGb }}</td>
                            <td class="py-2.5 px-3">{{ $tFb }}</td>
                            <td class="py-2.5 px-3">{{ $tFb > 0 ? number_format($tGb/$tFb, 1) : '—' }}</td>
                            <td class="py-2.5 px-3">{{ $mlbPitching->sum(fn($r) => (int)($r->ir ?? 0)) }}</td>
                            <td class="py-2.5 px-3">{{ $mlbPitching->sum(fn($r) => (int)($r->irs ?? 0)) }}</td>
                            <td class="py-2.5 px-3">{{ $mlbPitching->sum(fn($r) => (int)($r->wp ?? 0)) }}</td>
                            <td class="py-2.5 px-3">{{ $mlbPitching->sum(fn($r) => (int)($r->bk ?? 0)) }}</td>
                            <td class="py-2.5 px-3">{{ $mlbPitching->sum(fn($r) => (int)($r->opp_sb ?? 0)) }}</td>
                            <td class="py-2.5 px-3">{{ $mlbPitching->sum(fn($r) => (int)($r->opp_cs ?? 0)) }}</td>
                            <td class="py-2.5 px-3">{{ $tGs > 0 ? number_format($tRs/$tGs, 1) : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        @endif

    </section>
    @endif

    {{-- GLOSSARY --}}
    @if($mlbPitching->isNotEmpty())
    <section>
        <h2 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-3">Glossary</h2>
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-1.5 text-sm">
                @foreach([
                    '2B' => 'Doubles',
                    '3B' => 'Triples',
                    'BB' => 'Walks',
                    'BK' => 'Balks',
                    'BLSV' => 'Blown Saves',
                    'CG' => 'Complete Games',
                    'CS' => 'Caught Stealing',
                    'ER' => 'Earned Runs',
                    'ERA' => 'Earned Run Average',
                    'FB' => 'Fly Balls',
                    'G/F' => 'Ground To Fly Ball Ratio',
                    'GB' => 'Ground Balls',
                    'GB%' => 'Ground Ball Percentage',
                    'GP' => 'Games Played',
                    'GS' => 'Games Started',
                    'H' => 'Hits',
                    'HLD' => 'Holds',
                    'HR' => 'Home Runs',
                    'IBB' => 'Intentional Walks',
                    'IP' => 'Innings Pitched',
                    'IR' => 'Inherited Runners',
                    'IRS' => 'Inherited Runners Scored',
                    'K' => 'Strikeouts',
                    'K/9' => 'Strikeouts Per 9 Innings',
                    'K/BB' => 'Strikeout To Walk Ratio',
                    'L' => 'Losses',
                    'OBA' => 'Opponent Batting Average',
                    'OOBP' => "Opponent's On-Base Pct",
                    'OOPS' => "Opponent's OBP + SLG Pct",
                    'OSLUG' => "Opponent's Slugging Pct",
                    'P' => 'Pitches',
                    'P-TBF' => 'Pitches Per Batter Faced',
                    'P/I' => 'Pitches Per Inning',
                    'P/S' => 'Pitches Per Start',
                    'QS' => 'Quality Starts',
                    'R' => 'Runs',
                    'RSUP' => 'Run Support',
                    'SB' => 'Stolen Bases',
                    'SF' => 'Sacrifice Flies',
                    'SH' => 'Sacrifice Bunts',
                    'SHO' => 'Shutouts',
                    'SV' => 'Saves',
                    'TB' => 'Total Bases',
                    'TBF' => 'Batters Faced',
                    'W' => 'Wins',
                    'W%' => 'Win Percentage',
                    'WAR' => 'Wins Above Replacement',
                    'WHIP' => 'Walks Plus Hits Per Inning Pitched',
                    'WP' => 'Wild Pitches',
                ] as $abbr => $desc)
                <div>
                    <span class="text-white font-bold">{{ $abbr }}:</span>
                    <span class="text-gray-400">{{ $desc }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif
    @endif {{-- end pitching --}}

    @if($statType === 'fielding')
    {{-- FIELDING STATS --}}
    <section class="space-y-6">
        @php
            $fmtFp = function($v) { return $v > 0 ? number_format($v, 3) : '.000'; };
        @endphp

        @if($mlbFielding->isNotEmpty())
        <div>
            <h2 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-3">Fielding — {{ $levelName }}</h2>
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead>
                        <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="py-2.5 px-3 text-left">Season</th>
                            <th class="py-2.5 px-3 text-left">Team</th>
                            <th class="py-2.5 px-3">Pos</th>
                            <th class="py-2.5 px-3">GP</th><th class="py-2.5 px-3">GS</th>
                            <th class="py-2.5 px-3">FIP</th>
                            <th class="py-2.5 px-3">TC</th><th class="py-2.5 px-3">PO</th>
                            <th class="py-2.5 px-3">A</th><th class="py-2.5 px-3">E</th>
                            <th class="py-2.5 px-3">DP</th>
                            <th class="py-2.5 px-3">FP</th><th class="py-2.5 px-3">RF</th>
                            <th class="py-2.5 px-3">PB</th>
                            <th class="py-2.5 px-3">SBA</th><th class="py-2.5 px-3">CS</th>
                            <th class="py-2.5 px-3">CS%</th>
                            <th class="py-2.5 px-3">DWAR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($mlbFielding as $row)
                        @php
                            $isCatcher = (int)$row->position === 2;
                            $isDH = (int)$row->position === 10;
                        @endphp
                        <tr class="border-t border-gray-800/50 hover:bg-gray-800/30 transition">
                            <td class="py-2 px-3 font-semibold text-white">{{ $row->year }}</td>
                            <td class="py-2 px-3 text-left text-gray-400">{{ $row->team_abbr ?: '—' }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $row->pos_label }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->g }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->gs }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $isDH ? '.0' : $row->fip_display }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->tc }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->po }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->a }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->e }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->dp }}</td>
                            <td class="py-2 px-3 text-white font-semibold">{{ $isDH ? '.000' : $fmtFp($row->fp) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $isDH ? '.00' : number_format($row->rf, 2) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $isCatcher ? (int)$row->pb : '—' }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $isCatcher ? (int)$row->sba : '—' }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $isCatcher ? (int)$row->rto : '—' }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $isCatcher && (int)$row->sba > 0 ? number_format($row->cs_pct, 3) : '—' }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ number_format($row->dwar, 1) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @else
        <div class="text-center py-16 text-gray-500">
            <p class="text-lg">No fielding stats available.</p>
        </div>
        @endif

        {{-- FIELDING GLOSSARY --}}
        @if($careerFielding->isNotEmpty())
        <section>
            <h2 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-3">Glossary</h2>
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-5">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-1.5 text-sm">
                    @foreach([
                        'A' => 'Assists',
                        'CS' => 'Caught Stealing',
                        'CS%' => 'Caught Stealing Percentage',
                        'DP' => 'Double Plays',
                        'DWAR' => 'Defensive Wins Above Replacement',
                        'E' => 'Errors',
                        'FIP' => 'Full Innings Played',
                        'FP' => 'Fielding Percentage',
                        'GP' => 'Games Played',
                        'GS' => 'Games Started',
                        'PB' => 'Passed Balls',
                        'PO' => 'Putouts',
                        'Pos' => 'Position',
                        'RF' => 'Range Factor',
                        'SBA' => 'Stolen Bases Allowed',
                        'TC' => 'Total Chances',
                    ] as $abbr => $desc)
                    <div>
                        <span class="text-white font-bold">{{ $abbr }}:</span>
                        <span class="text-gray-400">{{ $desc }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </section>
        @endif

    </section>
    @endif

</div>
@endsection
