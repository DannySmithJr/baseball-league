@extends('layouts.public')

@section('title', $game->away_abbr . ' @ ' . $game->home_abbr . ' — ' . \Carbon\Carbon::parse($game->date)->format('M j, Y'))

@section('content')
@php
    $played    = (bool) $game->played;
    $awayId    = (int)  $game->away_team;
    $homeId    = (int)  $game->home_team;
    $results          = \App\Services\OotpService::AT_BAT_RESULTS;
    $positions        = \App\Services\OotpService::POSITIONS;
    $fieldingPositions = \App\Services\OotpService::FIELDING_POSITIONS;
    $awayWon   = $played && (int)$game->runs0 > (int)$game->runs1;
    $homeWon   = $played && (int)$game->runs1 > (int)$game->runs0;

    $fmtIni = fn($first, $last) => ($first ? mb_substr($first, 0, 1).'.' : '') . ' ' . $last;

    // Batting totals row
    $batTotals = function(array $players): object {
        $t = (object)['ab'=>0,'r'=>0,'h'=>0,'d'=>0,'t'=>0,'hr'=>0,'rbi'=>0,'bb'=>0,'k'=>0,'sb'=>0,'hp'=>0];
        foreach ($players as $p) {
            foreach (['ab','r','h','d','t','hr','rbi','bb','k','sb','hp'] as $col) {
                $t->$col += (int)$p->$col;
            }
        }
        return $t;
    };

    // Pre-compute extra-base hit notes from at-bats
    // Correct result codes: 6=1B, 7=2B, 8=3B, 9=HR, 1=K, 2=BB, 4=GO, 5=FO, 10=HBP
    $HR = \App\Services\OotpService::RESULT_HR; // 9
    $xbhNotes = [$awayId => ['2b'=>[],'3b'=>[],'hr'=>[]], $homeId => ['2b'=>[],'3b'=>[],'hr'=>[]]];
    $abIndex = 0;
    foreach ($atBats as $half) {
        foreach ($half['atbats'] as $ab) {
            $tid  = (int)$ab->team_id;
            $res  = (int)$ab->result;
            if (!in_array($res, [7,8,9])) { $abIndex++; continue; }
            $runnersOn = ((int)$ab->base1 ? 1 : 0) + ((int)$ab->base2 ? 1 : 0) + ((int)$ab->base3 ? 1 : 0);
            $ctx = [
                'name'   => $fmtIni($ab->batter_first, $ab->batter_last),
                'inning' => (int)$ab->inning,
                'off'    => $ab->pitcher_last ? $fmtIni($ab->pitcher_first, $ab->pitcher_last) : null,
                'on'     => $runnersOn,
                'outs'   => (int)$ab->outs,
                'rbi'    => (int)$ab->rbi,
            ];
            if ($res === 7) $xbhNotes[$tid]['2b'][] = $ctx;
            elseif ($res === 8) $xbhNotes[$tid]['3b'][] = $ctx;
            elseif ($res === 9) $xbhNotes[$tid]['hr'][] = $ctx;
            $abIndex++;
        }
    }

    // GIDP per team from box batting
    $gidpNotes = [$awayId => [], $homeId => []];
    foreach ([$awayId, $homeId] as $tid) {
        foreach ($boxBatting[$tid] ?? [] as $b) {
            if ((int)$b->gdp > 0) {
                $gidpNotes[$tid][] = $fmtIni($b->first_name, $b->last_name);
            }
        }
    }

    // Build inning ordinals helper
    $ordinals = [1=>'1st',2=>'2nd',3=>'3rd',4=>'4th',5=>'5th',6=>'6th',7=>'7th',8=>'8th',9=>'9th',10=>'10th',11=>'11th',12=>'12th'];
    $innLabel = fn(int $n) => $ordinals[$n] ?? $n.'th';

    // Format XBH note list — groups multiple hits by same player together
    $fmtXbh = function(array $hits) use ($innLabel): string {
        $grouped = []; $order = [];
        foreach ($hits as $x) {
            if (!isset($grouped[$x['name']])) { $grouped[$x['name']] = []; $order[] = $x['name']; }
            $grouped[$x['name']][] = $innLabel($x['inning']) . ' off ' . ($x['off'] ?? '?') . ', ' . $x['on'] . ' on, ' . $x['outs'] . ' out' . ($x['outs'] != 1 ? 's' : '');
        }
        $parts = [];
        foreach ($order as $name) {
            $d = $grouped[$name];
            $parts[] = $name . (count($d) > 1 ? ' ' . count($d) : '') . ' (' . implode('; ', $d) . ')';
        }
        return implode('; ', $parts);
    };

    // Total Bases per player per team (from boxBatting)
    $totalBases = [$awayId => [], $homeId => []];
    foreach ([$awayId, $homeId] as $_tid) {
        foreach ($boxBatting[$_tid] ?? [] as $_b) {
            $tb = (int)$_b->h + (int)$_b->d + 2*(int)$_b->t + 3*(int)$_b->hr;
            if ($tb > 0) $totalBases[$_tid][(int)$_b->player_id] = ['name' => $fmtIni($_b->first_name, $_b->last_name), 'tb' => $tb];
        }
    }

    // RISP with 2 outs: batter made an out (result 1/4/5/11) with 2 outs and runner on 2B or 3B
    $risp       = [$awayId => [], $homeId => []];
    $twoOutRBI  = [$awayId => [], $homeId => []];
    foreach ($atBats as $half) {
        foreach ($half['atbats'] as $ab) {
            if ((int)$ab->outs < 2) continue;
            $tid = (int)$ab->team_id;
            $pid = (int)$ab->player_id;
            // RISP 2 outs
            if (((int)$ab->base2 || (int)$ab->base3) && in_array((int)$ab->result, [1, 4, 5, 11])) {
                $rsp = ((int)$ab->base2 > 0 ? 1 : 0) + ((int)$ab->base3 > 0 ? 1 : 0);
                $risp[$tid][$pid] = ($risp[$tid][$pid] ?? 0) + $rsp;
            }
            // 2-out RBI
            if ((int)$ab->rbi > 0) {
                $twoOutRBI[$tid][$pid] = ($twoOutRBI[$tid][$pid] ?? 0) + (int)$ab->rbi;
            }
        }
    }

    // Pitching game score: 50 + outs + 2K - 2H - 3BB - 3HBP - 6HR
    $gameScore = function(object $pit): int {
        return 50 + (int)$pit->outs + 2*(int)$pit->k - 2*(int)$pit->ha - 3*(int)$pit->bb - 3*(int)$pit->hp - 6*(int)$pit->hra;
    };

    // Pinch-hitter substitution notes
    // Build spot→players map from boxBatting (already sorted by bat_order then stint)
    $spotPlayers = [$awayId => [], $homeId => []];
    foreach ([$awayId, $homeId] as $_tid) {
        foreach ($boxBatting[$_tid] ?? [] as $_b) {
            $spotPlayers[$_tid][(int)$_b->bat_order][] = $_b;
        }
    }
    // phLetters: player_id → letter (a, b, c...) per team
    // phNotes:   team_id  → array of footnote strings
    $phLetters = [$awayId => [], $homeId => []];
    $phNotes   = [$awayId => [], $homeId => []];
    $phSeen    = [];
    $phAlpha   = [$awayId => 0, $homeId => 0];
    foreach ($atBats as $half) {
        foreach ($half['atbats'] as $ab) {
            if (!(int)$ab->pinch) continue;
            $tid   = (int)$ab->team_id;
            $pid   = (int)$ab->player_id;
            $spot  = (int)$ab->spot;
            $inn   = (int)$ab->inning;
            $key   = $tid . '_' . $pid . '_' . $inn;
            if (isset($phSeen[$key])) continue;
            $phSeen[$key] = true;
            if (!isset($phLetters[$tid][$pid])) {
                $phLetters[$tid][$pid] = chr(ord('a') + $phAlpha[$tid]++);
            }
            $letter   = $phLetters[$tid][$pid];
            $phName   = $fmtIni($ab->batter_first, $ab->batter_last);
            // Find the player immediately before this PH in the slot's appearance order
            $original = null;
            $prevInSlot = null;
            foreach ($spotPlayers[$tid][$spot] ?? [] as $_b) {
                if ((int)$_b->player_id === $pid) {
                    if ($prevInSlot) $original = $fmtIni($prevInSlot->first_name, $prevInSlot->last_name);
                    break;
                }
                $prevInSlot = $_b;
            }
            $phNotes[$tid][] = $letter . ' - ' . $phName . ($original ? ' pinch hit for ' . $original : ' (PH)') . ' in the ' . $innLabel($inn);
        }
    }
