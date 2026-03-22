@extends('layouts.public')
@section('title', $player->first_name . ' ' . $player->last_name)

@php
use App\Services\OotpService;

$posName = OotpService::POSITIONS[(int)$player->position] ?? '?';

$batLabel = match((int)$player->bats ?? 0) {
    1 => 'R', 2 => 'L', 3 => 'S', default => '?'
};
$throwLabel = match((int)$player->throws ?? 0) {
    1 => 'R', 2 => 'L', default => '?'
};

$fmtAvg = function($v) {
    $v = (float) $v;
    if ($v <= 0) return '.000';
    if ($v >= 1) return '1.000';
    return '.' . str_pad((string) round($v * 1000), 3, '0', STR_PAD_LEFT);
};

$fmtWar = function($v) {
    return number_format((float)$v, 1);
};

$awardNames = OotpService::AWARD_NAMES;

// Group career awards by year for display
$awardsByYear = [];
foreach ($awards as $aw) {
    $awardsByYear[(int)$aw->year][] = $aw;
}
krsort($awardsByYear);

// Career totals for bio strip
$careerWar = $isPitcher
    ? $careerPitching->sum('war')
    : $careerBatting->sum('war');
@endphp

@section('content')

{{-- ── Player Header ─────────────────────────────────────────────────────── --}}
@php
    $feet = (int)($player->height ?? 0) > 0 ? intdiv((int)$player->height, 12) : null;
    $inches = (int)($player->height ?? 0) > 0 ? (int)$player->height % 12 : null;
    $dob = $player->date_of_birth ? \Carbon\Carbon::parse($player->date_of_birth) : null;
    $birthplace = implode(', ', array_filter([$player->birth_city ?? null, $player->birth_state ?? null]));
    $seasonYear = $seasonBatting->year ?? $seasonPitching->year ?? null;
@endphp
<div class="border-b border-gray-800 bg-gray-900/50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
        <table class="w-full"><tr class="align-top">

            {{-- LEFT: Name, Team, Position --}}
            <td class="pr-16" style="width:1%;white-space:nowrap">
                <h1 class="leading-tight">
                    <span class="block text-2xl text-gray-300 font-light tracking-wide uppercase">{{ $player->first_name }}</span>
                    <span class="block text-4xl font-black text-white tracking-tight uppercase">{{ $player->last_name }}</span>
                </h1>
                @if($player->nick_name)
                    <p class="text-sm text-gray-500 italic mt-1">"{{ $player->nick_name }}"</p>
                @endif
                <div class="flex items-center gap-2 mt-2 text-sm">
                    @if($player->current_team_id && $player->team_name)
                        <a href="{{ route('team', $player->current_team_id) }}" class="text-red-400 hover:text-red-300 font-semibold transition">{{ $player->team_name }}</a>
                        <span class="text-gray-600">·</span>
                    @endif
                    @if($player->uniform_number)
                        <span class="text-gray-400">#{{ $player->uniform_number }}</span>
                        <span class="text-gray-600">·</span>
                    @endif
                    <span class="text-gray-400">{{ $posName }}</span>
                </div>
            </td>

            {{-- MIDDLE: Bio details --}}
            <td class="text-sm pr-10">
                <table class="border-separate" style="border-spacing:0 2px">
                    @if($feet)
                    <tr><td class="text-gray-500 pr-4">HT/WT</td><td class="text-white font-semibold">{{ $feet }}'{{ $inches }}", {{ $player->weight ?? '?' }} lbs</td></tr>
                    @endif
                    @if($dob)
                    <tr><td class="text-gray-500 pr-4">BIRTHDATE</td><td class="text-white font-semibold">{{ $dob->format('n/j/Y') }} ({{ $player->age }})</td></tr>
                    @endif
                    <tr><td class="text-gray-500 pr-4">BAT/THR</td><td class="text-white font-semibold">{{ $batLabel }}/{{ $throwLabel }}</td></tr>
                    @if($birthplace)
                    <tr><td class="text-gray-500 pr-4">BIRTHPLACE</td><td class="text-white font-semibold">{{ $birthplace }}</td></tr>
                    @endif
                    <tr><td class="text-gray-500 pr-4">STATUS</td><td>
                        @if($player->retired ?? 0)
                            <span class="text-red-400 font-semibold">Retired</span>
                        @elseif($player->injury_is_injured ?? 0)
                            <span class="text-red-400 font-semibold">● Injured</span>
                        @else
                            <span class="text-green-400 font-semibold">● Active</span>
                        @endif
                    </td></tr>
                </table>
            </td>

            {{-- RIGHT: Season Stats Box --}}
            <td class="pl-10" style="width:1%;white-space:nowrap">
                @if($seasonBatting || $seasonPitching)
                <div class="bg-gray-800/50 border border-gray-700 rounded-xl overflow-hidden">
                    <div class="bg-red-600 px-4 py-1.5 text-center">
                        <p class="text-[11px] font-bold text-white uppercase tracking-wider">{{ $seasonYear }} Season Stats</p>
                    </div>
                    @if($seasonPitching)
                    @php $s = $seasonPitching; @endphp
                    <div class="flex divide-x divide-gray-700">
                        <div class="text-center px-5 py-3"><p class="text-xs text-gray-500">W-L</p><p class="text-xl font-black text-white">{{ (int)$s->w }}-{{ (int)$s->l }}</p>@if($headerRanks['wl'] ?? null)<p class="text-[10px] text-gray-500">{{ $headerRanks['wl'] }}</p>@endif</div>
                        <div class="text-center px-5 py-3"><p class="text-xs text-gray-500">ERA</p><p class="text-xl font-black text-white">{{ $s->era }}</p>@if($headerRanks['era'] ?? null)<p class="text-[10px] text-gray-500">{{ $headerRanks['era'] }}</p>@endif</div>
                        <div class="text-center px-5 py-3"><p class="text-xs text-gray-500">K</p><p class="text-xl font-black text-white">{{ (int)$s->k }}</p>@if($headerRanks['k'] ?? null)<p class="text-[10px] text-gray-500">{{ $headerRanks['k'] }}</p>@endif</div>
                        <div class="text-center px-5 py-3"><p class="text-xs text-gray-500">WHIP</p><p class="text-xl font-black text-white">{{ $s->whip }}</p>@if($headerRanks['whip'] ?? null)<p class="text-[10px] text-gray-500">{{ $headerRanks['whip'] }}</p>@endif</div>
                    </div>
                    @elseif($seasonBatting)
                    @php $s = $seasonBatting; @endphp
                    <div class="flex divide-x divide-gray-700">
                        <div class="text-center px-5 py-3"><p class="text-xs text-gray-500">AVG</p><p class="text-xl font-black text-white">{{ $fmtAvg($s->avg) }}</p>@if($headerRanks['avg'] ?? null)<p class="text-[10px] text-gray-500">{{ $headerRanks['avg'] }}</p>@endif</div>
                        <div class="text-center px-5 py-3"><p class="text-xs text-gray-500">HR</p><p class="text-xl font-black text-white">{{ (int)$s->hr }}</p>@if($headerRanks['hr'] ?? null)<p class="text-[10px] text-gray-500">{{ $headerRanks['hr'] }}</p>@endif</div>
                        <div class="text-center px-5 py-3"><p class="text-xs text-gray-500">RBI</p><p class="text-xl font-black text-white">{{ (int)$s->rbi }}</p>@if($headerRanks['rbi'] ?? null)<p class="text-[10px] text-gray-500">{{ $headerRanks['rbi'] }}</p>@endif</div>
                        <div class="text-center px-5 py-3"><p class="text-xs text-gray-500">OPS</p><p class="text-xl font-black text-white">{{ $fmtAvg($s->ops) }}</p>@if($headerRanks['ops'] ?? null)<p class="text-[10px] text-gray-500">{{ $headerRanks['ops'] }}</p>@endif</div>
                    </div>
                    @endif
                </div>
                @endif
            </td>

        </tr></table>
    </div>
