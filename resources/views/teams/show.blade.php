@extends('layouts.public')
@section('title', ($team->name ?? 'Team') . ' ' . ($team->nickname ?? ''))

@section('content')

{{-- Team Header --}}
<div class="border-b border-gray-800 bg-gray-900/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex items-center gap-5">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-3xl font-extrabold tracking-tight">
                        {{ $team->name }} {{ $team->nickname ?? '' }}
                    </h1>
                    @if($record)
                        <span class="text-lg font-semibold text-gray-400">{{ $record->w }}-{{ $record->l }}</span>
                    @endif
                </div>
                <div class="flex items-center gap-4 mt-1 text-sm text-gray-500 flex-wrap">
                    @if($team->sub_league_abbr ?? null)
                        <span>{{ $team->sub_league_abbr }} &bull; {{ $team->division_name ?? '' }}</span>
                    @endif
                    @if($team->park_name ?? null)
                        <span>{{ $team->park_name }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@include('teams._nav')

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="grid grid-cols-1 lg:grid-cols-[200px_1fr_260px] gap-5">

        {{-- ═══ LEFT SIDEBAR ═══ --}}
        <div class="space-y-4">
            @include('teams._sidebar')
        </div>

        {{-- ═══ MAIN CONTENT ═══ --}}
        <div class="space-y-5 min-w-0">

            {{-- TEAM LEADERS — 6-category grid --}}
            @if(!empty($leaders))
            @php
            $leaderCats = [
                ['key' => 'avg', 'label' => 'AVG'],
                ['key' => 'hr',  'label' => 'HR'],
                ['key' => 'rbi', 'label' => 'RBI'],
                ['key' => 'w',   'label' => 'W'],
                ['key' => 'era', 'label' => 'ERA'],
                ['key' => 'k',   'label' => 'K'],
            ];
            @endphp
            <div class="grid grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($leaderCats as $cat)
                @if(!empty($leaders[$cat['key']]))
                <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                    <div class="px-3 py-2 border-b border-gray-800">
                        <h3 class="font-bold text-xs uppercase tracking-wider text-gray-400">Team Leaders {{ $cat['label'] }}</h3>
                    </div>
                    <div class="px-3 py-2 space-y-1">
                        @php $teamLeaderVal = $leaders[$cat['key']][0]['val'] ?? null; @endphp
                        @foreach($leaders[$cat['key']] as $ldr)
                        @php $isTeamLeader = $ldr['val'] === $teamLeaderVal; @endphp
                        <div class="flex items-center justify-between">
                            <a href="{{ route('player', $ldr['player_id']) }}"
                               class="text-xs hover:text-red-400 transition font-medium truncate {{ $isTeamLeader ? 'text-yellow-400' : 'text-white' }}">
                                {{ $ldr['name'] }}
                            </a>
                            <span class="text-xs font-bold ml-2 shrink-0 {{ $isTeamLeader ? 'text-yellow-400' : 'text-gray-300' }}">{{ $ldr['val'] }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
                @endforeach
            </div>
            @endif

            {{-- PITCHING STAFF — three sections --}}
            @php
            $pitcherSections = [
                ['key' => 'starters',  'label' => 'Starting Rotation'],
                ['key' => 'relievers', 'label' => 'Relievers'],
                ['key' => 'closers',   'label' => 'Closers'],
            ];
            @endphp

            @foreach($pitcherSections as $pSection)
            @if(!empty($groups[$pSection['key']]))
            @php $tblId = 'tbl-' . $pSection['key']; @endphp
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-800">
                    <h2 class="font-bold text-sm uppercase tracking-wider">{{ $pSection['label'] }}</h2>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full text-xs sortable-table" id="{{ $tblId }}">
                    <thead>
                        <tr class="text-gray-500 uppercase tracking-wider border-b border-gray-800/60">
                            <th class="text-center py-2 px-1 font-medium w-6">T</th>
                            <th class="text-left py-2 px-2 font-medium sortable-col" data-sort="name" data-type="str">Pitcher <span class="si">↕</span></th>
                            <th class="text-center py-2 px-1.5 font-medium sortable-col" data-sort="g" data-type="num">G <span class="si">↕</span></th>
                            <th class="text-center py-2 px-1.5 font-medium sortable-col" data-sort="gs" data-type="num">GS <span class="si">↕</span></th>
                            <th class="text-center py-2 px-1.5 font-medium sortable-col" data-sort="w" data-type="num">W <span class="si">↕</span></th>
                            <th class="text-center py-2 px-1.5 font-medium sortable-col" data-sort="l" data-type="num">L <span class="si">↕</span></th>
                            <th class="text-center py-2 px-1.5 font-medium sortable-col" data-sort="sv" data-type="num">SV <span class="si">↕</span></th>
                            <th class="text-center py-2 px-2 font-medium sortable-col" data-sort="era" data-type="num">ERA <span class="si">↕</span></th>
                            <th class="text-center py-2 px-2 font-medium sortable-col" data-sort="whip" data-type="num">WHIP <span class="si">↕</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40">
                        @foreach($groups[$pSection['key']] as $p)
                        @php
                            $s    = $pitcherStats[$p->player_id] ?? null;
                            $thr  = match((int)($p->throws ?? 0)) { 1=>'R', 2=>'L', default=>'-' };
                            $eraNum  = $s ? (float)$s->era : 0;
                            $whipNum = $s ? (float)$s->whip : 0;
                        @endphp
                        <tr class="hover:bg-gray-800/30 transition" @if($s) data-row="1" @else data-nostats="1" @endif>
                            <td class="text-center py-2 px-1 text-gray-500">{{ $thr }}</td>
                            <td class="py-2 px-2 whitespace-nowrap font-medium" data-sort="name" data-raw="{{ $p->last_name }}">
                                <a href="{{ route('player', $p->player_id) }}" class="text-white hover:text-red-400 transition">
                                    {{ $p->first_name[0] }}. {{ $p->last_name }}
                                </a>
                            </td>
                            @if($s)
                            @php
                                $mlbW    = (int)$s->w > 0 && (int)$s->w >= ($mlbLeaders['w'] ?? 0);
                                $mlbSv   = (int)$s->sv > 0 && (int)$s->sv >= ($mlbLeaders['sv'] ?? 0);
                                $mlbPk   = (int)$s->k > 0 && (int)$s->k >= ($mlbLeaders['k'] ?? 0);
                                $mlbEra  = is_numeric($s->era) && (int)$s->outs >= 45 && (float)$s->era <= ($mlbLeaders['era'] ?? 0) + 0.005;
                                $mlbWhip = is_numeric($s->whip) && (int)$s->outs >= 45 && (float)$s->whip <= ($mlbLeaders['whip'] ?? 0) + 0.005;
                            @endphp
                            <td class="text-center py-2 px-1.5 text-gray-400" data-sort="g" data-raw="{{ $s->g }}">{{ $s->g }}</td>
                            <td class="text-center py-2 px-1.5 text-gray-400" data-sort="gs" data-raw="{{ $s->gs }}">{{ $s->gs }}</td>
                            <td class="text-center py-2 px-1.5 {{ $mlbW ? 'text-yellow-400 font-bold' : 'text-white font-medium' }}" data-sort="w" data-raw="{{ $s->w }}">{{ $s->w }}</td>
                            <td class="text-center py-2 px-1.5 text-gray-400" data-sort="l" data-raw="{{ $s->l }}">{{ $s->l }}</td>
                            <td class="text-center py-2 px-1.5 {{ $mlbSv ? 'text-yellow-400 font-bold' : 'text-gray-400' }}"
                                data-sort="sv" data-raw="{{ $s->sv }}">{{ $s->sv }}</td>
                            <td class="text-center py-2 px-2 font-mono {{ $mlbEra ? 'text-yellow-400 font-bold' : 'text-gray-300' }}"
                                data-sort="era" data-raw="{{ is_numeric($s->era) ? $s->era : 99 }}">{{ $s->era }}</td>
                            <td class="text-center py-2 px-2 font-mono {{ $mlbWhip ? 'text-yellow-400 font-bold' : 'text-gray-300' }}"
                                data-sort="whip" data-raw="{{ is_numeric($s->whip) ? $s->whip : 99 }}">{{ $s->whip }}</td>
                            @else
                            <td colspan="7" class="text-center py-2 text-gray-700">—</td>
                            @endif
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>
            @endif
            @endforeach

            {{-- POSITION PLAYER TABLES --}}
            @php
            $batterSections = [
                ['key' => 'catchers',  'label' => 'Catchers'],
                ['key' => 'infield',   'label' => 'Infielders'],
                ['key' => 'outfield',  'label' => 'Outfielders'],
                ['key' => 'dh',        'label' => 'Designated Hitters'],
            ];
            @endphp

            @foreach($batterSections as $section)
            @if(!empty($groups[$section['key']]))
            @php $tblId = 'tbl-' . $section['key']; @endphp
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-800">
                    <h2 class="font-bold text-sm uppercase tracking-wider">{{ $section['label'] }}</h2>
                </div>
                <div class="overflow-x-auto">
                <table class="w-full text-xs sortable-table" id="{{ $tblId }}">
                    <thead>
                        <tr class="text-gray-500 uppercase tracking-wider border-b border-gray-800/60">
                            <th class="text-center py-2 px-1 font-medium w-6">#</th>
                            <th class="text-center py-2 px-1 font-medium w-6">B</th>
                            <th class="text-left py-2 px-2 font-medium sortable-col" data-sort="name" data-type="str">Player <span class="si">↕</span></th>
                            <th class="text-center py-2 px-1 font-medium w-8">Pos</th>
                            <th class="text-center py-2 px-1.5 font-medium sortable-col" data-sort="g" data-type="num">G <span class="si">↕</span></th>
                            <th class="text-center py-2 px-1.5 font-medium sortable-col" data-sort="ab" data-type="num">AB <span class="si">↕</span></th>
                            <th class="text-center py-2 px-1.5 font-medium sortable-col" data-sort="h" data-type="num">H <span class="si">↕</span></th>
                            <th class="text-center py-2 px-1.5 font-medium sortable-col" data-sort="hr" data-type="num">HR <span class="si">↕</span></th>
                            <th class="text-center py-2 px-1.5 font-medium sortable-col" data-sort="rbi" data-type="num">RBI <span class="si">↕</span></th>
                            <th class="text-center py-2 px-1.5 font-medium sortable-col" data-sort="r" data-type="num">R <span class="si">↕</span></th>
                            <th class="text-center py-2 px-1.5 font-medium sortable-col" data-sort="bb" data-type="num">BB <span class="si">↕</span></th>
                            <th class="text-center py-2 px-1.5 font-medium sortable-col" data-sort="k" data-type="num">K <span class="si">↕</span></th>
                            <th class="text-center py-2 px-2 font-medium sortable-col" data-sort="avg" data-type="num">AVG <span class="si">↕</span></th>
                            <th class="text-center py-2 px-2 font-medium sortable-col" data-sort="obp" data-type="num">OBP <span class="si">↕</span></th>
                            <th class="text-center py-2 px-2 font-medium sortable-col" data-sort="slg" data-type="num">SLG <span class="si">↕</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40">
                        @foreach($groups[$section['key']] as $p)
                        @php
                            $s   = $batterStats[$p->player_id] ?? null;
                            $pos = \App\Services\OotpService::POSITIONS[$p->position] ?? '?';
                            $bat = match((int)($p->bats ?? 0)) { 1=>'R', 2=>'L', 3=>'S', default=>'-' };
                            $uni = (int)($p->uniform_number ?? 0);
                        @endphp
                        <tr class="hover:bg-gray-800/30 transition" @if($s) data-row="1" @else data-nostats="1" @endif>
                            <td class="text-center py-2 px-1 text-gray-600">{{ $uni ?: '' }}</td>
                            <td class="text-center py-2 px-1 text-gray-500">{{ $bat }}</td>
                            <td class="py-2 px-2 whitespace-nowrap" data-sort="name" data-raw="{{ $p->last_name }}">
                                <a href="{{ route('player', $p->player_id) }}" class="text-white hover:text-red-400 transition font-medium">
                                    {{ $p->first_name[0] }}. {{ $p->last_name }}
                                </a>
                            </td>
                            <td class="text-center py-2 px-1 text-gray-500">{{ $pos }}</td>
                            @if($s)
                            @php
                                $mlbHr  = (int)$s->hr > 0 && (int)$s->hr >= ($mlbLeaders['hr'] ?? 0);
                                $mlbRbi = (int)$s->rbi > 0 && (int)$s->rbi >= ($mlbLeaders['rbi'] ?? 0);
                                $mlbH   = (int)$s->h > 0 && (int)$s->h >= ($mlbLeaders['h'] ?? 0);
                                $mlbSb  = (int)$s->sb > 0 && (int)$s->sb >= ($mlbLeaders['sb'] ?? 0);
                                $mlbAvg = (int)$s->ab >= 100 && abs($s->avg - ($mlbLeaders['avg'] ?? 0)) < 0.001;
                            @endphp
                            <td class="text-center py-2 px-1.5 text-gray-400" data-sort="g" data-raw="{{ $s->g }}">{{ $s->g }}</td>
                            <td class="text-center py-2 px-1.5 text-gray-400" data-sort="ab" data-raw="{{ $s->ab }}">{{ $s->ab }}</td>
                            <td class="text-center py-2 px-1.5 {{ $mlbH ? 'text-yellow-400 font-bold' : 'text-white font-medium' }}" data-sort="h" data-raw="{{ $s->h }}">{{ $s->h }}</td>
                            <td class="text-center py-2 px-1.5 {{ $mlbHr ? 'text-yellow-400 font-bold' : 'text-gray-400' }}"
                                data-sort="hr" data-raw="{{ $s->hr }}">{{ $s->hr }}</td>
                            <td class="text-center py-2 px-1.5 {{ $mlbRbi ? 'text-yellow-400 font-bold' : 'text-gray-400' }}"
                                data-sort="rbi" data-raw="{{ $s->rbi }}">{{ $s->rbi }}</td>
                            <td class="text-center py-2 px-1.5 text-gray-400" data-sort="r" data-raw="{{ $s->r }}">{{ $s->r }}</td>
                            <td class="text-center py-2 px-1.5 text-gray-400" data-sort="bb" data-raw="{{ $s->bb }}">{{ $s->bb }}</td>
                            <td class="text-center py-2 px-1.5 text-gray-400" data-sort="k" data-raw="{{ $s->k }}">{{ $s->k }}</td>
                            @php
                                $avgFmt = $s->avg >= 1 ? '1.000' : '.'.str_pad((string)round($s->avg*1000), 3, '0', STR_PAD_LEFT);
                                $obpFmt = $s->obp >= 1 ? '1.000' : '.'.str_pad((string)round($s->obp*1000), 3, '0', STR_PAD_LEFT);
                                $slgFmt = $s->slg >= 1 ? '1.000' : '.'.str_pad((string)round($s->slg*1000), 3, '0', STR_PAD_LEFT);
                            @endphp
                            <td class="text-center py-2 px-2 font-mono {{ $mlbAvg ? 'text-yellow-400 font-bold' : 'text-white' }}" data-sort="avg" data-raw="{{ number_format($s->avg, 4) }}">{{ $avgFmt }}</td>
                            <td class="text-center py-2 px-2 text-gray-300 font-mono" data-sort="obp" data-raw="{{ number_format($s->obp, 4) }}">{{ $obpFmt }}</td>
                            <td class="text-center py-2 px-2 text-gray-300 font-mono" data-sort="slg" data-raw="{{ number_format($s->slg, 4) }}">{{ $slgFmt }}</td>
                            @else
                            <td colspan="11" class="text-center py-2 text-gray-700">—</td>
                            @endif
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>
            @endif
            @endforeach

            {{-- WHO'S HOT / WHO'S NOT --}}
            @if($hotBatters->isNotEmpty() || $hotPitchers->isNotEmpty() || $coldBatters->isNotEmpty() || $coldPitchers->isNotEmpty())
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Hot --}}
                <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                    <div class="px-4 py-2.5 border-b border-gray-800">
                        <h2 class="font-bold text-sm text-green-400 uppercase tracking-wider">Who's Hot?</h2>
                    </div>
                    <div class="p-3 space-y-2">
                        @foreach($hotBatters as $hb)
                        <div class="flex items-center justify-between">
                            <div class="min-w-0">
                                <a href="{{ route('player', $hb->player_id) }}" class="text-sm text-white hover:text-red-400 font-medium">
                                    {{ \App\Services\OotpService::POSITIONS[$hb->position] ?? '' }}
                                    {{ $hb->first_name[0] }}. {{ $hb->last_name }}
                                </a>
                            </div>
                            <span class="text-xs text-green-400 font-bold ml-2 shrink-0">
                                {{ $hb->avg !== null ? ltrim(number_format($hb->avg, 3), '0') : '' }}{{ (int)$hb->hr > 0 ? ', '.$hb->hr.' HR' : ', '.$hb->ab.' AB' }}{{ (int)$hb->rbi > 0 ? ', '.$hb->rbi.' RBI' : '' }}
                            </span>
                        </div>
                        @endforeach
                        @foreach($hotPitchers as $hp)
                        <div class="flex items-center justify-between">
                            <div class="min-w-0">
                                <a href="{{ route('player', $hp->player_id) }}" class="text-sm text-white hover:text-red-400 font-medium">
                                    P {{ $hp->first_name[0] }}. {{ $hp->last_name }}
                                </a>
                            </div>
                            <span class="text-xs text-green-400 font-bold ml-2 shrink-0">
                                @php $hpIp = floor((int)$hp->outs / 3) . '.' . ((int)$hp->outs % 3); @endphp
                                {{ $hp->w }}-{{ $hp->l }}, {{ $hp->era }}, {{ $hpIp }} IP
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- Cold --}}
                <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                    <div class="px-4 py-2.5 border-b border-gray-800">
                        <h2 class="font-bold text-sm text-red-400 uppercase tracking-wider">Who's Not?</h2>
                    </div>
                    <div class="p-3 space-y-2">
                        @foreach($coldBatters as $cb)
                        <div class="flex items-center justify-between">
                            <div class="min-w-0">
                                <a href="{{ route('player', $cb->player_id) }}" class="text-sm text-white hover:text-red-400 font-medium">
                                    {{ \App\Services\OotpService::POSITIONS[$cb->position] ?? '' }}
                                    {{ $cb->first_name[0] }}. {{ $cb->last_name }}
                                </a>
                            </div>
                            <span class="text-xs text-red-400 font-bold ml-2 shrink-0">
                                {{ $cb->avg !== null ? ltrim(number_format($cb->avg, 3), '0') : '' }}{{ (int)$cb->hr > 0 ? ', '.$cb->hr.' HR' : ', '.$cb->ab.' AB' }}{{ (int)$cb->rbi > 0 ? ', '.$cb->rbi.' RBI' : '' }}
                            </span>
                        </div>
                        @endforeach
                        @foreach($coldPitchers as $cp)
                        <div class="flex items-center justify-between">
                            <div class="min-w-0">
                                <a href="{{ route('player', $cp->player_id) }}" class="text-sm text-white hover:text-red-400 font-medium">
                                    P {{ $cp->first_name[0] }}. {{ $cp->last_name }}
                                </a>
                            </div>
                            <span class="text-xs text-red-400 font-bold ml-2 shrink-0">
                                @php $cpIp = floor((int)$cp->outs / 3) . '.' . ((int)$cp->outs % 3); @endphp
                                {{ $cp->w }}-{{ $cp->l }}, {{ $cp->era }}, {{ $cpIp }} IP
                            </span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

        </div>

        {{-- ═══ RIGHT SIDEBAR ═══ --}}
        <div class="space-y-4">

            {{-- Recent Results --}}
            @if($recentGames->isNotEmpty())
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <div class="px-4 py-2.5 border-b border-gray-800 flex items-center justify-between">
                    <h2 class="font-bold text-sm uppercase tracking-wider text-gray-400">Recent</h2>
                    <a href="{{ route('schedule') }}?team={{ $team->team_id }}" class="text-[10px] text-red-400 hover:text-red-300">Schedule →</a>
                </div>
                <div class="flex flex-wrap gap-1.5 p-2.5">
                    @foreach($recentGames->reverse() as $rg)
                    @php
                        $isHome  = (int)$rg->home_team === $team->team_id;
                        $myRuns  = $isHome ? (int)$rg->runs1 : (int)$rg->runs0;
                        $oppRuns = $isHome ? (int)$rg->runs0 : (int)$rg->runs1;
                        $won     = $myRuns > $oppRuns;
                        $oppAbbr = $isHome ? $rg->away_abbr : $rg->home_abbr;
                        $prefix  = $isHome ? 'vs' : '@';
                    @endphp
                    <div class="text-center px-1.5 py-1 rounded border text-[10px] leading-tight
                        {{ $won ? 'border-green-800/40 bg-green-900/10' : 'border-red-800/40 bg-red-900/10' }}">
                        <p class="text-gray-500">{{ $prefix }} {{ $oppAbbr }}</p>
                        <p class="font-bold {{ $won ? 'text-green-400' : 'text-red-400' }}">
                            {{ $won ? 'W' : 'L' }} {{ $myRuns }}-{{ $oppRuns }}
                        </p>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Division Standings --}}
            @if($divisionStandings)
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <div class="px-4 py-2.5 border-b border-gray-800">
                    <h2 class="font-bold text-sm uppercase tracking-wider text-gray-400">{{ $divisionStandings['division_name'] }}</h2>
                </div>
                <table class="w-full text-xs table-fixed">
                    <thead>
                        <tr class="text-gray-600 border-b border-gray-800/60">
                            <th class="text-left py-1.5 px-3 font-medium"></th>
                            <th class="text-right py-1.5 pl-0 pr-1.5 font-medium w-11">W-L</th>
                            <th class="text-right py-1.5 px-1 font-medium w-9">PCT</th>
                            <th class="text-right py-1.5 pl-0 pr-2 font-medium w-7">GB</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40">
                        @foreach($divisionStandings['teams'] as $st)
                        @php $isMe = (int)$st->team_id === $team->team_id; @endphp
                        <tr class="{{ $isMe ? 'bg-gray-800/40' : '' }}">
                            <td class="py-1.5 px-3 {{ $isMe ? 'text-white font-bold' : 'text-gray-300' }} truncate overflow-hidden">
                                {{ $st->name }} {{ $st->nickname ?? '' }}
                            </td>
                            <td class="text-right py-1.5 pl-0 pr-1.5 text-gray-400 whitespace-nowrap">{{ $st->w }}-{{ $st->l }}</td>
                            <td class="text-right py-1.5 px-1 text-gray-300 font-mono">{{ $st->pct }}</td>
                            <td class="text-right py-1.5 pl-0 pr-2 text-gray-500">{{ $st->gb }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- Team Information --}}
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <div class="px-4 py-2.5 border-b border-gray-800">
                    <h2 class="font-bold text-sm uppercase tracking-wider text-gray-400">Team Information</h2>
                </div>
                <div class="p-3 space-y-1 text-xs">
                    @php
                    $pctFmt = fn($w, $l) => ($w + $l) > 0 ? ltrim(number_format($w / ($w + $l), 3), '0') . ' PCT' : '';
                    $recFmt = fn($w, $l) => $w . '-' . $l . ', ' . $pctFmt($w, $l);
                    @endphp
                    @if($record)
                    <div class="flex justify-between"><span class="text-gray-500">Record overall</span><span class="text-gray-300 font-mono text-right">{{ $recFmt($record->w, $record->l) }}</span></div>
                    @endif
                    @if($divisionStandings)
                    @php
                        $myStanding = $divisionStandings['teams']->firstWhere('team_id', $team->team_id);
                        $myPos = $myStanding ? $divisionStandings['teams']->search(fn($t) => $t->team_id === $team->team_id) + 1 : null;
                        $ordinal = fn($n) => $n . match($n % 10) { 1 => $n % 100 === 11 ? 'th' : 'st', 2 => $n % 100 === 12 ? 'th' : 'nd', 3 => $n % 100 === 13 ? 'th' : 'rd', default => 'th' };
                    @endphp
                    @if($myPos)
                    <div class="flex justify-between"><span class="text-gray-500">Position in Division</span><span class="text-gray-300 font-mono">{{ $ordinal($myPos) }}, {{ $myStanding->gb }} GB</span></div>
                    @endif
                    @endif
                    @if($homeAway)
                    <div class="flex justify-between"><span class="text-gray-500">Record at home</span><span class="text-gray-300 font-mono">{{ $recFmt($homeAway['home_w'], $homeAway['home_l']) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Record on the road</span><span class="text-gray-300 font-mono">{{ $recFmt($homeAway['road_w'], $homeAway['road_l']) }}</span></div>
                    @endif
                    @if(!empty($extendedRecords))
                    <div class="flex justify-between"><span class="text-gray-500">Record in X-innings</span><span class="text-gray-300 font-mono">{{ $recFmt($extendedRecords['xi']['w'], $extendedRecords['xi']['l']) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Record in one-run</span><span class="text-gray-300 font-mono">{{ $recFmt($extendedRecords['oner']['w'], $extendedRecords['oner']['l']) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Record versus LHP</span><span class="text-gray-300 font-mono">{{ $recFmt($extendedRecords['lhp']['w'], $extendedRecords['lhp']['l']) }}</span></div>
                    <div class="flex justify-between"><span class="text-gray-500">Record versus RHP</span><span class="text-gray-300 font-mono">{{ $recFmt($extendedRecords['rhp']['w'], $extendedRecords['rhp']['l']) }}</span></div>
                    @endif
                    @if($last10Rec)
                    <div class="flex justify-between"><span class="text-gray-500">Record last 10 games</span><span class="text-gray-300 font-mono">{{ $recFmt($last10Rec['w'], $last10Rec['l']) }}</span></div>
                    @endif
                    @if(!empty($extendedRecords['months']))
                    @foreach($extendedRecords['months'] as $month => $mr)
                    <div class="flex justify-between"><span class="text-gray-500">Record in {{ $month }}</span><span class="text-gray-300 font-mono">{{ $recFmt($mr['w'], $mr['l']) }}</span></div>
                    @endforeach
                    @endif
                </div>
            </div>

            {{-- Farm System Rank --}}
            @if(!empty($farmRank))
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <div class="px-4 py-2.5 border-b border-gray-800">
                    <h2 class="font-bold text-sm uppercase tracking-wider text-gray-400">Farm System</h2>
                </div>
                <div class="p-3 space-y-1.5 text-xs">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Farm Rank</span>
                        <span class="text-white font-bold text-sm">#{{ $farmRank['rank'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Prospects</span>
                        <span class="text-gray-300">{{ $farmRank['prospect_count'] }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Elite Prospects</span>
                        <span class="text-gray-300">{{ $farmRank['elite_count'] }}</span>
                    </div>
                    @if($farmRank['top_prospect'])
                    <div class="flex justify-between">
                        <span class="text-gray-500">Top Prospect</span>
                        <span class="text-gray-300">{{ $farmRank['top_prospect'] }}</span>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            {{-- Batting Stats & Rankings --}}
            @if(!empty($battingRankings))
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <div class="px-4 py-2.5 border-b border-gray-800">
                    <h2 class="font-bold text-sm uppercase tracking-wider text-gray-400">Batting Stats & Rankings</h2>
                </div>
                <div class="p-3 space-y-1 text-xs">
                    @foreach($battingRankings as $stat)
                    <div class="flex justify-between">
                        <span class="text-gray-500">{{ $stat['label'] }}</span>
                        <span class="text-gray-300 font-mono">{{ $stat['val'] }} - {{ $stat['rank'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Pitching Stats & Rankings --}}
            @if(!empty($pitchingRankings))
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <div class="px-4 py-2.5 border-b border-gray-800">
                    <h2 class="font-bold text-sm uppercase tracking-wider text-gray-400">Pitching Stats & Rankings</h2>
                </div>
                <div class="p-3 space-y-1 text-xs">
                    @foreach($pitchingRankings as $stat)
                    <div class="flex justify-between">
                        <span class="text-gray-500">{{ $stat['label'] }}</span>
                        <span class="text-gray-300 font-mono">{{ $stat['val'] }} - {{ $stat['rank'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Upcoming --}}
            @if($upcoming->isNotEmpty())
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <div class="px-4 py-2.5 border-b border-gray-800">
                    <h2 class="font-bold text-sm uppercase tracking-wider text-gray-400">Upcoming Sim {{ DB::table('settings')->value('sim_length') ?? 7 }} Days</h2>
                </div>
                <div class="p-3 space-y-1.5">
                    @foreach($upcoming as $ug)
                    @php
                        $isHome = (int)$ug->home_team === $team->team_id;
                        $oppName = ($isHome ? $ug->away_name : $ug->home_name) . ' ' . ($isHome ? $ug->away_nickname : $ug->home_nickname);
                        $prefix  = $isHome ? 'vs' : '@';
                    @endphp
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-gray-400">{{ $prefix }} <span class="text-white font-medium">{{ $oppName }}</span></span>
                        <span class="text-gray-600">{{ \Carbon\Carbon::parse($ug->date)->format('M j') }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>

    </div>
</div>

@push('scripts')
<style>
.sortable-col { cursor: pointer; user-select: none; }
.sortable-col:hover { color: #fbbf24; }
.sortable-col[data-dir] .si { color: #fbbf24; opacity: 1; }
.si { font-size: 9px; opacity: 0.4; margin-left: 1px; }
</style>
<script>
document.querySelectorAll('.sortable-table').forEach(function(table) {
    table.querySelectorAll('th[data-sort]').forEach(function(th) {
        th.addEventListener('click', function() {
            var key  = th.dataset.sort;
            var type = th.dataset.type || 'num';
            var asc  = th.dataset.dir !== 'asc';
            table.querySelectorAll('th[data-sort]').forEach(function(h) {
                h.dataset.dir = '';
                var si = h.querySelector('.si');
                if (si) si.textContent = '↕';
            });
            th.dataset.dir = asc ? 'asc' : 'desc';
            var si = th.querySelector('.si');
            if (si) si.textContent = asc ? '↑' : '↓';
            var tbody  = table.querySelector('tbody');
            var rows   = Array.from(tbody.querySelectorAll('tr[data-row]'));
            var noStat = Array.from(tbody.querySelectorAll('tr[data-nostats]'));
            rows.sort(function(a, b) {
                var aCell = a.querySelector('td[data-sort="' + key + '"]');
                var bCell = b.querySelector('td[data-sort="' + key + '"]');
                var aVal  = aCell ? (aCell.dataset.raw || '') : '';
                var bVal  = bCell ? (bCell.dataset.raw || '') : '';
                var cmp   = type === 'num'
                    ? (parseFloat(aVal) || 0) - (parseFloat(bVal) || 0)
                    : aVal.localeCompare(bVal);
                return asc ? cmp : -cmp;
            });
            rows.forEach(function(r) { tbody.appendChild(r); });
            noStat.forEach(function(r) { tbody.appendChild(r); });
        });
    });
});
</script>
@endpush

@endsection