@endphp

{{-- ── GAME HEADER ── --}}
@php
    $awayRec = $homeAwayRecs[$awayId] ?? null;
    $homeRec = $homeAwayRecs[$homeId] ?? null;
    $awayOvr = $awayRec ? ($awayRec['home_w']+$awayRec['road_w']).'-'.($awayRec['home_l']+$awayRec['road_l']) : null;
    $homeOvr = $homeRec ? ($homeRec['home_w']+$homeRec['road_w']).'-'.($homeRec['home_l']+$homeRec['road_l']) : null;
    $awayLogo = $teamLogos[$awayId] ?? null;
    $homeLogo = $teamLogos[$homeId] ?? null;
@endphp
<div class="bg-gray-900 border-b border-gray-800">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-5">

        <p class="text-xs text-gray-600 text-center mb-4">
            {{ \Carbon\Carbon::parse($game->date)->format('l, F j, Y') }}
            @if($played && $game->innings && (int)$game->innings > 9)
                &nbsp;·&nbsp; <span class="text-yellow-500">{{ $game->innings }} Innings</span>
            @endif
        </p>

        {{-- Team logos + names + score --}}
        <div class="flex items-center justify-between gap-2">

            {{-- Away --}}
            <div class="flex items-center gap-3 flex-1 justify-end">
                <div class="text-right">
                    <a href="{{ route('team', $awayId) }}" class="text-lg font-extrabold {{ $awayWon ? 'text-white' : 'text-gray-400' }} hover:text-red-400 transition leading-tight block">{{ $game->away_name }}</a>
                    @if($awayOvr)<span class="text-xs text-gray-600">{{ $awayOvr }}</span>@endif
                </div>
                @if($awayLogo)
                    <img src="/images/logos/{{ $awayLogo }}" alt="{{ $game->away_abbr }}" class="w-10 h-10 object-contain flex-shrink-0 {{ $awayWon ? '' : 'opacity-50' }}">
                @endif
            </div>

            {{-- Scores --}}
            <div class="flex items-center gap-3 px-4 flex-shrink-0">
                @if($played)
                    <span class="text-4xl font-black tabular-nums {{ $awayWon ? 'text-white' : 'text-gray-500' }}">{{ $game->runs0 }}</span>
                    <div class="text-center">
                        <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">Final</p>
                    </div>
                    <span class="text-4xl font-black tabular-nums {{ $homeWon ? 'text-white' : 'text-gray-500' }}">{{ $game->runs1 }}</span>
                @else
                    <span class="text-gray-600 text-2xl font-light">@</span>
                @endif
            </div>

            {{-- Home --}}
            <div class="flex items-center gap-3 flex-1 justify-start">
                @if($homeLogo)
                    <img src="/images/logos/{{ $homeLogo }}" alt="{{ $game->home_abbr }}" class="w-10 h-10 object-contain flex-shrink-0 {{ $homeWon ? '' : 'opacity-50' }}">
                @endif
                <div class="text-left">
                    <a href="{{ route('team', $homeId) }}" class="text-lg font-extrabold {{ $homeWon ? 'text-white' : 'text-gray-400' }} hover:text-red-400 transition leading-tight block">{{ $game->home_name }}</a>
                    @if($homeOvr)<span class="text-xs text-gray-600">{{ $homeOvr }}</span>@endif
                </div>
            </div>

        </div>


    </div>
</div>

@if($played)