</div>

{{-- ── Player Nav ──────────────────────────────────────────────────────────── --}}
<div class="border-b border-gray-800 bg-gray-900/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <nav class="flex gap-1 -mb-px overflow-x-auto">
            @php
                $playerNav = [
                    ['hash' => '#overview', 'label' => 'Overview'],
                    ['hash' => '#news',     'label' => 'News'],
                    ['hash' => '#stats',    'label' => 'Stats'],
                    ['hash' => '#ratings',  'label' => 'Ratings'],
                    ['hash' => '#bio',      'label' => 'Bio'],
                    ['hash' => '#splits',   'label' => 'Splits'],
                    ['hash' => '#gamelog',  'label' => 'Game Log'],
                ];
                $isCcpOwner = auth()->check() && auth()->user()->hasCcp() && auth()->user()->ccp()?->player_id === $player->player_id;
            @endphp
            @foreach($playerNav as $nav)
            <a href="{{ $nav['hash'] }}"
               class="whitespace-nowrap px-4 py-3 text-sm font-medium border-b-2 transition
                      border-transparent text-gray-400 hover:text-white hover:border-gray-600">
                {{ $nav['label'] }}
            </a>
            @endforeach
            @if($isCcpOwner)
            <a href="#manage"
               class="whitespace-nowrap px-4 py-3 text-sm font-medium border-b-2 transition
                      border-transparent text-red-400 hover:text-red-300 hover:border-red-600">
                Manage
            </a>
            @endif
        </nav>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-10">

    {{-- ── This Season ──────────────────────────────────────────────────────── --}}
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
                        <th class="py-2.5 px-3">SLG</th>
                        <th class="py-2.5 px-3">OPS</th>
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
                    <tr class="border-t border-gray-800/50 hover:bg-gray-800/30 transition">
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
                        <th class="py-2.5 px-3">ERA</th>
                        <th class="py-2.5 px-3">WHIP</th>
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
                    <tr class="border-t border-gray-800/50 hover:bg-gray-800/30 transition">
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

    {{-- ── News ──────────────────────────────────────────────────────────────── --}}
    @if($news->isNotEmpty())
    <section id="news">
        <h2 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-3">News</h2>
        <div class="space-y-3">
            @php
                $newsSources = DB::table('news_sources')->where('active', true)->get();
                $leagueSource = $newsSources->firstWhere('team_id', null);
                $teamSources  = $newsSources->whereNotNull('team_id')->keyBy('team_id');

                // Team logos for news cards
                $seasonYear = app(\App\Services\OotpService::class)->seasonYear() ?? (int)date('Y');
                $newsTeamLogos = [];
                foreach (DB::table('teams')->where('level', 1)->get(['team_id', 'logo_file_name']) as $t) {
                    $base = pathinfo($t->logo_file_name ?? '', PATHINFO_FILENAME);
                    $newsTeamLogos[(int)$t->team_id] = \App\Services\OotpService::logoForYear($base, $seasonYear);
                }
            @endphp
            @foreach($news as $article)
            @php
                $articleTeamId = (int)$article->team_id_0;
                $source = $teamSources[$articleTeamId] ?? $leagueSource ?? null;
                $teamLogo = $newsTeamLogos[$articleTeamId] ?? null;
                $parsedBody = \App\Services\OotpService::parseMessageBody($article->body);
            @endphp
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
                <div class="flex items-start gap-3">
                    {{-- Team logo or league icon --}}
                    <div class="shrink-0">
                        @if($teamLogo)
                            <img src="/images/logos/{{ $teamLogo }}" class="w-10 h-10 object-contain" alt="">
                        @else
                            <div class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center text-red-500">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>
                            </div>
                        @endif
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-0.5">
                            <span class="text-xs font-bold text-gray-400 uppercase">
                                @if($articleTeamId && isset($newsTeamLogos[$articleTeamId]))
                                    {{ DB::table('teams')->where('team_id', $articleTeamId)->value('nickname') ?? '' }}
                                @else
                                    {{ $settings->league_abbr ?? 'sMLB' }}
                                @endif
                            </span>
                        </div>
                        <p class="text-sm text-gray-300 leading-relaxed mb-2">{!! $parsedBody !!}</p>
                        <div class="flex items-center gap-2">
                            <span class="text-[11px] text-gray-500 font-medium">{{ $source->name ?? $leagueSource->name ?? 'BNN' }}</span>
                            <span class="text-[11px] text-gray-600">{{ \Carbon\Carbon::parse($article->date)->format('M j, Y') }}</span>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </section>
    @endif

    {{-- ── Ratings ──────────────────────────────────────────────────────────── --}}
    @if($ratings)
    @php
        // Ratings are stored on 1-100 scale (OOTP setting), display as-is
        $toScout = fn($v) => (int)$v;

        // OOTP color scale (1-100)
        $ratingColor = fn($v) => match(true) {
            $v >= 80 => 'ootp-80',
            $v >= 70 => 'ootp-70',
            $v >= 60 => 'ootp-60',
            $v >= 50 => 'ootp-50',
            $v >= 40 => 'ootp-40',
            $v >= 30 => 'ootp-30',
            default  => 'ootp-20',
        };

        $armSlots = [1 => 'Normal', 2 => 'Sidearm', 3 => 'Over the Top', 4 => 'Submarine'];
    @endphp
    <section>
        <h2 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-3">Ratings</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">

            {{-- Batting Ratings --}}
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="py-2 px-3 text-left">Batting</th>
                            <th class="py-2 px-3 text-center w-12">Con</th>
                            <th class="py-2 px-3 text-center w-12">Gap</th>
                            <th class="py-2 px-3 text-center w-12">Pow</th>
                            <th class="py-2 px-3 text-center w-12">Eye</th>
                            <th class="py-2 px-3 text-center w-12">K's</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40">
                        @foreach([
                            ['label' => 'Current', 'prefix' => 'batting_ratings_overall_'],
                            ['label' => 'vs R',    'prefix' => 'batting_ratings_vsr_'],
                            ['label' => 'vs L',    'prefix' => 'batting_ratings_vsl_'],
                            ['label' => 'Potential','prefix' => 'batting_ratings_talent_'],
                        ] as $row)
                        @php
                            $con = $toScout($ratings->{$row['prefix'].'contact'});
                            $gap = $toScout($ratings->{$row['prefix'].'gap'});
                            $pow = $toScout($ratings->{$row['prefix'].'power'});
                            $eye = $toScout($ratings->{$row['prefix'].'eye'});
                            $ks  = $toScout($ratings->{$row['prefix'].'strikeouts'});
                        @endphp
                        <tr>
                            <td class="py-1.5 px-3 text-gray-400 text-xs">{{ $row['label'] }}</td>
                            <td class="py-1.5 px-3 text-center {{ $ratingColor($con) }}">{{ $con }}</td>
                            <td class="py-1.5 px-3 text-center {{ $ratingColor($gap) }}">{{ $gap }}</td>
                            <td class="py-1.5 px-3 text-center {{ $ratingColor($pow) }}">{{ $pow }}</td>
                            <td class="py-1.5 px-3 text-center {{ $ratingColor($eye) }}">{{ $eye }}</td>
                            <td class="py-1.5 px-3 text-center {{ $ratingColor($ks) }}">{{ $ks }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pitching Ratings --}}
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="py-2 px-3 text-left">Pitching</th>
                            <th class="py-2 px-3 text-center w-14">Stuff</th>
                            <th class="py-2 px-3 text-center w-14">Move</th>
                            <th class="py-2 px-3 text-center w-14">Ctrl</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40">
                        @foreach([
                            ['label' => 'Current', 'prefix' => 'pitching_ratings_overall_'],
                            ['label' => 'vs R',    'prefix' => 'pitching_ratings_vsr_'],
                            ['label' => 'vs L',    'prefix' => 'pitching_ratings_vsl_'],
                            ['label' => 'Potential','prefix' => 'pitching_ratings_talent_'],
                        ] as $row)
                        @php
                            $stuff = $toScout($ratings->{$row['prefix'].'stuff'});
                            $move  = $toScout($ratings->{$row['prefix'].'movement'});
                            $ctrl  = $toScout($ratings->{$row['prefix'].'control'});
                        @endphp
                        <tr>
                            <td class="py-1.5 px-3 text-gray-400 text-xs">{{ $row['label'] }}</td>
                            <td class="py-1.5 px-3 text-center {{ $ratingColor($stuff) }}">{{ $stuff }}</td>
                            <td class="py-1.5 px-3 text-center {{ $ratingColor($move) }}">{{ $move }}</td>
                            <td class="py-1.5 px-3 text-center {{ $ratingColor($ctrl) }}">{{ $ctrl }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Fielding --}}
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="py-2 px-3 text-left">Fielding</th>
                            <th class="py-2 px-3 text-center w-10">C</th>
                            <th class="py-2 px-3 text-center w-10">IF</th>
                            <th class="py-2 px-3 text-center w-10">OF</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40">
                        @php
                            $cArm = $toScout($ratings->fielding_ratings_catcher_arm);
                            $cAbi = $toScout($ratings->fielding_ratings_catcher_ability);
                            $cFrm = $toScout($ratings->fielding_ratings_catcher_framing);
                            $ifRng = $toScout($ratings->fielding_ratings_infield_range);
                            $ifArm = $toScout($ratings->fielding_ratings_infield_arm);
                            $ifErr = $toScout($ratings->fielding_ratings_infield_error);
                            $tdp   = $toScout($ratings->fielding_ratings_turn_doubleplay);
                            $ofRng = $toScout($ratings->fielding_ratings_outfield_range);
                            $ofArm = $toScout($ratings->fielding_ratings_outfield_arm);
                            $ofErr = $toScout($ratings->fielding_ratings_outfield_error);
                        @endphp
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Range</td><td class="py-1.5 px-3 text-center {{ $ratingColor($cAbi) }}">{{ $cAbi ?: '' }}</td><td class="py-1.5 px-3 text-center {{ $ratingColor($ifRng) }}">{{ $ifRng }}</td><td class="py-1.5 px-3 text-center {{ $ratingColor($ofRng) }}">{{ $ofRng }}</td></tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Error</td><td class="py-1.5 px-3 text-center"></td><td class="py-1.5 px-3 text-center {{ $ratingColor($ifErr) }}">{{ $ifErr }}</td><td class="py-1.5 px-3 text-center {{ $ratingColor($ofErr) }}">{{ $ofErr }}</td></tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Arm</td><td class="py-1.5 px-3 text-center {{ $ratingColor($cArm) }}">{{ $cArm ?: '' }}</td><td class="py-1.5 px-3 text-center {{ $ratingColor($ifArm) }}">{{ $ifArm }}</td><td class="py-1.5 px-3 text-center {{ $ratingColor($ofArm) }}">{{ $ofArm }}</td></tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Turn DP</td><td class="py-1.5 px-3 text-center"></td><td class="py-1.5 px-3 text-center {{ $ratingColor($tdp) }}">{{ $tdp }}</td><td class="py-1.5 px-3 text-center"></td></tr>
                        @if($cArm > 0)
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">C Block</td><td class="py-1.5 px-3 text-center {{ $ratingColor($cAbi) }}">{{ $cAbi }}</td><td class="py-1.5 px-3 text-center"></td><td class="py-1.5 px-3 text-center"></td></tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">C Frame</td><td class="py-1.5 px-3 text-center {{ $ratingColor($cFrm) }}">{{ $cFrm }}</td><td class="py-1.5 px-3 text-center"></td><td class="py-1.5 px-3 text-center"></td></tr>
                        @endif
                    </tbody>
                </table>
            </div>

            {{-- Position Ratings --}}
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="py-2 px-3 text-left">Position</th>
                            <th class="py-2 px-3 text-center w-20">Cur / Pot</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40">
                        @php
                            $posNames = [1=>'Pitcher',2=>'Catcher',3=>'First',4=>'Second',5=>'Third',6=>'Short',7=>'Left',8=>'Center',9=>'Right'];
                        @endphp
                        @for($pi = 1; $pi <= 9; $pi++)
                        @php
                            $cur = $toScout($ratings->{'fielding_rating_pos'.$pi} ?? 0);
                            $pot = $toScout($ratings->{'fielding_rating_pos'.$pi.'_pot'} ?? 0);
                        @endphp
                        @if($cur > 0 || $pot > 0)
                        <tr>
                            <td class="py-1.5 px-3 text-gray-400 text-xs">{{ $posNames[$pi] ?? $pi }}</td>
                            <td class="py-1.5 px-3 text-center">
                                <span class="{{ $ratingColor($cur) }}">{{ $cur }}</span>
                                <span class="text-gray-600"> / </span>
                                <span class="{{ $ratingColor($pot) }}">{{ $pot }}</span>
                            </td>
                        </tr>
                        @endif
                        @endfor
                    </tbody>
                </table>
            </div>

            {{-- Run/Bunt --}}
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="py-2 px-3 text-left" colspan="2">Run / Bunt</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40">
                        @php
                            $spd = $toScout($ratings->running_ratings_speed);
                            $stlAgg = $toScout($ratings->running_ratings_stealing_rate);
                            $stl = $toScout($ratings->running_ratings_stealing);
                            $run = $toScout($ratings->running_ratings_baserunning);
                            $bunt = $toScout($ratings->batting_ratings_misc_bunt);
                            $bfh  = $toScout($ratings->batting_ratings_misc_bunt_for_hit);
                        @endphp
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Speed</td><td class="py-1.5 px-3 text-right {{ $ratingColor($spd) }}">{{ $spd }}</td></tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Stl Aggr</td><td class="py-1.5 px-3 text-right {{ $ratingColor($stlAgg) }}">{{ $stlAgg }}</td></tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Stealing</td><td class="py-1.5 px-3 text-right {{ $ratingColor($stl) }}">{{ $stl }}</td></tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Running</td><td class="py-1.5 px-3 text-right {{ $ratingColor($run) }}">{{ $run }}</td></tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Sac Bunt</td><td class="py-1.5 px-3 text-right {{ $ratingColor($bunt) }}">{{ $bunt }}</td></tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Bunt for Hit</td><td class="py-1.5 px-3 text-right {{ $ratingColor($bfh) }}">{{ $bfh }}</td></tr>
                    </tbody>
                </table>
            </div>

            {{-- Pitch Ratings + Other Pitching --}}
            @if($isPitcher)
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="py-2 px-3 text-left">Pitch</th>
                            <th class="py-2 px-3 text-center w-12">Cur</th>
                            <th class="py-2 px-3 text-center w-12">Pot</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40">
                        @php
                            $pitchNames = [
                                'fastball'=>'Fastball','slider'=>'Slider','curveball'=>'Curveball',
                                'screwball'=>'Screwball','forkball'=>'Forkball','changeup'=>'Changeup',
                                'sinker'=>'Sinker','splitter'=>'Splitter','knuckleball'=>'Knuckleball',
                                'cutter'=>'Cutter','circlechange'=>'Circle Change','knucklecurve'=>'Knuckle Curve',
                            ];
                        @endphp
                        @foreach($pitchNames as $key => $label)
                        @php
                            $cur = $toScout($ratings->{'pitching_ratings_pitches_'.$key} ?? 0);
                            $pot = $toScout($ratings->{'pitching_ratings_pitches_talent_'.$key} ?? 0);
                        @endphp
                        @if($cur > 20 || $pot > 20)
                        <tr>
                            <td class="py-1.5 px-3 text-gray-400 text-xs">{{ $label }}</td>
                            <td class="py-1.5 px-3 text-center {{ $ratingColor($cur) }}">{{ $cur }}</td>
                            <td class="py-1.5 px-3 text-center {{ $ratingColor($pot) }}">{{ $pot }}</td>
                        </tr>
                        @endif
                        @endforeach
                        <tr class="border-t border-gray-700">
                            <td colspan="3" class="py-1 px-3"></td>
                        </tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Velocity</td><td colspan="2" class="py-1.5 px-3 text-right text-white">{{ $ratings->pitching_ratings_misc_velocity }}-{{ $ratings->pitching_ratings_misc_velocity_target }} (Max)</td></tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">GB %</td><td colspan="2" class="py-1.5 px-3 text-right text-white">{{ $ratings->pitching_ratings_misc_ground_fly }}%</td></tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Arm Slot</td><td colspan="2" class="py-1.5 px-3 text-right text-white">{{ $armSlots[$ratings->pitching_ratings_misc_arm_slot] ?? 'Normal' }}</td></tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Stamina</td><td colspan="2" class="py-1.5 px-3 text-right {{ $ratingColor($toScout($ratings->pitching_ratings_misc_stamina)) }}">{{ $toScout($ratings->pitching_ratings_misc_stamina) }}</td></tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Hold</td><td colspan="2" class="py-1.5 px-3 text-right {{ $ratingColor($toScout($ratings->pitching_ratings_misc_hold)) }}">{{ $toScout($ratings->pitching_ratings_misc_hold) }}</td></tr>
                    </tbody>
                </table>
            </div>
            @endif

        </div>

        {{-- OSA Overall --}}
        <div class="mt-3 text-xs text-gray-500">
            OSA Ovr <span class="text-white font-bold">{{ $toScout($ratings->overall) }}</span>
            Pot <span class="text-white font-bold">{{ $toScout($ratings->talent) }}</span>
        </div>
    </section>
    @endif

    {{-- ── Career Stats — split MLB / Minors ────────────────────────────────── --}}
    @if($careerBatting->isNotEmpty() || $careerPitching->isNotEmpty())
    @php
        $levelLabels = [1 => 'MLB', 2 => 'AAA', 3 => 'AA', 4 => 'A+', 5 => 'A', 6 => 'Rk'];

        // Split career data by MLB (level=1) vs Minors (level!=1)
        $mlbBatting    = $careerBatting->filter(fn($r) => (int)($r->team_level ?? 0) === 1);
        $minorsBatting = $careerBatting->filter(fn($r) => (int)($r->team_level ?? 0) !== 1);
        $mlbPitching    = $careerPitching->filter(fn($r) => (int)($r->team_level ?? 0) === 1);
        $minorsPitching = $careerPitching->filter(fn($r) => (int)($r->team_level ?? 0) !== 1);

        // Totals helper
        $batTotals = function ($rows, $label = 'Career') use ($fmtAvg, $fmtWar) {
            $g=$rows->sum(fn($r)=>(int)$r->g); $ab=$rows->sum(fn($r)=>(int)$r->ab);
            $r2=$rows->sum(fn($r)=>(int)$r->r); $h=$rows->sum(fn($r)=>(int)$r->h);
            $d=$rows->sum(fn($r)=>(int)$r->d); $t=$rows->sum(fn($r)=>(int)$r->t_triples);
            $hr=$rows->sum(fn($r)=>(int)$r->hr); $rbi=$rows->sum(fn($r)=>(int)$r->rbi);
            $bb=$rows->sum(fn($r)=>(int)$r->bb); $k=$rows->sum(fn($r)=>(int)$r->k);
            $sb=$rows->sum(fn($r)=>(int)$r->sb); $hp=$rows->sum(fn($r)=>(int)$r->hp);
            $sf=$rows->sum(fn($r)=>(int)$r->sf); $war=$rows->sum('war');
            $avg=$ab>0?$h/$ab:0; $obp=($ab+$bb+$hp+$sf)>0?($h+$bb+$hp)/($ab+$bb+$hp+$sf):0;
            $slg=$ab>0?(($h-$d-$t-$hr)+2*$d+3*$t+4*$hr)/$ab:0; $ops=$obp+$slg;
            return compact('label','g','ab','r2','h','d','t','hr','rbi','bb','k','sb','war','avg','obp','slg','ops');
        };

        $pitTotals = function ($rows, $label = 'Career') use ($fmtWar) {
            $g=$rows->sum(fn($r)=>(int)$r->g); $gs=$rows->sum(fn($r)=>(int)$r->gs);
            $w=$rows->sum(fn($r)=>(int)$r->w); $l=$rows->sum(fn($r)=>(int)$r->l);
            $sv=$rows->sum(fn($r)=>(int)$r->sv); $hld=$rows->sum(fn($r)=>(int)$r->hld);
            $outs=$rows->sum(fn($r)=>(int)$r->outs); $h=$rows->sum(fn($r)=>(int)$r->h);
            $er=$rows->sum(fn($r)=>(int)$r->er); $bb=$rows->sum(fn($r)=>(int)$r->bb);
            $k=$rows->sum(fn($r)=>(int)$r->k); $hr=$rows->sum(fn($r)=>(int)$r->hr);
            $war=$rows->sum('war'); $ip=$outs/3;
            $ipD=floor($outs/3).'.'.($outs%3);
            $era=$ip>0?number_format(($er/$ip)*9,2):'—';
            $whip=$ip>0?number_format(($h+$bb)/$ip,2):'—';
            return compact('label','g','gs','w','l','sv','hld','outs','ipD','h','er','bb','k','hr','war','era','whip');
        };
    @endphp

    <section class="space-y-6">

        {{-- ══ BATTING CAREER — MLB then Minors then Level Totals ══ --}}
        @foreach([
            ['label' => 'Career Major League Stats', 'rows' => $mlbBatting],
            ['label' => 'Career Minor League Stats', 'rows' => $minorsBatting],
        ] as $careerSection)
        @if($careerSection['rows']->isNotEmpty())
        <div>
            <h2 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-3">{{ $careerSection['label'] }}</h2>
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead>
                        <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="py-2.5 px-3 text-left">Team</th>
                            <th class="py-2.5 px-3">Year</th>
                            <th class="py-2.5 px-3">G</th><th class="py-2.5 px-3">AB</th>
                            <th class="py-2.5 px-3">R</th><th class="py-2.5 px-3">H</th>
                            <th class="py-2.5 px-3">2B</th><th class="py-2.5 px-3">3B</th>
                            <th class="py-2.5 px-3">HR</th><th class="py-2.5 px-3">RBI</th>
                            <th class="py-2.5 px-3">BB</th><th class="py-2.5 px-3">K</th>
                            <th class="py-2.5 px-3">SB</th>
                            <th class="py-2.5 px-3">AVG</th><th class="py-2.5 px-3">OBP</th>
                            <th class="py-2.5 px-3">SLG</th>
                            <th class="py-2.5 px-3">OPS</th>
                            <th class="py-2.5 px-3">WAR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $groupedRows = $careerSection['rows']->groupBy(fn($r) => (int)($r->team_level ?? 0))->sortKeys();
                            $hasMultipleLevels = $groupedRows->count() > 1;
                        @endphp
                        @foreach($groupedRows as $lvl => $levelRows)
                        @foreach($levelRows as $row)
                        @php
                            $isRowMlb = (int)($row->team_level ?? 0) === 1;
                            $yrLdr = $isRowMlb ? ($mlbLeadersByYear[$row->year] ?? []) : [];
                            $yrHrLead  = $isRowMlb && (int)$row->hr > 0 && (int)$row->hr >= ($yrLdr['hr'] ?? 999);
                            $yrRbiLead = $isRowMlb && (int)$row->rbi > 0 && (int)$row->rbi >= ($yrLdr['rbi'] ?? 999);
                            $yrHLead   = $isRowMlb && (int)$row->h > 0 && (int)$row->h >= ($yrLdr['h'] ?? 999);
                            $yrSbLead  = $isRowMlb && (int)$row->sb > 0 && (int)$row->sb >= ($yrLdr['sb'] ?? 999);
                            $yrAvgLead = $isRowMlb && (int)$row->ab >= 100 && abs($row->avg - ($yrLdr['avg'] ?? 0)) < 0.001;
                            $teamLabel = ($row->team_name ?? '') . ($row->league_abbr ? ' - ' . $row->league_abbr : '');
                        @endphp
                        <tr class="border-t border-gray-800/50 hover:bg-gray-800/30 transition">
                            <td class="py-2 px-3 text-left text-gray-400">{{ $teamLabel ?: '—' }}</td>
                            <td class="py-2 px-3 font-semibold text-white">{{ $row->year }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->g }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->ab }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->r }}</td>
                            <td class="py-2 px-3 {{ $yrHLead ? 'text-yellow-400 font-bold' : 'text-gray-300' }}">{{ (int)$row->h }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->d }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->t_triples }}</td>
                            <td class="py-2 px-3 {{ $yrHrLead ? 'text-yellow-400 font-bold' : 'text-gray-300' }}">{{ (int)$row->hr }}</td>
                            <td class="py-2 px-3 {{ $yrRbiLead ? 'text-yellow-400 font-bold' : 'text-gray-300' }}">{{ (int)$row->rbi }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->bb }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->k }}</td>
                            <td class="py-2 px-3 {{ $yrSbLead ? 'text-yellow-400 font-bold' : 'text-gray-300' }}">{{ (int)$row->sb }}</td>
                            <td class="py-2 px-3 {{ $yrAvgLead ? 'text-yellow-400 font-bold' : 'text-white font-semibold' }}">{{ $fmtAvg($row->avg) }}</td>
                            <td class="py-2 px-3 text-white font-semibold">{{ $fmtAvg($row->obp) }}</td>
                            <td class="py-2 px-3 text-white font-semibold">{{ $fmtAvg($row->slg) }}</td>
                            <td class="py-2 px-3 font-semibold text-white">{{ $fmtAvg($row->ops) }}</td>
                            <td class="py-2 px-3 font-semibold text-gray-300">{{ $fmtWar($row->war) }}</td>
                        </tr>
                        @endforeach
                        {{-- Level subtotal when multiple levels in minors --}}
                        @if($hasMultipleLevels)
                        @php $lvlTot = $batTotals($levelRows, 'Total - ' . ($levelLabels[$lvl] ?? 'Level '.$lvl)); @endphp
                        <tr class="border-t border-gray-700 bg-gray-800/20 font-semibold text-gray-300">
                            <td class="py-2 px-3 text-left text-xs text-gray-500 uppercase tracking-wider">{{ $lvlTot['label'] }}</td>
                            <td class="py-2 px-3 text-gray-600">—</td>
                            <td class="py-2 px-3">{{ $lvlTot['g'] }}</td><td class="py-2 px-3">{{ $lvlTot['ab'] }}</td>
                            <td class="py-2 px-3">{{ $lvlTot['r2'] }}</td><td class="py-2 px-3">{{ $lvlTot['h'] }}</td>
                            <td class="py-2 px-3">{{ $lvlTot['d'] }}</td><td class="py-2 px-3">{{ $lvlTot['t'] }}</td>
                            <td class="py-2 px-3">{{ $lvlTot['hr'] }}</td><td class="py-2 px-3">{{ $lvlTot['rbi'] }}</td>
                            <td class="py-2 px-3">{{ $lvlTot['bb'] }}</td><td class="py-2 px-3">{{ $lvlTot['k'] }}</td>
                            <td class="py-2 px-3">{{ $lvlTot['sb'] }}</td>
                            <td class="py-2 px-3">{{ $fmtAvg($lvlTot['avg']) }}</td><td class="py-2 px-3">{{ $fmtAvg($lvlTot['obp']) }}</td>
                            <td class="py-2 px-3">{{ $fmtAvg($lvlTot['slg']) }}</td><td class="py-2 px-3">{{ $fmtAvg($lvlTot['ops']) }}</td>
                            <td class="py-2 px-3">{{ $fmtWar($lvlTot['war']) }}</td>
                        </tr>
                        @endif
                        @endforeach
                        {{-- Overall section totals --}}
                        @php $tot = $batTotals($careerSection['rows'], 'Total'); @endphp
                        <tr class="border-t-2 border-gray-700 bg-gray-800/30 font-bold text-white">
                            <td class="py-2.5 px-3 text-left text-xs text-gray-400 uppercase tracking-wider">{{ $tot['label'] }}</td>
                            <td class="py-2.5 px-3 text-gray-500">—</td>
                            <td class="py-2.5 px-3">{{ $tot['g'] }}</td><td class="py-2.5 px-3">{{ $tot['ab'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['r2'] }}</td><td class="py-2.5 px-3">{{ $tot['h'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['d'] }}</td><td class="py-2.5 px-3">{{ $tot['t'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['hr'] }}</td><td class="py-2.5 px-3">{{ $tot['rbi'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['bb'] }}</td><td class="py-2.5 px-3">{{ $tot['k'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['sb'] }}</td>
                            <td class="py-2.5 px-3">{{ $fmtAvg($tot['avg']) }}</td><td class="py-2.5 px-3">{{ $fmtAvg($tot['obp']) }}</td>
                            <td class="py-2.5 px-3">{{ $fmtAvg($tot['slg']) }}</td><td class="py-2.5 px-3">{{ $fmtAvg($tot['ops']) }}</td>
                            <td class="py-2.5 px-3">{{ $fmtWar($tot['war']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        @endif
        @endforeach

        {{-- ══ BATTING CAREER TOTALS BY LEVEL ══ --}}
        @if($careerBatting->isNotEmpty())
        @php
            $batLevelGroups = $careerBatting->groupBy(fn($r) => (int)($r->team_level ?? 0))->sortKeys();
        @endphp
        @if($batLevelGroups->count() > 1)
        <div>
            <h2 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-3">Career Totals by Level</h2>
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead>
                        <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="py-2.5 px-3 text-left">Level</th>
                            <th class="py-2.5 px-3">G</th><th class="py-2.5 px-3">AB</th>
                            <th class="py-2.5 px-3">R</th><th class="py-2.5 px-3">H</th>
                            <th class="py-2.5 px-3">2B</th><th class="py-2.5 px-3">3B</th>
                            <th class="py-2.5 px-3">HR</th><th class="py-2.5 px-3">RBI</th>
                            <th class="py-2.5 px-3">BB</th><th class="py-2.5 px-3">K</th>
                            <th class="py-2.5 px-3">SB</th>
                            <th class="py-2.5 px-3">AVG</th><th class="py-2.5 px-3">OBP</th>
                            <th class="py-2.5 px-3">SLG</th>
                            <th class="py-2.5 px-3">OPS</th>
                            <th class="py-2.5 px-3">WAR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($batLevelGroups as $lvl => $levelRows)
                        @php $lvlTot = $batTotals($levelRows, $levelLabels[$lvl] ?? 'Level '.$lvl); @endphp
                        <tr class="border-t border-gray-800/50 hover:bg-gray-800/30 transition">
                            <td class="py-2 px-3 text-left font-semibold text-white">{{ $lvlTot['label'] }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $lvlTot['g'] }}</td><td class="py-2 px-3 text-gray-300">{{ $lvlTot['ab'] }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $lvlTot['r2'] }}</td><td class="py-2 px-3 text-gray-300">{{ $lvlTot['h'] }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $lvlTot['d'] }}</td><td class="py-2 px-3 text-gray-300">{{ $lvlTot['t'] }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $lvlTot['hr'] }}</td><td class="py-2 px-3 text-gray-300">{{ $lvlTot['rbi'] }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $lvlTot['bb'] }}</td><td class="py-2 px-3 text-gray-300">{{ $lvlTot['k'] }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $lvlTot['sb'] }}</td>
                            <td class="py-2 px-3 text-white font-semibold">{{ $fmtAvg($lvlTot['avg']) }}</td>
                            <td class="py-2 px-3 text-white font-semibold">{{ $fmtAvg($lvlTot['obp']) }}</td>
                            <td class="py-2 px-3 text-white font-semibold">{{ $fmtAvg($lvlTot['slg']) }}</td>
                            <td class="py-2 px-3 text-white font-semibold">{{ $fmtAvg($lvlTot['ops']) }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $fmtWar($lvlTot['war']) }}</td>
                        </tr>
                        @endforeach
                        @php $careerTot = $batTotals($careerBatting, 'Career'); @endphp
                        <tr class="border-t-2 border-gray-700 bg-gray-800/30 font-bold text-white">
                            <td class="py-2.5 px-3 text-left text-xs text-gray-400 uppercase tracking-wider">{{ $careerTot['label'] }}</td>
                            <td class="py-2.5 px-3">{{ $careerTot['g'] }}</td><td class="py-2.5 px-3">{{ $careerTot['ab'] }}</td>
                            <td class="py-2.5 px-3">{{ $careerTot['r2'] }}</td><td class="py-2.5 px-3">{{ $careerTot['h'] }}</td>
                            <td class="py-2.5 px-3">{{ $careerTot['d'] }}</td><td class="py-2.5 px-3">{{ $careerTot['t'] }}</td>
                            <td class="py-2.5 px-3">{{ $careerTot['hr'] }}</td><td class="py-2.5 px-3">{{ $careerTot['rbi'] }}</td>
                            <td class="py-2.5 px-3">{{ $careerTot['bb'] }}</td><td class="py-2.5 px-3">{{ $careerTot['k'] }}</td>
                            <td class="py-2.5 px-3">{{ $careerTot['sb'] }}</td>
                            <td class="py-2.5 px-3">{{ $fmtAvg($careerTot['avg']) }}</td><td class="py-2.5 px-3">{{ $fmtAvg($careerTot['obp']) }}</td>
                            <td class="py-2.5 px-3">{{ $fmtAvg($careerTot['slg']) }}</td><td class="py-2.5 px-3">{{ $fmtAvg($careerTot['ops']) }}</td>
                            <td class="py-2.5 px-3">{{ $fmtWar($careerTot['war']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        @endif
        @endif

        {{-- ══ PITCHING CAREER ══ --}}
        @foreach([
            ['label' => 'Career Major League Stats', 'rows' => $mlbPitching],
            ['label' => 'Career Minor League Stats', 'rows' => $minorsPitching],
        ] as $careerSection)
        @if($careerSection['rows']->isNotEmpty())
        <div>
            <h2 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-3">{{ $careerSection['label'] }}</h2>
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead>
                        <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="py-2.5 px-3 text-left">Team</th>
                            <th class="py-2.5 px-3">Year</th>
                            <th class="py-2.5 px-3">G</th><th class="py-2.5 px-3">GS</th>
                            <th class="py-2.5 px-3">W</th><th class="py-2.5 px-3">L</th>
                            <th class="py-2.5 px-3">SV</th><th class="py-2.5 px-3">HLD</th>
                            <th class="py-2.5 px-3">IP</th>
                            <th class="py-2.5 px-3">H</th><th class="py-2.5 px-3">ER</th>
                            <th class="py-2.5 px-3">BB</th><th class="py-2.5 px-3">K</th>
                            <th class="py-2.5 px-3">HR</th>
                            <th class="py-2.5 px-3">ERA</th>
                            <th class="py-2.5 px-3">WHIP</th>
                            <th class="py-2.5 px-3">WAR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($careerSection['rows'] as $row)
                        @php
                            $isRowMlb = (int)($row->team_level ?? 0) === 1;
                            $yrLdr = $isRowMlb ? ($mlbLeadersByYear[$row->year] ?? []) : [];
                            $yrWLead   = $isRowMlb && (int)$row->w > 0 && (int)$row->w >= ($yrLdr['w'] ?? 999);
                            $yrKLead   = $isRowMlb && (int)$row->k > 0 && (int)$row->k >= ($yrLdr['k'] ?? 999);
                            $yrSvLead  = $isRowMlb && (int)$row->sv > 0 && (int)$row->sv >= ($yrLdr['sv'] ?? 999);
                            $yrEraLead = $isRowMlb && is_numeric($row->era) && (int)$row->outs >= 45 && (float)$row->era <= ($yrLdr['era'] ?? 0) + 0.005;
                            $teamLabel = ($row->team_name ?? '') . ($row->league_abbr ? ' - ' . $row->league_abbr : '');
                        @endphp
                        <tr class="border-t border-gray-800/50 hover:bg-gray-800/30 transition">
                            <td class="py-2 px-3 text-left text-gray-400">{{ $teamLabel ?: '—' }}</td>
                            <td class="py-2 px-3 font-semibold text-white">{{ $row->year }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->g }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->gs }}</td>
                            <td class="py-2 px-3 {{ $yrWLead ? 'text-yellow-400 font-bold' : 'text-gray-300' }}">{{ (int)$row->w }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->l }}</td>
                            <td class="py-2 px-3 {{ $yrSvLead ? 'text-yellow-400 font-bold' : 'text-gray-300' }}">{{ (int)$row->sv }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->hld }}</td>
                            <td class="py-2 px-3 text-white font-semibold">{{ $row->ip_display }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->h }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->er }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->bb }}</td>
                            <td class="py-2 px-3 {{ $yrKLead ? 'text-yellow-400 font-bold' : 'text-gray-300' }}">{{ (int)$row->k }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ (int)$row->hr }}</td>
                            <td class="py-2 px-3 font-semibold {{ $yrEraLead ? 'text-yellow-400' : 'text-white' }}">{{ $row->era }}</td>
                            <td class="py-2 px-3 text-white font-semibold">{{ $row->whip }}</td>
                            <td class="py-2 px-3 font-semibold text-gray-300">{{ $fmtWar($row->war) }}</td>
                        </tr>
                        @endforeach
                        {{-- Section totals --}}
                        @php $tot = $pitTotals($careerSection['rows'], 'Total'); @endphp
                        <tr class="border-t-2 border-gray-700 bg-gray-800/30 font-bold text-white">
                            <td class="py-2.5 px-3 text-left text-xs text-gray-400 uppercase tracking-wider">{{ $tot['label'] }}</td>
                            <td class="py-2.5 px-3 text-gray-500">—</td>
                            <td class="py-2.5 px-3">{{ $tot['g'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['gs'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['w'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['l'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['sv'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['hld'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['ipD'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['h'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['er'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['bb'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['k'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['hr'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['era'] }}</td>
                            <td class="py-2.5 px-3">{{ $tot['whip'] }}</td>
                            <td class="py-2.5 px-3">{{ $fmtWar($tot['war']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        @endif
        @endforeach

        {{-- ══ PITCHING CAREER TOTALS BY LEVEL ══ --}}
        @if($careerPitching->isNotEmpty())
        @php
            $pitLevelGroups = $careerPitching->groupBy(fn($r) => (int)($r->team_level ?? 0))->sortKeys();
        @endphp
        @if($pitLevelGroups->count() > 1)
        <div>
            <h2 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-3">Career Totals by Level</h2>
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-x-auto">
                <table class="w-full text-sm text-right">
                    <thead>
                        <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="py-2.5 px-3 text-left">Level</th>
                            <th class="py-2.5 px-3">G</th><th class="py-2.5 px-3">GS</th>
                            <th class="py-2.5 px-3">W</th><th class="py-2.5 px-3">L</th>
                            <th class="py-2.5 px-3">SV</th><th class="py-2.5 px-3">HLD</th>
                            <th class="py-2.5 px-3">IP</th>
                            <th class="py-2.5 px-3">H</th><th class="py-2.5 px-3">ER</th>
                            <th class="py-2.5 px-3">BB</th><th class="py-2.5 px-3">K</th>
                            <th class="py-2.5 px-3">HR</th>
                            <th class="py-2.5 px-3">ERA</th>
                            <th class="py-2.5 px-3">WHIP</th>
                            <th class="py-2.5 px-3">WAR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pitLevelGroups as $lvl => $levelRows)
                        @php $lvlTot = $pitTotals($levelRows, $levelLabels[$lvl] ?? 'Level '.$lvl); @endphp
                        <tr class="border-t border-gray-800/50 hover:bg-gray-800/30 transition">
                            <td class="py-2 px-3 text-left font-semibold text-white">{{ $lvlTot['label'] }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $lvlTot['g'] }}</td><td class="py-2 px-3 text-gray-300">{{ $lvlTot['gs'] }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $lvlTot['w'] }}</td><td class="py-2 px-3 text-gray-300">{{ $lvlTot['l'] }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $lvlTot['sv'] }}</td><td class="py-2 px-3 text-gray-300">{{ $lvlTot['hld'] }}</td>
                            <td class="py-2 px-3 text-white font-semibold">{{ $lvlTot['ipD'] }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $lvlTot['h'] }}</td><td class="py-2 px-3 text-gray-300">{{ $lvlTot['er'] }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $lvlTot['bb'] }}</td><td class="py-2 px-3 text-gray-300">{{ $lvlTot['k'] }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $lvlTot['hr'] }}</td>
                            <td class="py-2 px-3 text-white font-semibold">{{ $lvlTot['era'] }}</td>
                            <td class="py-2 px-3 text-white font-semibold">{{ $lvlTot['whip'] }}</td>
                            <td class="py-2 px-3 text-gray-300">{{ $fmtWar($lvlTot['war']) }}</td>
                        </tr>
                        @endforeach
                        @php $careerTot = $pitTotals($careerPitching, 'Career'); @endphp
                        <tr class="border-t-2 border-gray-700 bg-gray-800/30 font-bold text-white">
                            <td class="py-2.5 px-3 text-left text-xs text-gray-400 uppercase tracking-wider">{{ $careerTot['label'] }}</td>
                            <td class="py-2.5 px-3">{{ $careerTot['g'] }}</td><td class="py-2.5 px-3">{{ $careerTot['gs'] }}</td>
                            <td class="py-2.5 px-3">{{ $careerTot['w'] }}</td><td class="py-2.5 px-3">{{ $careerTot['l'] }}</td>
                            <td class="py-2.5 px-3">{{ $careerTot['sv'] }}</td><td class="py-2.5 px-3">{{ $careerTot['hld'] }}</td>
                            <td class="py-2.5 px-3">{{ $careerTot['ipD'] }}</td>
                            <td class="py-2.5 px-3">{{ $careerTot['h'] }}</td><td class="py-2.5 px-3">{{ $careerTot['er'] }}</td>
                            <td class="py-2.5 px-3">{{ $careerTot['bb'] }}</td><td class="py-2.5 px-3">{{ $careerTot['k'] }}</td>
                            <td class="py-2.5 px-3">{{ $careerTot['hr'] }}</td>
                            <td class="py-2.5 px-3">{{ $careerTot['era'] }}</td><td class="py-2.5 px-3">{{ $careerTot['whip'] }}</td>
                            <td class="py-2.5 px-3">{{ $fmtWar($careerTot['war']) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        @endif
        @endif
    </section>
    @endif

    {{-- ── Awards ────────────────────────────────────────────────────────────── --}}
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

                        // 4=Cy Young, 5=MVP, 6=ROY → gold
                        // 7=Gold Glove → blue, 11=Silver Slugger → sky
                        // 9=All-Star → green
                        // 14=WS Champ, 15=WS MVP → red
                        // 13=Reliever of Year → purple
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

</div>
@endsection
