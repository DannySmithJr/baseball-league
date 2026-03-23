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
    // Covers both pinch hitters AND defensive replacements, in chronological order
    // using game_logs as the authoritative substitution timeline.
    $phLetters = [$awayId => [], $homeId => []];
    $phNotes   = [$awayId => [], $homeId => []];
    $phAlpha   = [$awayId => 0, $homeId => 0];
    $defSubPlayers = [$awayId => [], $homeId => []];

    // Build starter set (first player in each batting slot)
    $starters = [$awayId => [], $homeId => []];
    foreach ([$awayId, $homeId] as $_tid) {
        foreach ($spotPlayers[$_tid] as $_slot => $_players) {
            if (!empty($_players)) {
                $starters[$_tid][(int)$_players[0]->player_id] = true;
            }
        }
    }

    // Build player_id → name map and player_id → slot/position from box batting
    $_pidName = [];
    $_pidSlot = [];
    $_pidPos  = [];
    foreach ([$awayId, $homeId] as $_tid) {
        foreach ($boxBatting[$_tid] ?? [] as $_b) {
            $_pidName[(int)$_b->player_id] = $fmtIni($_b->first_name, $_b->last_name);
            $_pidSlot[(int)$_b->player_id] = (int)$_b->bat_order;
            $_pidPos[(int)$_b->player_id]  = $fieldingPositions[(int)$_b->position] ?? '';
        }
    }

    // Map player_id → team_id from box batting
    $_pidTeam = [];
    foreach ([$awayId, $homeId] as $_tid) {
        foreach ($boxBatting[$_tid] ?? [] as $_b) {
            $_pidTeam[(int)$_b->player_id] = $_tid;
        }
    }

    // Parse game_logs chronologically for PH and defensive sub entries
    // Also track which player was replaced using slot history
    $_slotCurrent = [$awayId => [], $homeId => []]; // slot → current player_id
    foreach ([$awayId, $homeId] as $_tid) {
        foreach ($spotPlayers[$_tid] as $_slot => $_players) {
            if (!empty($_players)) {
                $_slotCurrent[$_tid][$_slot] = (int)$_players[0]->player_id;
            }
        }
    }

    $curInning = 1;
    foreach ($atBatLogs as $_abl) {
        $curInning = $_abl['inning'] ?? $curInning;
    }

    // Use game_logs type=2 entries in line order for chronological subs
    $_subLogs = \Illuminate\Support\Facades\DB::table('game_logs')
        ->where('game_id', $game->game_id)
        ->where('type', 2)
        ->orderBy('line')
        ->get(['line', 'text']);

    $_curInning = 1;
    $_curHalf = 'top';
    // Also get type=1 for inning tracking
    $_allLogs = \Illuminate\Support\Facades\DB::table('game_logs')
        ->where('game_id', $game->game_id)
        ->whereIn('type', [1, 2])
        ->orderBy('line')
        ->get(['line', 'type', 'text']);

    foreach ($_allLogs as $_log) {
        if ($_log->type == 1) {
            if (preg_match('/Top of the (\d+)/i', $_log->text, $_m)) { $_curInning = (int)$_m[1]; $_curHalf = 'top'; }
            elseif (preg_match('/Bottom of the (\d+)/i', $_log->text, $_m)) { $_curInning = (int)$_m[1]; $_curHalf = 'bottom'; }
            continue;
        }

        $cleanText = strip_tags($_log->text);

        // Pinch hitter
        if (str_contains($cleanText, 'Pinch Hitting:') && preg_match('/player_(\d+)\.html/', $_log->text, $_m)) {
            $_pid = (int)$_m[1];
            $_tid = $_pidTeam[$_pid] ?? null;
            if (!$_tid || isset($phLetters[$_tid][$_pid])) continue;

            $_slot = $_pidSlot[$_pid] ?? null;
            $_replacedPid = $_slot ? ($_slotCurrent[$_tid][$_slot] ?? null) : null;
            $_replacedName = $_replacedPid ? ($_pidName[$_replacedPid] ?? null) : null;

            $phLetters[$_tid][$_pid] = chr(ord('a') + $phAlpha[$_tid]++);
            $letter = $phLetters[$_tid][$_pid];
            $phNotes[$_tid][] = $letter . ' - ' . ($_pidName[$_pid] ?? '?') . ($_replacedName ? ' pinch hit for ' . $_replacedName : ' (PH)') . ' in the ' . $innLabel($_curInning);

            if ($_slot) $_slotCurrent[$_tid][$_slot] = $_pid;
        }

        // Defensive replacement: "Now in CF:", "Now at 1B:", etc.
        if (preg_match('/Now (?:in|at) \w+/i', $cleanText) && preg_match('/player_(\d+)\.html/', $_log->text, $_m)) {
            $_pid = (int)$_m[1];
            $_tid = $_pidTeam[$_pid] ?? null;
            if (!$_tid || isset($starters[$_tid][$_pid]) || isset($phLetters[$_tid][$_pid])) continue;

            $_slot = $_pidSlot[$_pid] ?? null;
            $_replacedPid = $_slot ? ($_slotCurrent[$_tid][$_slot] ?? null) : null;
            $_replacedName = $_replacedPid ? ($_pidName[$_replacedPid] ?? null) : null;
            $_defPos = $_pidPos[$_pid] ?? '';

            $phLetters[$_tid][$_pid] = chr(ord('a') + $phAlpha[$_tid]++);
            $defSubPlayers[$_tid][$_pid] = true;
            $letter = $phLetters[$_tid][$_pid];
            $phNotes[$_tid][] = $letter . ' - ' . ($_pidName[$_pid] ?? '?') . ($_replacedName ? ' entered as defensive replacement' . ($_defPos ? ' at ' . $_defPos : '') . ' for ' . $_replacedName : ' (defensive replacement)');

            if ($_slot) $_slotCurrent[$_tid][$_slot] = $_pid;
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

{{-- ── NAV TABS ── --}}
@if($played)
<div class="bg-gray-900 border-b border-gray-800">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 flex gap-6">
        <a href="{{ route('game', $game->game_id) }}"
           class="py-2.5 text-sm font-semibold border-b-2 border-red-500 text-white">Box Score</a>
        <a href="{{ route('game.logs', $game->game_id) }}"
           class="py-2.5 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-300 transition">Game Log</a>
    </div>
</div>
@endif

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
                                <span class="text-gray-600 ml-0.5">{{ $phLetter ? (isset($defSubPlayers[$tid][(int)$b->player_id]) ? $fieldPos : 'PH') : $fieldPos }}</span>
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
                            <th class="text-center px-1.5 py-1.5 font-medium">ERA</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40">
                        @foreach($pitchers as $pit)
                        @php
                            $decLabel = '';
                            $decRecord = '';
                            $_pStat = $pitSeasonStats[(int)$pit->player_id] ?? null;
                            if ($game->winning_pitcher == $pit->player_id) {
                                $decLabel = 'W';
                                if ($_pStat) $decRecord = '(' . $_pStat['w'] . '-' . $_pStat['l'] . ')';
                            } elseif ($game->losing_pitcher == $pit->player_id) {
                                $decLabel = 'L';
                                if ($_pStat) $decRecord = '(' . $_pStat['w'] . '-' . $_pStat['l'] . ')';
                            } elseif ($game->save_pitcher == $pit->player_id) {
                                $decLabel = 'SV';
                                if ($_pStat) $decRecord = '(' . $_pStat['s'] . ')';
                            } elseif ((int)$pit->hld > 0) {
                                $decLabel = 'H';
                            } elseif ((int)$pit->bs > 0) {
                                $decLabel = 'BS';
                            }
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
                                    @if($decRecord)<span class="text-gray-500 text-xs ml-0.5">{{ $decRecord }}</span>@endif
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
                            @php $pSeason = $pitSeasonStats[(int)$pit->player_id] ?? null; @endphp
                            <td class="text-center px-1.5 py-1.5 text-gray-400">{{ $pSeason ? $pSeason['era'] : '—' }}</td>
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

@endif {{-- $played --}}

@endsection
