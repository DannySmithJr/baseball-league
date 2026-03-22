@php
use App\Services\OotpService;

$posName = OotpService::POSITIONS[(int)$player->position] ?? '?';
$batLabel = match((int)$player->bats ?? 0) { 1 => 'R', 2 => 'L', 3 => 'S', default => '?' };
$throwLabel = match((int)$player->throws ?? 0) { 1 => 'R', 2 => 'L', default => '?' };
$fmtAvg = function($v) { $v = (float)$v; if ($v <= 0) return '.000'; if ($v >= 1) return '1.000'; return '.' . str_pad((string)round($v * 1000), 3, '0', STR_PAD_LEFT); };
$heightInches = (int)($player->height ?? 0) > 0 ? (int)floor((int)$player->height / 2.54) : null;
$feet = $heightInches ? intdiv($heightInches, 12) : null;
$inches = $heightInches ? $heightInches % 12 : null;
$dob = $player->date_of_birth ? \Carbon\Carbon::parse($player->date_of_birth) : null;
$birthplace = implode(', ', array_filter([$player->birth_city ?? null, $player->birth_state ?? null]));
$seasonYear = $seasonBatting->year ?? $seasonPitching->year ?? null;
@endphp

{{-- ── Player Header ── --}}
<div class="border-b border-gray-800 bg-gray-900/50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-5">
        <table class="w-full"><tr class="align-top">

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
                @if($contract && (int)($contract->salary0 ?? 0) > 0)
                @php
                    $salary = (int)$contract->salary0;
                    $yrsLeft = collect(range(0, 9))->filter(fn($i) => (int)($contract->{'salary'.$i} ?? 0) > 0)->count();
                @endphp
                <div class="mt-1 text-sm">
                    <span class="text-green-400 font-semibold">${{ number_format($salary) }}</span>
                    @if($yrsLeft > 1)
                        <span class="text-gray-500">({{ $yrsLeft }} yrs)</span>
                    @else
                        <span class="text-gray-500">(1 yr)</span>
                    @endif
                </div>
                @endif
            </td>

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

            <td class="pl-10" style="width:1%;white-space:nowrap">
                @if($seasonBatting || $seasonPitching)
                <div class="bg-gray-800/50 border border-gray-700 rounded-xl overflow-hidden">
                    <div class="bg-red-600 px-4 py-1.5 text-center">
                        <p class="text-[11px] font-bold text-white uppercase tracking-wider">{{ $seasonYear }} Season Stats</p>
                    </div>
                    @if($seasonPitching)
                    @php $s = $seasonPitching; @endphp
                    <div class="flex divide-x divide-gray-700">
                        <div class="text-center px-5 py-3"><p class="text-xs text-gray-500">W-L</p><p class="text-xl font-black text-white">{{ (int)$s->w }}-{{ (int)$s->l }}</p>@if($headerRanks['wl'] ?? null)<p class="text-xs text-gray-400">{{ $headerRanks['wl'] }}</p>@endif</div>
                        <div class="text-center px-5 py-3"><p class="text-xs text-gray-500">ERA</p><p class="text-xl font-black text-white">{{ $s->era }}</p>@if($headerRanks['era'] ?? null)<p class="text-xs text-gray-400">{{ $headerRanks['era'] }}</p>@endif</div>
                        <div class="text-center px-5 py-3"><p class="text-xs text-gray-500">K</p><p class="text-xl font-black text-white">{{ (int)$s->k }}</p>@if($headerRanks['k'] ?? null)<p class="text-xs text-gray-400">{{ $headerRanks['k'] }}</p>@endif</div>
                        <div class="text-center px-5 py-3"><p class="text-xs text-gray-500">WHIP</p><p class="text-xl font-black text-white">{{ $s->whip }}</p>@if($headerRanks['whip'] ?? null)<p class="text-xs text-gray-400">{{ $headerRanks['whip'] }}</p>@endif</div>
                    </div>
                    @elseif($seasonBatting)
                    @php $s = $seasonBatting; @endphp
                    <div class="flex divide-x divide-gray-700">
                        <div class="text-center px-5 py-3"><p class="text-xs text-gray-500">AVG</p><p class="text-xl font-black text-white">{{ $fmtAvg($s->avg) }}</p>@if($headerRanks['avg'] ?? null)<p class="text-xs text-gray-400">{{ $headerRanks['avg'] }}</p>@endif</div>
                        <div class="text-center px-5 py-3"><p class="text-xs text-gray-500">HR</p><p class="text-xl font-black text-white">{{ (int)$s->hr }}</p>@if($headerRanks['hr'] ?? null)<p class="text-xs text-gray-400">{{ $headerRanks['hr'] }}</p>@endif</div>
                        <div class="text-center px-5 py-3"><p class="text-xs text-gray-500">RBI</p><p class="text-xl font-black text-white">{{ (int)$s->rbi }}</p>@if($headerRanks['rbi'] ?? null)<p class="text-xs text-gray-400">{{ $headerRanks['rbi'] }}</p>@endif</div>
                        <div class="text-center px-5 py-3"><p class="text-xs text-gray-500">OPS</p><p class="text-xl font-black text-white">{{ $fmtAvg($s->ops) }}</p>@if($headerRanks['ops'] ?? null)<p class="text-xs text-gray-400">{{ $headerRanks['ops'] }}</p>@endif</div>
                    </div>
                    @endif
                </div>
                @endif
            </td>

        </tr></table>
    </div>
</div>

{{-- ── Player Nav ── --}}
<div class="border-b border-gray-800 bg-gray-900/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <nav class="flex gap-1 -mb-px overflow-x-auto">
            @php
                $playerNav = [
                    ['route' => 'player',         'label' => 'Overview'],
                    ['route' => 'player.news',    'label' => 'News'],
                    ['route' => 'player.stats',   'label' => 'Stats'],
                    ['route' => 'player.ratings', 'label' => 'Ratings'],
                    ['route' => 'player.bio',      'label' => 'Bio'],
                    ['route' => 'player.contract', 'label' => 'Contract'],
                    ['route' => 'player.gamelog', 'label' => 'Game Log'],
                ];
                $isCcpOwner = auth()->check() && auth()->user()->hasCcp() && auth()->user()->ccp()?->player_id === $player->player_id;
                $activeTab = $activeTab ?? 'player';
            @endphp
            @foreach($playerNav as $nav)
            <a href="{{ route($nav['route'], $player->player_id) }}"
               class="whitespace-nowrap px-4 py-3 text-sm font-medium border-b-2 transition
                      {{ $activeTab === $nav['route']
                         ? 'border-red-500 text-white'
                         : 'border-transparent text-gray-400 hover:text-white hover:border-gray-600' }}">
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