{{-- ── PLAYERS OF THE GAME + LINE SCORE ── --}}
@php
    $winTeamId  = $awayWon ? $awayId : $homeId;
    $loseTeamId = $awayWon ? $homeId : $awayId;

    // Top 2 WPA players across batting + pitching
    $potgRows = \Illuminate\Support\Facades\DB::select("
        SELECT p.player_id, p.first_name, p.last_name, p.position, s.wpa, s.source, s.team_id
        FROM (
            SELECT player_id, wpa, team_id, 'batting'  AS source FROM players_game_batting        WHERE game_id = ?
            UNION ALL
            SELECT player_id, wpa, team_id, 'pitching' AS source FROM players_game_pitching_stats WHERE game_id = ?
        ) s
        JOIN players p ON s.player_id = p.player_id
        ORDER BY s.wpa DESC LIMIT 2
    ", [$game->game_id, $game->game_id]);

    // Team colors keyed by team_id
    $_potgTeamIds = array_unique(array_map(fn($r) => (int)$r->team_id, $potgRows));
    $_teamColors  = \Illuminate\Support\Facades\DB::table('teams')
        ->whereIn('team_id', $_potgTeamIds)
        ->get(['team_id','background_color_id','text_color_id'])
        ->keyBy('team_id');

    // Build stat line for each POTG player
    $potgPlayers = [];
    foreach ($potgRows as $_pr) {
        $_pid  = (int)$_pr->player_id;
        $_line = [];
        if ($_pr->source === 'pitching') {
            // find in boxPitching
            $_pit = null;
            foreach (array_merge($boxPitching[$awayId] ?? [], $boxPitching[$homeId] ?? []) as $_p) {
                if ((int)$_p->player_id === $_pid) { $_pit = $_p; break; }
            }
            if ($_pit) {
                $_line[] = ($_pit->ip_display ?? '?') . ' IP';
                $_line[] = (int)$_pit->k . ' SO';
                if ($_pit->pi) $_line[] = (int)$_pit->pi . ' P';
            }
        } else {
            // find in boxBatting
            $_bat = null;
            foreach (array_merge($boxBatting[$awayId] ?? [], $boxBatting[$homeId] ?? []) as $_b) {
                if ((int)$_b->player_id === $_pid) { $_bat = $_b; break; }
            }
            if ($_bat) {
                $_line[] = (int)$_bat->h . '-' . (int)$_bat->ab;
                if ((int)$_bat->r   > 0) $_line[] = (int)$_bat->r   . ' R';
                if ((int)$_bat->rbi > 0) $_line[] = (int)$_bat->rbi . ' RBI';
                if ((int)$_bat->hr  > 0) $_line[] = ((int)$_bat->hr > 1 ? (int)$_bat->hr . ' ' : '') . 'HR';
                if ((int)$_bat->d   > 0) $_line[] = ((int)$_bat->d  > 1 ? (int)$_bat->d  . ' ' : '') . '2B';
                if ((int)$_bat->t   > 0) $_line[] = ((int)$_bat->t  > 1 ? (int)$_bat->t  . ' ' : '') . '3B';
                if ((int)$_bat->bb  > 0) $_line[] = ((int)$_bat->bb > 1 ? (int)$_bat->bb . ' ' : '') . 'BB';
                if ((int)$_bat->sb  > 0) $_line[] = ((int)$_bat->sb > 1 ? (int)$_bat->sb . ' ' : '') . 'SB';
            }
        }
        $_tc = $_teamColors[(int)$_pr->team_id] ?? null;
        $potgPlayers[] = [
            'row'     => $_pr,
            'line'    => $_line,
            'bgColor' => $_tc->background_color_id ?? '#1f2937',
            'txColor' => $_tc->text_color_id       ?? '#ffffff',
        ];
    }
@endphp
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 border-b border-gray-800">
    <div class="grid grid-cols-2 gap-10 items-start">

        {{-- Players of the Game --}}
        <div>
            <p class="text-xs font-bold tracking-widest text-gray-600 uppercase mb-4">Players of the Game</p>
            <div class="flex gap-8">
                @foreach($potgPlayers as $_potg)
                @php
                    $_initials = mb_strtoupper(mb_substr($_potg['row']->first_name, 0, 1) . mb_substr($_potg['row']->last_name, 0, 1));
                    $_wpa      = number_format((float)$_potg['row']->wpa * 100, 2);
                @endphp
                <div class="flex items-center gap-4 flex-1 min-w-0">
                    {{-- Team-colored circle --}}
                    <div class="shrink-0 w-20 h-20 rounded-full flex flex-col items-center justify-center"
                         style="background-color:{{ $_potg['bgColor'] }};border:3px solid {{ $_potg['txColor'] }}55">
                        <span class="font-black leading-none" style="font-size:1.1rem;color:{{ $_potg['txColor'] }}">{{ $_wpa }}</span>
                        <span class="font-bold tracking-widest mt-1" style="font-size:0.6rem;opacity:0.65;color:{{ $_potg['txColor'] }}">WPA</span>
                    </div>
                    {{-- Name + stats --}}
                    <div class="min-w-0">
                        <p class="font-bold text-white text-lg leading-tight">{{ $fmtIni($_potg['row']->first_name, $_potg['row']->last_name) }} <span class="text-gray-500 font-normal text-sm">{{ $positions[(int)($_potg['row']->position ?? 0)] ?? '' }}</span></p>
                        <p class="text-sm text-gray-300 mt-1">{{ implode(', ', $_potg['line']) }}</p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Line Score --}}
        <div>
            <div class="overflow-x-auto">
                <table class="text-xs w-full" style="table-layout:fixed">
                    <colgroup>
                        <col style="width:36px">
                        @for($i = 1; $i <= $lineScore['max']; $i++)<col style="width:22px">@endfor
                        <col style="width:28px">
                        <col style="width:24px">
                        <col style="width:24px">
                    </colgroup>
                    <thead>
                        <tr class="text-gray-600 border-b border-gray-800">
                            <th class="text-left py-1 font-medium"></th>
                            @for($i = 1; $i <= $lineScore['max']; $i++)
                                <th class="text-center py-1 font-medium">{{ $i }}</th>
                            @endfor
                            <th class="text-center py-1 font-bold text-gray-400 border-l border-gray-800">R</th>
                            <th class="text-center py-1 font-medium">H</th>
                            <th class="text-center py-1 font-medium">E</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach([
                            ['abbr'=>$game->away_abbr,'scores'=>$lineScore['away'],'runs'=>$game->runs0,'hits'=>$game->hits0??'—','err'=>$game->errors0??'—','winner'=>$awayWon],
                            ['abbr'=>$game->home_abbr,'scores'=>$lineScore['home'],'runs'=>$game->runs1,'hits'=>$game->hits1??'—','err'=>$game->errors1??'—','winner'=>$homeWon],
                        ] as $row)
                        <tr class="border-b border-gray-800/40">
                            <td class="py-1 font-bold {{ $row['winner'] ? 'text-white' : 'text-gray-500' }}">{{ $row['abbr'] }}</td>
                            @for($i = 1; $i <= $lineScore['max']; $i++)
                                <td class="text-center py-1 {{ isset($row['scores'][$i]) && $row['scores'][$i] > 0 ? 'text-gray-200' : 'text-gray-600' }}">
                                    {{ $row['scores'][$i] ?? (($i > (int)$game->innings && !$row['winner']) ? 'x' : '0') }}
                                </td>
                            @endfor
                            <td class="text-center py-1 font-bold {{ $row['winner'] ? 'text-white' : 'text-gray-500' }} border-l border-gray-800">{{ $row['runs'] }}</td>
                            <td class="text-center py-1 text-gray-400">{{ $row['hits'] }}</td>
                            <td class="text-center py-1 text-gray-400">{{ $row['err'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

{{-- ── BATTING BOX SCORES ── --}}
@php
    // CS play details keyed by RUNNER's player_id.
    // CS events appear in the batter's pitch log (not the runner's own at-bat),
    // so parse the runner name from the text and resolve via a full-name → player_id map.
    $_nameToId = [];
    foreach (array_merge($boxBatting[$awayId] ?? [], $boxBatting[$homeId] ?? []) as $_pb) {
        $_nameToId[trim($_pb->first_name . ' ' . $_pb->last_name)] = (int)$_pb->player_id;
    }
    $csPlayDetails = []; // player_id → [{base, pitcher}]
    foreach ($atBatLogs as $_abl) {
        foreach ($_abl['pitches'] as $_p) {
            if (preg_match('/^(.+?)\s+is caught stealing home/i', $_p, $_m)) {
                $_rpid = $_nameToId[trim($_m[1])] ?? null;
                if ($_rpid) $csPlayDetails[$_rpid][] = ['base' => 'home', 'pitcher' => $_abl['pitcher']];
            } elseif (preg_match('/^(.+?)\s+is caught stealing (\w+) base/i', $_p, $_m)) {
                $_rpid = $_nameToId[trim($_m[1])] ?? null;
                if ($_rpid) $csPlayDetails[$_rpid][] = ['base' => $_m[2], 'pitcher' => $_abl['pitcher']];
            }
        }
    }
    // SB play details keyed by RUNNER's player_id (same approach as CS)
    $sbPlayDetails = []; // player_id → [{base, pitcher}]
    foreach ($atBatLogs as $_abl) {
        foreach ($_abl['pitches'] as $_p) {
            if (preg_match('/^(.+?)\s+steals home/i', $_p, $_m)) {
                $_rpid = $_nameToId[trim($_m[1])] ?? null;
                if ($_rpid) $sbPlayDetails[$_rpid][] = ['base' => 'home', 'pitcher' => $_abl['pitcher']];
            } elseif (preg_match('/^(.+?)\s+steals (\w+)/i', $_p, $_m)) {
                $_rpid = $_nameToId[trim($_m[1])] ?? null;
                if ($_rpid) $sbPlayDetails[$_rpid][] = ['base' => $_m[2], 'pitcher' => $_abl['pitcher']];
            }
        }
    }
    // Starting catcher per team for this game (position 2, lowest stint = starter)
    $catcherByTeam = [];
    $_catcherRows = \Illuminate\Support\Facades\DB::table('players_game_batting as pgb')
        ->join('players as p', 'p.player_id', '=', 'pgb.player_id')
        ->where('pgb.game_id', $game->game_id)
        ->where('pgb.position', 2)
        ->orderBy('pgb.stint')
        ->get(['pgb.team_id', 'p.first_name', 'p.last_name']);
    foreach ($_catcherRows as $_cr) {
        $_ctid = (int)$_cr->team_id;
        if (!isset($catcherByTeam[$_ctid])) {
            $catcherByTeam[$_ctid] = $fmtIni($_cr->first_name, $_cr->last_name);
        }
    }
@endphp
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 border-b border-gray-800">
    <div class="grid grid-cols-2 gap-6">

        @foreach([
            ['team_id'=>$awayId,'abbr'=>$game->away_abbr],
            ['team_id'=>$homeId,'abbr'=>$game->home_abbr],
        ] as $side)
        @php
            $batters = $boxBatting[$side['team_id']] ?? [];
            $tid     = $side['team_id'];
        @endphp

        <div class="min-w-0">
            <p class="text-xs font-bold tracking-widest text-red-500 uppercase mb-2">{{ $side['abbr'] }} Batting</p>
            <div class="overflow-x-auto">
                <table class="text-xs w-full">
                    <thead>
                        <tr class="text-gray-600 border-b border-gray-800 uppercase tracking-wider">
                            <th class="text-left px-0 py-1.5 font-medium">Hitters</th>
                            <th class="text-center px-1 py-1.5 font-medium">AB</th>
                            <th class="text-center px-1 py-1.5 font-medium">R</th>
                            <th class="text-center px-1 py-1.5 font-medium">H</th>
                            <th class="text-center px-1 py-1.5 font-medium">RBI</th>
                            <th class="text-center px-1 py-1.5 font-medium">HR</th>
                            <th class="text-center px-1 py-1.5 font-medium">BB</th>
                            <th class="text-center px-1 py-1.5 font-medium">SO</th>
                            <th class="text-center px-1 py-1.5 font-medium">AVG</th>
                            <th class="text-center px-1 py-1.5 font-medium">OBP</th>
                            <th class="text-center px-1 py-1.5 font-medium">SLG</th>
                            <th class="text-center px-1 py-1.5 font-medium">OPS</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40">
                        @foreach($batters as $b)
                        @php
                            $bStat    = $batSeasonStats[(int)$b->player_id] ?? null;
                            $phLetter = $phLetters[$tid][(int)$b->player_id] ?? null;
                            $fieldPos = $fieldingPositions[(int)$b->position] ?? '';
                            // Fallback: pos=0 pitchers (SP=1, RP=2) show P
                            if (!$fieldPos && !$phLetter && in_array((int)$b->player_position, [1, 2])) {
                                $fieldPos = 'P';
                            }
                            $bOPS = $bStat ? number_format((float)$bStat['obp'] + (float)$bStat['slg'], 3) : null;
                        @endphp
                        <tr class="hover:bg-gray-800/20">
                            <td class="py-1.5 pr-2">
                                @if($phLetter)<span class="text-gray-500 text-xs mr-0.5">{{ $phLetter }}-</span>@endif
                                <a href="{{ route('player', $b->player_id) }}" class="text-gray-200 hover:text-red-400 transition font-medium">
                                    {{ mb_substr($b->first_name,0,1) }}. {{ $b->last_name }}
                                </a>
                                <span class="text-gray-600 ml-0.5">{{ $phLetter ? 'PH' : $fieldPos }}</span>
                            </td>
                            <td class="text-center px-1 py-1.5 text-gray-400">{{ $b->ab }}</td>
                            <td class="text-center px-1 py-1.5 text-gray-400">{{ $b->r }}</td>
                            <td class="text-center px-1 py-1.5 {{ (int)$b->h > 0 ? 'text-white font-semibold' : 'text-gray-500' }}">{{ $b->h }}</td>
                            <td class="text-center px-1 py-1.5 {{ (int)$b->rbi > 0 ? 'text-gray-200' : 'text-gray-500' }}">{{ $b->rbi }}</td>
                            <td class="text-center px-1 py-1.5 {{ (int)$b->hr > 0 ? 'text-yellow-400 font-bold' : 'text-gray-600' }}">{{ (int)$b->hr ?: '·' }}</td>
                            <td class="text-center px-1 py-1.5 text-gray-500">{{ $b->bb }}</td>
                            <td class="text-center px-1 py-1.5 text-gray-500">{{ $b->k }}</td>
                            <td class="text-center px-1 py-1.5 text-gray-500">{{ $bStat ? ltrim($bStat['avg'],'0') : '—' }}</td>
                            <td class="text-center px-1 py-1.5 text-gray-500">{{ $bStat ? ltrim($bStat['obp'],'0') : '—' }}</td>
                            <td class="text-center px-1 py-1.5 text-gray-500">{{ $bStat ? ltrim($bStat['slg'],'0') : '—' }}</td>
                            <td class="text-center px-1 py-1.5 text-gray-500">{{ $bOPS ? ltrim($bOPS,'0') : '—' }}</td>
                        </tr>
                        @endforeach
                        @if(!empty($batters))
                        @php $tot = $batTotals($batters); @endphp
                        <tr class="font-bold border-t border-gray-700 bg-gray-800/20">
                            <td class="py-1.5 text-gray-500 text-xs uppercase tracking-wider">Totals</td>
                            <td class="text-center px-1 py-1.5 text-gray-300">{{ $tot->ab }}</td>
                            <td class="text-center px-1 py-1.5 text-gray-300">{{ $tot->r }}</td>
                            <td class="text-center px-1 py-1.5 text-gray-300">{{ $tot->h }}</td>
                            <td class="text-center px-1 py-1.5 text-gray-300">{{ $tot->rbi }}</td>
                            <td class="text-center px-1 py-1.5 text-gray-300">{{ $tot->hr }}</td>
                            <td class="text-center px-1 py-1.5 text-gray-300">{{ $tot->bb }}</td>
                            <td class="text-center px-1 py-1.5 text-gray-300">{{ $tot->k }}</td>
                            <td colspan="4"></td>
                        </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            {{-- Batting notes — order matches OOTP: subs / 2B-3B-HR-TB-RISP-GIDP-LOB / baserunning / fielding --}}
            @php
                $notes          = $xbhNotes[$tid] ?? ['2b'=>[],'3b'=>[],'hr'=>[]];
                $teamGameErrors = $gameErrorData['counts'][$tid] ?? [];
                $teamDPChains   = $gameDoublePlays[$tid] ?? [];
                $_tbSorted = $totalBases[$tid] ?? [];
                usort($_tbSorted, fn($a, $b) => $b['tb'] <=> $a['tb']);
                $tbParts = array_map(fn($x) => $x['name'] . ($x['tb'] > 1 ? ' ' . $x['tb'] : ''), $_tbSorted);

                $_rispSorted = $risp[$tid] ?? [];
                arsort($_rispSorted);
                $rispParts = [];
                foreach ($_rispSorted as $_pid => $_cnt) {
                    $_rn = null;
                    foreach ($boxBatting[$tid] ?? [] as $_b) {
                        if ((int)$_b->player_id === $_pid) { $_rn = $fmtIni($_b->first_name, $_b->last_name); break; }
                    }
                    if ($_rn) $rispParts[] = $_rn . ($_cnt > 1 ? ' ' . $_cnt : '');
                }
                // 2-out RBI
                $_torSorted = $twoOutRBI[$tid] ?? [];
                arsort($_torSorted);
                $twoOutRbiParts = [];
                foreach ($_torSorted as $_pid => $_cnt) {
                    $_rn = null;
                    foreach ($boxBatting[$tid] ?? [] as $_b) {
                        if ((int)$_b->player_id === $_pid) { $_rn = $fmtIni($_b->first_name, $_b->last_name); break; }
                    }
                    if ($_rn) $twoOutRbiParts[] = $_rn . ($_cnt > 1 ? ' ' . $_cnt : '');
                }

                $teamLOB = $gameLOB[$tid] ?? 0;
                $sbParts = []; $csParts = []; $shParts = []; $hpParts = [];
                foreach ($boxBatting[$tid] ?? [] as $_b) {
                    $_pid2 = (int)$_b->player_id;
                    if ((int)$_b->sb > 0) {
                        $seasonSB = $batSeasonStats[$_pid2]['sb'] ?? 0;
                        $defTeamSB  = ($tid === $awayId) ? $homeId : $awayId;
                        $catcherSB  = $catcherByTeam[$defTeamSB] ?? null;
                        $_sbEvents  = $sbPlayDetails[$_pid2] ?? [];
                        $_sbParts2  = [];
                        foreach ($_sbEvents as $_ev) {
                            $_base2 = $_ev['base'] === 'home' ? 'Home' : ucfirst($_ev['base']) . ' base';
                            $_by2   = implode('/', array_filter([$_ev['pitcher'], $catcherSB]));
                            $_sbParts2[] = $_base2 . ($_by2 ? ', ' . $_by2 : '');
                        }
                        $sbParts[] = $fmtIni($_b->first_name, $_b->last_name)
                            . ((int)$_b->sb > 1 ? ' ' . (int)$_b->sb : '')
                            . ' (' . $seasonSB
                            . (!empty($_sbParts2) ? ', ' . implode('; ', $_sbParts2) : '')
                            . ')';
                    }
                    if ((int)$_b->cs > 0) {
                        $seasonCS  = $batSeasonStats[$_pid2]['cs'] ?? 0;
                        $defTeam   = ($tid === $awayId) ? $homeId : $awayId;
                        $catcher   = $catcherByTeam[$defTeam] ?? null;
                        $_csEvents = $csPlayDetails[$_pid2] ?? [];
                        $_csParts2 = [];
                        foreach ($_csEvents as $_ev) {
                            $_base = $_ev['base'] === 'home' ? 'Home' : ucfirst($_ev['base']) . ' base';
                            $_by   = implode('/', array_filter([$_ev['pitcher'], $catcher]));
                            $_csParts2[] = $_base . ($_by ? ' by ' . $_by : '');
                        }
                        $csParts[] = $fmtIni($_b->first_name, $_b->last_name)
                            . ' (' . $seasonCS
                            . (!empty($_csParts2) ? ', ' . implode('; ', $_csParts2) : '')
                            . ')';
                    }
                    if ((int)$_b->sh > 0) $shParts[] = $fmtIni($_b->first_name, $_b->last_name) . ((int)$_b->sh > 1 ? ' ' . (int)$_b->sh : '');
                    if ((int)$_b->hp > 0) $hpParts[] = $fmtIni($_b->first_name, $_b->last_name) . ((int)$_b->hp > 1 ? ' ' . (int)$_b->hp : '');
                }
                $errLines = [];
                foreach ($teamGameErrors as $_pid3 => $_cnt3) {
                    $_name3 = null;
                    foreach ($gameErrorData['posMap'][$tid] ?? [] as $_bObj) {
                        if ((int)$_bObj->player_id === $_pid3) { $_name3 = $fmtIni($_bObj->first_name, $_bObj->last_name); break; }
                    }
                    $seasonE = $seasonErrors[$_pid3] ?? 0;
                    $errLines[] = ($_name3 ?? 'Unknown') . ' ' . $_cnt3 . ' (' . $seasonE . ')';
                }
                $hasBatting     = !empty($notes['2b']) || !empty($notes['3b']) || !empty($notes['hr'])
                                  || !empty($tbParts) || !empty($twoOutRbiParts) || !empty($rispParts)
                                  || !empty($shParts) || !empty($hpParts) || !empty($gidpNotes[$tid]) || $teamLOB > 0;
                $hasBaserunning = !empty($sbParts) || !empty($csParts);
                $hasFielding    = !empty($errLines) || !empty($teamDPChains);
            @endphp
            <div class="mt-3 text-sm text-gray-400 leading-relaxed max-w-prose">

                {{-- Substitutions --}}
                @if(!empty($phNotes[$tid]))
                <div class="space-y-0.5 mb-4">
                    @foreach($phNotes[$tid] as $phLine)
                    <p>{{ $phLine }}</p>
                    @endforeach
                </div>
                @endif

                {{-- Batting notes (OOTP order: 2B 3B HR TB RISP GIDP LOB) --}}
                @if($hasBatting)
                <div class="space-y-0.5">
                    @if(!empty($notes['2b']))
                    <p><span class="font-semibold text-gray-400">2B:</span> {{ $fmtXbh($notes['2b']) }}</p>
                    @endif
                    @if(!empty($notes['3b']))
                    <p><span class="font-semibold text-gray-400">3B:</span> {{ $fmtXbh($notes['3b']) }}</p>
                    @endif
                    @if(!empty($notes['hr']))
                    <p><span class="font-semibold text-gray-400">HR:</span> {{ $fmtXbh($notes['hr']) }}</p>
                    @endif
                    @if(!empty($tbParts))
                    <p><span class="font-semibold text-gray-400">Total Bases:</span> {{ implode(', ', $tbParts) }}</p>
                    @endif
                    @if(!empty($twoOutRbiParts))
                    <p><span class="font-semibold text-gray-400">2-Out RBI:</span> {{ implode(', ', $twoOutRbiParts) }}</p>
                    @endif
                    @if(!empty($rispParts))
                    <p><span class="font-semibold text-gray-400">Runners left in scoring position, 2 outs:</span> {{ implode(', ', $rispParts) }}</p>
                    @endif
                    @if(!empty($shParts))
                    <p><span class="font-semibold text-gray-400">Sac Bunt:</span> {{ implode(', ', $shParts) }}</p>
                    @endif
                    @if(!empty($hpParts))
                    <p><span class="font-semibold text-gray-400">Hit by Pitch:</span> {{ implode(', ', $hpParts) }}</p>
                    @endif
                    @if(!empty($gidpNotes[$tid]))
                    <p><span class="font-semibold text-gray-400">GIDP:</span> {{ implode(', ', $gidpNotes[$tid]) }}</p>
                    @endif
                    @if($teamLOB > 0)
                    <p><span class="font-semibold text-gray-400">Team LOB:</span> {{ $teamLOB }}</p>
                    @endif
                </div>
                @endif

                {{-- Baserunning --}}
                @if($hasBaserunning)
                <div class="mt-4 space-y-0.5">
                    <p class="font-bold text-gray-400 uppercase tracking-wider text-xs">Baserunning</p>
                    @if(!empty($sbParts))
                    <p><span class="font-semibold text-gray-400">SB:</span> {{ implode(', ', $sbParts) }}</p>
                    @endif
                    @if(!empty($csParts))
                    <p><span class="font-semibold text-gray-400">CS:</span> {{ implode(', ', $csParts) }}</p>
                    @endif
                </div>
                @endif

                {{-- Fielding --}}
                @if($hasFielding)
                <div class="mt-4 space-y-0.5">
                    <p class="font-bold text-gray-400 uppercase tracking-wider text-xs">Fielding</p>
                    @if(!empty($errLines))
                    <p><span class="font-semibold text-gray-400">E:</span> {{ implode(', ', $errLines) }}</p>
                    @endif
                    @if(!empty($teamDPChains))
                    <p><span class="font-semibold text-gray-400">DP:</span> {{ count($teamDPChains) }} ({{ implode(', ', $teamDPChains) }})</p>
                    @endif
                </div>
                @endif

            </div>

        </div>
        @endforeach

    </div>
</div>

{{-- ── PITCHING BOX SCORES ── --}}
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 border-b border-gray-800">
    <div class="grid grid-cols-2 gap-6">

        @foreach([
            ['team_id'=>$awayId,'abbr'=>$game->away_abbr],
            ['team_id'=>$homeId,'abbr'=>$game->home_abbr],
        ] as $side)
        @php
            $pitchers = $boxPitching[$side['team_id']] ?? [];
        @endphp

        <div>
            <p class="text-xs font-bold tracking-widest text-red-500 uppercase mb-2">{{ $side['abbr'] }} Pitching</p>
            <div class="overflow-x-auto">
                <table class="text-xs w-full">
                    <thead>
                        <tr class="text-gray-600 border-b border-gray-800 uppercase tracking-wider">
                            <th class="text-left py-1.5 pr-2 font-medium">Pitcher</th>
                            <th class="text-center px-1.5 py-1.5 font-medium">IP</th>
                            <th class="text-center px-1.5 py-1.5 font-medium">H</th>
                            <th class="text-center px-1.5 py-1.5 font-medium">R</th>
                            <th class="text-center px-1.5 py-1.5 font-medium">ER</th>
                            <th class="text-center px-1.5 py-1.5 font-medium">BB</th>
                            <th class="text-center px-1.5 py-1.5 font-medium">K</th>
                            <th class="text-center px-1.5 py-1.5 font-medium">HR</th>
                            <th class="text-center px-1.5 py-1.5 font-medium">BF</th>
                            <th class="text-center px-1.5 py-1.5 font-medium">PI</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40">
                        @foreach($pitchers as $pit)
                        @php
                            $decLabel = '';
                            if ($game->winning_pitcher == $pit->player_id)     $decLabel = 'W';
                            elseif ($game->losing_pitcher == $pit->player_id)  $decLabel = 'L';
                            elseif ($game->save_pitcher == $pit->player_id)    $decLabel = 'SV';
                            elseif ((int)$pit->hld > 0)                        $decLabel = 'H';
                            elseif ((int)$pit->bs > 0)                         $decLabel = 'BS';
                        @endphp
                        <tr class="hover:bg-gray-800/20">
                            <td class="py-1.5 pr-2">
                                <a href="{{ route('player', $pit->player_id) }}" class="text-gray-200 hover:text-red-400 transition font-medium">
                                    {{ mb_substr($pit->first_name,0,1) }}. {{ $pit->last_name }}
                                </a>
                                @if($decLabel)
                                    <span class="ml-1 font-bold
                                        {{ $decLabel==='W'?'text-green-400':($decLabel==='L'?'text-red-400':($decLabel==='SV'?'text-blue-400':'text-gray-500')) }}">
                                        {{ $decLabel }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-center px-1.5 py-1.5 text-gray-200 font-semibold">{{ $pit->ip_display }}</td>
                            <td class="text-center px-1.5 py-1.5 text-gray-400">{{ $pit->ha }}</td>
                            <td class="text-center px-1.5 py-1.5 text-gray-400">{{ $pit->r }}</td>
                            <td class="text-center px-1.5 py-1.5 text-gray-400">{{ $pit->er }}</td>
                            <td class="text-center px-1.5 py-1.5 text-gray-400">{{ $pit->bb }}</td>
                            <td class="text-center px-1.5 py-1.5 {{ (int)$pit->k >= 5 ? 'text-yellow-400 font-semibold' : 'text-gray-400' }}">{{ $pit->k }}</td>
                            <td class="text-center px-1.5 py-1.5 {{ (int)$pit->hra > 0 ? 'text-red-400' : 'text-gray-600' }}">{{ $pit->hra ?: '·' }}</td>
                            <td class="text-center px-1.5 py-1.5 text-gray-500">{{ $pit->bf ?? '—' }}</td>
                            <td class="text-center px-1.5 py-1.5 text-gray-500">{{ $pit->pi ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pitching notes --}}
            @if(!empty($pitchers))
            <div class="mt-3 text-sm text-gray-400 leading-relaxed space-y-1">
                @php
                    $gsParts  = [];
                    $gbfbParts = [];
                    $piParts  = [];
                    foreach ($pitchers as $pit) {
                        $name = mb_substr($pit->first_name,0,1).'. '.$pit->last_name;
                        $gs   = $gameScore($pit);
                        $gsParts[]   = $name . ' ' . $gs;
                        $gbfbParts[] = $name . ' ' . (int)$pit->gb . '-' . (int)$pit->fb;
                        $piParts[]   = $name . ' ' . ($pit->pi ?? '?');
                    }
                @endphp
                <p><span class="font-semibold text-gray-400">Game Score:</span> {{ implode(', ', $gsParts) }}</p>
                <p><span class="font-semibold text-gray-400">GB-FB:</span> {{ implode(', ', $gbfbParts) }}</p>
                <p><span class="font-semibold text-gray-400">Pitches:</span> {{ implode(', ', $piParts) }}</p>
            </div>
            @endif
        </div>
        @endforeach

    </div>
</div>

{{-- ── GAME NOTES ── --}}
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 border-b border-gray-800">
    <div class="text-sm text-gray-400 flex flex-wrap gap-x-6 gap-y-1">
        @if($game->park_name)
            <span><span class="text-gray-400 font-semibold">Park:</span> {{ $game->park_name }}</span>
        @endif
        @if($game->attendance)
            <span><span class="text-gray-400 font-semibold">Attendance:</span> {{ number_format($game->attendance) }}</span>
        @endif
        @if($game->time)
        @php
            $hr   = intdiv((int)$game->time, 100);
            $mn   = (int)$game->time % 100;
            $ampm = $hr >= 12 ? 'PM' : 'AM';
            $h12  = $hr % 12 ?: 12;
            $startTime = sprintf('%d:%02d %s ET', $h12, $mn, $ampm);
        @endphp
            <span><span class="text-gray-400 font-semibold">Start:</span> {{ $startTime }}</span>
        @endif
    </div>
</div>

{{-- ── GAME LOG ── --}}
@if(!empty($atBats))
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 pb-12">
    <h2 class="text-xs font-bold tracking-widest text-red-500 uppercase mb-5">Game Log</h2>

    @php
        $abIdx  = 0;

        // Build pitch-log lookup keyed by "player_id_inning_half".
        // Using inning+half avoids the carry-over at-bat mismatch where a batter
        // appears in game_logs for two different innings (7th interrupted, 8th completed).
        // Populate pitch log key first — needed by runner simulation before orphan building.
        $pitchLogByKey = [];   // ["pid_inning_half" => pitches[]]
        foreach ($atBatLogs as $_abl) {
            $_pid2 = $_abl['player_id'] ?? null;
            if ($_pid2 === null) continue;
            $pitchLogByKey[$_pid2 . '_' . $_abl['inning'] . '_' . $_abl['half']] = $_abl['pitches'];
        }

        // Also detect orphan entries: batter in game_logs but no DB row for that inning/half.
        // Build set of (pid_inning_half) that DO have a DB at-bat row.
        $_seenAbKeys = [];
        foreach ($atBats as $_h) {
            $_hHalf = $_h['half'];
            foreach ($_h['atbats'] as $_ab) {
                $_seenAbKeys[(int)$_ab->player_id . '_' . (int)$_ab->inning . '_' . $_hHalf] = true;
            }
        }
        // Build spot lookup and last-at-bat-per-half lookup
        $_spotByPlayer  = [];
        $_lastAbByHalf  = [];
        foreach ($atBats as $_h) {
            $_hKey = $_h['inning'] . '_' . $_h['half'];
            foreach ($_h['atbats'] as $_ab) {
                $_spotByPlayer[(int)$_ab->player_id] = $_ab->spot;
                $_lastAbByHalf[$_hKey] = $_ab;  // last one wins
            }
        }
        // Simulate runner names per at-bat using game_logs pitch text as source of truth.
        // Non-pitch entries (no "X-Y:" prefix) contain explicit runner movements:
        // "Name to second", "Name scores", "Name is caught stealing", etc.
        // Only the batter's own result (reach base or out) is taken from the result code.
        $_toBase = ['first'=>1,'second'=>2,'third'=>3];
        foreach ($atBats as &$_halfRef) {
            $_runners = [1 => null, 2 => null, 3 => null];
            foreach ($_halfRef['atbats'] as &$_abRef) {
                // Store BEFORE state
                $_abRef->_runner1 = $_runners[1];
                $_abRef->_runner2 = $_runners[2];
                $_abRef->_runner3 = $_runners[3];

                // Process pitch log — non-pitch lines contain runner movements;
                // pitch result lines are checked for fielder's choice (batter reaches 1st
                // even though the result code is 4/Groundout).
                $_abPitches = $pitchLogByKey[(int)$_abRef->player_id . '_' . (int)$_abRef->inning . '_' . $_halfRef['half']] ?? [];
                $_fcReached = false;
                foreach ($_abPitches as $_p) {
                    if (preg_match('/^\d+-\d+:/i', $_p)) {
                        if (preg_match('/fielders? choice/i', $_p)) $_fcReached = true;
                        continue; // skip "0-0: Ball" etc.
                    }
                    if      (preg_match('/caught stealing 2nd|picked off 1st/i', $_p))  { $_runners[1] = null; }
                    elseif  (preg_match('/caught stealing 3rd|picked off 2nd/i', $_p))  { $_runners[2] = null; }
                    elseif  (preg_match('/caught stealing home|picked off 3rd/i', $_p)) { $_runners[3] = null; }
                    elseif  (preg_match('/steals 2nd/i', $_p))  { $_runners[2] = $_runners[1]; $_runners[1] = null; }
                    elseif  (preg_match('/steals 3rd/i', $_p))  { $_runners[3] = $_runners[2]; $_runners[2] = null; }
                    elseif  (preg_match('/steals home/i', $_p)) { $_runners[3] = null; }
                    elseif  (preg_match('/^(.+?)\s+to\s+(first|second|third)/i', $_p, $_pm)) {
                        $_mn = trim($_pm[1]);
                        $_tb = $_toBase[strtolower($_pm[2])] ?? null;
                        if ($_tb) {
                            foreach ([1,2,3] as $_b) { if ($_runners[$_b] === $_mn) { $_runners[$_b] = null; break; } }
                            $_runners[$_tb] = $_mn;
                        }
                    }
                    elseif (preg_match('/^(.+?)\s+scores/i', $_p, $_pm)) {
                        $_mn = trim($_pm[1]);
                        foreach ([1,2,3] as $_b) { if ($_runners[$_b] === $_mn) { $_runners[$_b] = null; break; } }
                    }
                }

                // Add batter to base based on result (game_logs handles all runner advancements above)
                $_rn   = (int)$_abRef->result;
                $_abName = trim(($_abRef->batter_first ?? '') . ' ' . ($_abRef->batter_last ?? ''));
                if      ($_rn === 9)              { $_runners[1] = $_runners[2] = $_runners[3] = null; }
                elseif  (in_array($_rn, [2, 10])) { $_runners[1] = $_abName; }
                elseif  ($_rn === 6)              { $_runners[1] = $_abName; }
                elseif  ($_rn === 7)              { $_runners[2] = $_abName; }
                elseif  ($_rn === 8)              { $_runners[3] = $_abName; }
                elseif  ($_fcReached)             { $_runners[1] = $_abName; } // fielder's choice: batter safe at 1st despite result=4
                $_abRef->_fcReached = $_fcReached;
                // Outs: game_logs text already moved any runners; batter doesn't reach
            }
            unset($_abRef);
        }
        unset($_halfRef);

        // Build orphan entries using the previous at-bat's DB base state + result.
        // The DB base1/2/3 flags already reflect any mid-PA events (CS, pickoffs)
        // from earlier at-bats, so this is the authoritative source.
        $orphansByHalf = [];
        foreach ($atBatLogs as $_abl) {
            $pid = $_abl['player_id'] ?? null;
            if ($pid === null) continue;
            $_abKey = $pid . '_' . $_abl['inning'] . '_' . $_abl['half'];
            if (!isset($_seenAbKeys[$_abKey])) {
                $_hKey   = $_abl['inning'] . '_' . $_abl['half'];
                $_prevAb = $_lastAbByHalf[$_hKey] ?? null;
                $_orphOuts = $_prevAb
                    ? (int)$_prevAb->outs + (in_array((int)$_prevAb->result, [1,4,5]) ? 1 : 0)
                    : null;
                // Start from the previous batter's DB base flags and runner names,
                // then advance based on their result to get the orphan's state.
                // base1/2/3 = BEFORE state. Apply prev result to get orphan's before state.
                $_r1 = $_prevAb ? (int)$_prevAb->base1 : 0;
                $_r2 = $_prevAb ? (int)$_prevAb->base2 : 0;
                $_r3 = $_prevAb ? (int)$_prevAb->base3 : 0;
                if ($_prevAb) {
                    $_pr = (int)$_prevAb->result;
                    if (in_array($_pr, [2,10])) { $_r3=($_r1&&$_r2)?1:$_r3; $_r2=$_r1?1:$_r2; $_r1=1; }
                    elseif ($_pr===6) { $_r3=$_r2; $_r2=$_r1; $_r1=1; }
                    elseif ($_pr===7) { $_r3=$_r1; $_r2=1; $_r1=0; }
                    elseif ($_pr===8) { $_r3=1; $_r2=0; $_r1=0; }
                    elseif ($_pr===9) { $_r1=$_r2=$_r3=0; }
                }
                // Runner names: start from prev batter's BEFORE state, replay their pitch log
                // events line by line (same logic as the main simulation), then place prev
                // batter on base per their result code → gives us the orphan's BEFORE state.
                $_n1 = $_n2 = $_n3 = null;
                if ($_prevAb) {
                    $_pn = [1 => $_prevAb->_runner1 ?? null, 2 => $_prevAb->_runner2 ?? null, 3 => $_prevAb->_runner3 ?? null];
                    $_prevPitches = $pitchLogByKey[(int)$_prevAb->player_id . '_' . (int)$_prevAb->inning . '_' . $_abl['half']] ?? [];
                    // Walk through every game_log line for the previous at-bat
                    $_prevFcReached = false;
                    foreach ($_prevPitches as $_pp) {
                        if (preg_match('/^\d+-\d+:/i', $_pp)) {
                            if (preg_match('/fielders? choice/i', $_pp)) $_prevFcReached = true;
                            continue; // skip pitch entries
                        }
                        if      (preg_match('/caught stealing 2nd|picked off 1st/i', $_pp))  { $_pn[1] = null; }
                        elseif  (preg_match('/caught stealing 3rd|picked off 2nd/i', $_pp))  { $_pn[2] = null; }
                        elseif  (preg_match('/caught stealing home|picked off 3rd/i', $_pp)) { $_pn[3] = null; }
                        elseif  (preg_match('/steals 2nd/i', $_pp))  { $_pn[2] = $_pn[1]; $_pn[1] = null; }
                        elseif  (preg_match('/steals 3rd/i', $_pp))  { $_pn[3] = $_pn[2]; $_pn[2] = null; }
                        elseif  (preg_match('/steals home/i', $_pp)) { $_pn[3] = null; }
                        elseif  (preg_match('/^(.+?)\s+to\s+(first|second|third)/i', $_pp, $_pm2)) {
                            $_mn2 = trim($_pm2[1]);
                            $_tb2 = $_toBase[strtolower($_pm2[2])] ?? null;
                            if ($_tb2) {
                                foreach ([1,2,3] as $_b2) { if ($_pn[$_b2] === $_mn2) { $_pn[$_b2] = null; break; } }
                                $_pn[$_tb2] = $_mn2;
                            }
                        }
                        elseif (preg_match('/^(.+?)\s+scores/i', $_pp, $_pm2)) {
                            $_mn2 = trim($_pm2[1]);
                            foreach ([1,2,3] as $_b2) { if ($_pn[$_b2] === $_mn2) { $_pn[$_b2] = null; break; } }
                        }
                    }
                    // Place the previous batter on base from their result code
                    $_prevName = trim(($_prevAb->batter_first ?? '') . ' ' . ($_prevAb->batter_last ?? ''));
                    $_prevResult = (int)$_prevAb->result;
                    if      ($_prevResult === 9)                    { $_pn[1] = $_pn[2] = $_pn[3] = null; }
                    elseif  (in_array($_prevResult, [2, 10]))       { $_pn[1] = $_prevName; }
                    elseif  ($_prevResult === 6)                    { $_pn[1] = $_prevName; }
                    elseif  ($_prevResult === 7)                    { $_pn[2] = $_prevName; }
                    elseif  ($_prevResult === 8)                    { $_pn[3] = $_prevName; }
                    elseif  ($_prevFcReached)                       { $_pn[1] = $_prevName; }
                    $_n1 = $_pn[1]; $_n2 = $_pn[2]; $_n3 = $_pn[3];
                }
                $orphansByHalf[$_hKey][] = [
                    'name'    => $_abl['batter_name'] ?? '—',
                    'spot'    => $_spotByPlayer[$pid] ?? '',
                    'pitcher' => $_abl['pitcher'] ?? null,
                    'pitches' => $_abl['pitches'],
                    'outs'    => $_orphOuts,
                    'base1'   => $_r1, 'base2' => $_r2, 'base3' => $_r3,
                    'runner1' => $_n1, 'runner2' => $_n2, 'runner3' => $_n3,
                ];
            }
        }

        // Pre-compute score after each at-bat using run_diff deltas.
        // run_diff = home_score - away_score BEFORE each at-bat.
        // Score AFTER at-bat N = the state going into at-bat N+1.
        $flatAbs = [];
        foreach ($atBats as $h) {
            foreach ($h['atbats'] as $_ab) {
                $flatAbs[] = ['team_id' => (int)$h['team_id'], 'run_diff' => (int)$_ab->run_diff];
            }
        }
        $awayFinal  = array_sum($lineScore['away']);
        $homeFinal  = array_sum($lineScore['home']);
        $scoreAfterArr = [];
        $trackAway  = 0;
        $trackHome  = 0;
        for ($__i = 0; $__i < count($flatAbs); $__i++) {
            $__nextDiff = ($__i + 1 < count($flatAbs))
                ? $flatAbs[$__i + 1]['run_diff']
                : ($homeFinal - $awayFinal);
            $__delta = $__nextDiff - $flatAbs[$__i]['run_diff'];
            if ($__delta < 0) $trackAway += (-$__delta);
            if ($__delta > 0) $trackHome += $__delta;
            $scoreAfterArr[$__i] = [$trackAway, $trackHome];
        }
    @endphp

    @foreach($atBats as $half)
    @php
        $halfLabel = $half['half'] === 'top' ? 'Top' : 'Bot';
        $innStr    = $innLabel($half['inning']);
        $teamAbbr  = $half['team_id'] === $awayId ? $game->away_abbr : $game->home_abbr;
        $halfRuns  = $half['half'] === 'top'
            ? ($lineScore['away'][$half['inning']] ?? 0)
            : ($lineScore['home'][$half['inning']] ?? 0);
    @endphp

    <div class="mb-3">
        {{-- Inning header --}}
        <div class="flex items-center gap-2 py-1.5 border-b border-gray-800 mb-0.5">
            <span class="text-xs font-bold tracking-widest text-gray-500 uppercase">{{ $halfLabel }} {{ $innStr }}</span>
            <span class="text-gray-700 text-xs">{{ $teamAbbr }}</span>
            @if($halfRuns > 0)
                <span class="bg-red-600/20 text-red-400 text-xs font-bold px-1.5 py-0.5 rounded">
                    {{ $halfRuns }} {{ $halfRuns === 1 ? 'Run' : 'Runs' }}
                </span>
            @endif
        </div>

        {{-- At-bats --}}
        @foreach($half['atbats'] as $ab)
        @php
            $isHR        = (int)$ab->result === 9;
            $autoExpand  = $abIdx === 0 || $isHR;
            $resultLabel = ($ab->_fcReached ?? false) ? "Fielder's Choice" : ($results[(int)$ab->result] ?? 'At-Bat');
            $batterName  = trim($ab->batter_first . ' ' . $ab->batter_last) ?: '—';
            $pitcherName = trim(($ab->pitcher_first ?? '') . ' ' . ($ab->pitcher_last ?? '')) ?: '—';
            // Use simulation runner names for flags — more reliable than DB base1/2/3
            // which OOTP sometimes stores as the after-state (e.g. sac bunt plays).
            $b1 = ($ab->_runner1 ?? null) ? 1 : 0;
            $b2 = ($ab->_runner2 ?? null) ? 1 : 0;
            $b3 = ($ab->_runner3 ?? null) ? 1 : 0;
            $bases = [];
            if ($b1) $bases[] = '1B';
            if ($b2) $bases[] = '2B';
            if ($b3) $bases[] = '3B';
            $baseStr = empty($bases) ? 'Bases empty' : 'Runners on ' . implode(', ', $bases);
            [$__away, $__home] = $scoreAfterArr[$abIdx] ?? [0, 0];
            $scoreAfter = $game->away_abbr . ' ' . $__away . ' — ' . $__home . ' ' . $game->home_abbr;
            // Game log entries for this at-bat — matched by player_id + inning + half
            $__pid    = (int)$ab->player_id;
            $__half   = (int)$ab->team_id === $awayId ? 'top' : 'bottom';
            $__abKey  = $__pid . '_' . (int)$ab->inning . '_' . $__half;
            $abPitches = $pitchLogByKey[$__abKey] ?? [];
            $abIdx++;
        @endphp

        <div class="ab-item border-b border-gray-800/30 last:border-0">
            {{-- Header row --}}
            <button onclick="toggleAB(this)"
                class="w-full text-left flex items-center gap-2 px-2 py-2 hover:bg-gray-800/30 transition group">

                <span class="ab-arrow text-gray-700 text-xs w-2.5 shrink-0 group-hover:text-gray-500">{{ $autoExpand ? '▼' : '▶' }}</span>
                @if($ab->pinch)
                    <span class="text-amber-500 text-xs font-bold w-3.5 shrink-0">PH</span>
                @else
                    <span class="text-gray-600 text-xs w-3.5 shrink-0 text-right">{{ $ab->spot ?? '' }}</span>
                @endif

                <span class="flex-1 text-sm font-medium {{ $isHR ? 'text-yellow-300' : ($ab->pinch ? 'text-amber-200/70 italic' : 'text-gray-200') }}">
                    {{ $batterName }}
                    <span class="ml-1.5 font-semibold
                        {{ $isHR ? 'text-yellow-400' : (in_array((int)$ab->result,[6,7,8]) ? 'text-green-400' : (in_array((int)$ab->result,[2,10]) ? 'text-blue-400' : 'text-gray-500')) }}">
                        {{ $resultLabel }}
                        @if((int)$ab->rbi > 0)<span class="text-gray-600 font-normal ml-1">{{ $ab->rbi }} RBI</span>@endif
                    </span>
                </span>

                <span class="text-gray-300 text-sm font-semibold shrink-0 hidden sm:block" style="font-family:'Roboto',sans-serif">{{ $scoreAfter }}</span>

            </button>

            {{-- Detail --}}
            <div class="ab-detail {{ $autoExpand ? '' : 'hidden' }} bg-gray-950/60 px-8 py-2.5 text-sm text-gray-500">
                <div class="flex gap-8 mb-2.5 overflow-x-auto">
                    <div class="shrink-0"><span class="text-gray-700 text-xs uppercase tracking-wider">Count</span><br><span class="text-gray-400">{{ $ab->balls }}-{{ $ab->strikes }}</span></div>
                    <div class="shrink-0"><span class="text-gray-700 text-xs uppercase tracking-wider">Outs</span><br><span class="text-gray-400">{{ $ab->outs }}</span></div>
                    <div class="shrink-0"><span class="text-gray-700 text-xs uppercase tracking-wider">RBI / R</span><br><span class="text-gray-400">{{ $ab->rbi }} / {{ $ab->r }}</span></div>
                    <div class="shrink-0">
                        <span class="text-gray-700 text-xs uppercase tracking-wider block mb-1">Runners</span>
                        <div class="base-diamond">
                            <div class="diamond third-base" style="background:{{ $b3 ? '#3b82f6' : '#374151' }}"
                                @if($b3 && ($ab->_runner3 ?? null)) title="{{ $ab->_runner3 }}" @endif></div>
                            <div class="base-diamond-center">
                                <div class="diamond" style="background:{{ $b2 ? '#3b82f6' : '#374151' }}"
                                    @if($b2 && ($ab->_runner2 ?? null)) title="{{ $ab->_runner2 }}" @endif></div>
                                <div style="height:8px"></div>
                            </div>
                            <div class="diamond first-base" style="background:{{ $b1 ? '#3b82f6' : '#374151' }}"
                                @if($b1 && ($ab->_runner1 ?? null)) title="{{ $ab->_runner1 }}" @endif></div>
                        </div>
                    </div>
                    @if($ab->exit_velo)
                    <div class="shrink-0"><span class="text-gray-700 text-xs uppercase tracking-wider">Exit Velo</span><br><span class="text-gray-400">{{ number_format($ab->exit_velo,1) }} mph</span></div>
                    @endif
                    @if($ab->launch_angle)
                    <div class="shrink-0"><span class="text-gray-700 text-xs uppercase tracking-wider">Launch Angle</span><br><span class="text-gray-400">{{ $ab->launch_angle }}°</span></div>
                    @endif
                    <div class="shrink-0"><span class="text-gray-700 text-xs uppercase tracking-wider">Pitcher</span><br><span class="text-gray-400">{{ $pitcherName }}</span></div>
                </div>
                @if(!empty($abPitches))
                <div class="border-t border-gray-800/40 pt-2 space-y-0.5">
                    @foreach($abPitches as $pitch)
                    <p class="text-xs text-gray-400" style="font-family:'Roboto',sans-serif">{{ $pitch }}</p>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
        @endforeach

        {{-- Orphan (carry-over) at-bats: batter appeared in game_logs but no DB row --}}
        {{-- (e.g. CS ends inning while batter is mid-PA; batter's AB continues next inning) --}}
        @php $_orphanKey = $half['inning'] . '_' . $half['half']; @endphp
        @foreach($orphansByHalf[$_orphanKey] ?? [] as $_orph)
        @php
            // Find the play that ended the inning (last pitch log entry)
            $_orphLastPlay = !empty($_orph['pitches']) ? end($_orph['pitches']) : null;
            // Strip count prefix (e.g. "1-0: Ball" → "Ball"); baserunning plays have no count prefix
            $_orphPlayLabel = $_orphLastPlay
                ? preg_replace('/^\d+-\d+:\s*/', '', $_orphLastPlay)
                : null;
            // Parse the count from the last counted pitch (e.g. "1-0: Ball" → balls=2, strikes=0 after)
            $_orphCount = '—';
            foreach (array_reverse($_orph['pitches']) as $_pp) {
                if (preg_match('/^(\d+)-(\d+):/', $_pp, $_cm)) {
                    $_orphCount = $_cm[1] . '-' . $_cm[2];
                    break;
                }
            }
        @endphp
        <div class="ab-item border-b border-gray-800/30 last:border-0">
            <button onclick="toggleAB(this)"
                class="w-full text-left flex items-center gap-2 px-2 py-2 hover:bg-gray-800/30 transition group">
                <span class="ab-arrow text-gray-700 text-xs w-2.5 shrink-0 group-hover:text-gray-500">▶</span>
                <span class="text-gray-600 text-xs w-3.5 shrink-0 text-right">{{ $_orph['spot'] }}</span>
                <span class="flex-1 text-sm font-medium text-gray-200">
                    {{ $_orph['name'] }}
                    @if($_orphPlayLabel)
                        <span class="ml-1.5 font-semibold text-gray-500">{{ $_orphPlayLabel }}</span>
                    @endif
                </span>
            </button>
            <div class="ab-detail hidden bg-gray-950/60 px-8 py-2.5 text-sm text-gray-500">
                <div class="flex gap-8 mb-2.5 overflow-x-auto">
                    <div class="shrink-0"><span class="text-gray-700 text-xs uppercase tracking-wider">Count</span><br><span class="text-gray-400">{{ $_orphCount }}</span></div>
                    @if($_orph['outs'] !== null)
                    <div class="shrink-0"><span class="text-gray-700 text-xs uppercase tracking-wider">Outs</span><br><span class="text-gray-400">{{ $_orph['outs'] }}</span></div>
                    @endif
                    <div class="shrink-0">
                        <span class="text-gray-700 text-xs uppercase tracking-wider block mb-1">Runners</span>
                        <div class="base-diamond">
                            <div class="diamond third-base" style="background:{{ $_orph['base3'] ? '#3b82f6' : '#374151' }}"
                                @if($_orph['base3'] && $_orph['runner3']) title="{{ $_orph['runner3'] }}" @endif></div>
                            <div class="base-diamond-center">
                                <div class="diamond" style="background:{{ $_orph['base2'] ? '#3b82f6' : '#374151' }}"
                                    @if($_orph['base2'] && $_orph['runner2']) title="{{ $_orph['runner2'] }}" @endif></div>
                                <div style="height:8px"></div>
                            </div>
                            <div class="diamond first-base" style="background:{{ $_orph['base1'] ? '#3b82f6' : '#374151' }}"
                                @if($_orph['base1'] && $_orph['runner1']) title="{{ $_orph['runner1'] }}" @endif></div>
                        </div>
                    </div>
                    @if($_orph['pitcher'])
                    <div class="shrink-0"><span class="text-gray-700 text-xs uppercase tracking-wider">Pitcher</span><br><span class="text-gray-400">{{ $_orph['pitcher'] }}</span></div>
                    @endif
                </div>
                @if(!empty($_orph['pitches']))
                <div class="border-t border-gray-800/40 pt-2 space-y-0.5">
                    @foreach($_orph['pitches'] as $_p)
                    <p class="text-xs text-gray-400" style="font-family:'Roboto',sans-serif">{{ $_p }}</p>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endforeach

</div>
@endif

@endif {{-- $played --}}

@push('scripts')
<script>
function toggleAB(btn) {
    var detail = btn.nextElementSibling;
    var arrow  = btn.querySelector('.ab-arrow');
    if (detail.classList.contains('hidden')) {
        detail.classList.remove('hidden');
        arrow.textContent = '▼';
    } else {
        detail.classList.add('hidden');
        arrow.textContent = '▶';
    }
}
</script>
@endpush

@endsection
