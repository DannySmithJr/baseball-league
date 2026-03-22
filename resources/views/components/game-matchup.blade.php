@props([
    'date'        => null,   // string e.g. "Jul 1" — shown top-left when set
    'time'        => null,   // string e.g. "7:05 PM ET" — shown top-right when set
    'away'        => [],     // ['abbr', 'name'?, 'logo'?, 'bgColor'?, 'record'?, 'rpg'?, 'avg'?, 'hr'?, 'momentum'?]
    'home'        => [],     // same
    'awayStarter' => null,   // ['name', 'w', 'l', 'era', 'k'] or null = TBD
    'homeStarter' => null,   // same
    'location'    => null,   // string or null
    'stream'      => false,  // bool — show Twitch link
    'odds'        => null,   // ['spread', 'over_under', 'favorite', 'home_ml', 'away_ml'] or null
])

<div class="bg-gray-900 border border-gray-800 rounded-xl p-4 hover:border-gray-700 transition flex flex-col">

    {{-- Header: date / time --}}
    @if($date || $time)
    <div class="flex items-center justify-between mb-3">
        @if($date)
            <p class="text-xs text-gray-500 font-semibold">{{ $date }}</p>
        @endif
        @if($time)
            <p class="text-xs text-gray-400 {{ !$date ? 'w-full' : '' }}">{{ $time }}</p>
        @endif
    </div>
    @endif

    {{-- Stat column headers --}}
    <div class="flex items-center mb-1 pl-[34px]">
        <div class="flex-1"></div>
        <div class="flex text-[10px] text-gray-600 uppercase tracking-wider">
            <span class="w-10 text-center">R/G</span>
            <span class="w-12 text-center">AVG</span>
            <span class="w-12 text-center">HR</span>
        </div>
    </div>

    {{-- Away team row --}}
    <div class="flex items-center gap-2.5 mb-1.5">
        @if(!empty($away['logo']))
            <img src="/images/logos/{{ $away['logo'] }}" class="w-7 h-7 object-contain shrink-0" alt="{{ $away['abbr'] ?? '' }}">
        @else
            <div class="w-7 h-7 rounded-full shrink-0" style="background:{{ $away['bgColor'] ?? '#374151' }}"></div>
        @endif
        <div class="flex-1 min-w-0 leading-tight">
            <span class="text-sm font-bold text-white">{{ $away['name'] ?? $away['abbr'] ?? '' }}</span>
            @if(!empty($away['record']))
                <span class="text-xs text-gray-500 ml-1.5">{{ $away['record'] }}</span>
            @endif
            @if(!empty($away['momentum']) && abs($away['momentum']) >= 3)
                <span class="ml-1.5 text-xs font-bold {{ $away['momentum'] > 0 ? 'text-green-400' : 'text-red-400' }}">{{ $away['momentum'] > 0 ? 'W' : 'L' }}{{ abs($away['momentum']) }}</span>
            @endif
        </div>
        <div class="flex text-xs text-gray-300 shrink-0">
            <span class="w-10 text-center">{{ isset($away['rpg']) ? number_format($away['rpg'], 1) : '—' }}</span>
            <span class="w-12 text-center">{{ isset($away['avg']) ? ltrim(number_format($away['avg'], 3), '0') : '—' }}</span>
            <span class="w-12 text-center">{{ $away['hr'] ?? '—' }}</span>
        </div>
    </div>

    <div class="h-1"></div>

    {{-- Home team row --}}
    <div class="flex items-center gap-2.5 mb-4">
        @if(!empty($home['logo']))
            <img src="/images/logos/{{ $home['logo'] }}" class="w-7 h-7 object-contain shrink-0" alt="{{ $home['abbr'] ?? '' }}">
        @else
            <div class="w-7 h-7 rounded-full shrink-0" style="background:{{ $home['bgColor'] ?? '#374151' }}"></div>
        @endif
        <div class="flex-1 min-w-0 leading-tight">
            <span class="text-sm font-bold text-white">{{ $home['name'] ?? $home['abbr'] ?? '' }}</span>
            @if(!empty($home['record']))
                <span class="text-xs text-gray-500 ml-1.5">{{ $home['record'] }}</span>
            @endif
            @if(!empty($home['momentum']) && abs($home['momentum']) >= 3)
                <span class="ml-1.5 text-xs font-bold {{ $home['momentum'] > 0 ? 'text-green-400' : 'text-red-400' }}">{{ $home['momentum'] > 0 ? 'W' : 'L' }}{{ abs($home['momentum']) }}</span>
            @endif
        </div>
        <div class="flex text-xs text-gray-300 shrink-0">
            <span class="w-10 text-center">{{ isset($home['rpg']) ? number_format($home['rpg'], 1) : '—' }}</span>
            <span class="w-12 text-center">{{ isset($home['avg']) ? ltrim(number_format($home['avg'], 3), '0') : '—' }}</span>
            <span class="w-12 text-center">{{ $home['hr'] ?? '—' }}</span>
        </div>
    </div>

    {{-- Probable Pitchers --}}
    <div class="border-t border-gray-800 pt-3 flex-1">
        <div class="flex items-center mb-1.5">
            <p class="text-[10px] font-bold tracking-widest text-gray-600 uppercase flex-1">Probable Pitchers</p>
            <div class="flex text-[10px] text-gray-600 uppercase tracking-wider shrink-0">
                <span class="w-10 text-center">W-L</span>
                <span class="w-12 text-center">ERA</span>
                <span class="w-12 text-center">K</span>
            </div>
        </div>
        @foreach([[$awayStarter, $away['abbr'] ?? ''], [$homeStarter, $home['abbr'] ?? '']] as [$sp, $spAbbr])
        <div class="flex items-center mb-1">
            <div class="flex-1 min-w-0">
                @if($sp)
                    <span class="text-sm text-gray-200 font-semibold">{{ $sp['name'] }}</span>
                    <span class="text-[11px] text-gray-500 ml-1">{{ $spAbbr }}</span>
                @else
                    <span class="text-xs text-gray-600 italic">TBD</span>
                @endif
            </div>
            <div class="flex text-xs text-gray-300 shrink-0">
                <span class="w-10 text-center">{{ $sp ? $sp['w'].'-'.$sp['l'] : '—' }}</span>
                <span class="w-12 text-center">{{ $sp['era'] ?? '—' }}</span>
                <span class="w-12 text-center">{{ $sp ? $sp['k'] : '—' }}</span>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Odds: Spread & O/U --}}
    @if($odds)
    @php
        $sp = $odds['spread'] ?? 0;
        $ou = $odds['over_under'] ?? 0;
        $fav = $odds['favorite'] ?? 'home';
        $favAbbr = $fav === 'home' ? ($home['abbr'] ?? 'HOME') : ($away['abbr'] ?? 'AWAY');
        $spreadStr = $sp == 0 ? 'PK' : $favAbbr . ' ' . number_format(abs($sp), 1);
    @endphp
    <div class="border-t border-gray-800 pt-2.5 mt-1 flex items-center justify-between text-xs">
        <div>
            <span class="text-gray-600 uppercase text-[10px] tracking-wider">Spread</span>
            <span class="text-gray-300 font-semibold ml-1.5">{{ $spreadStr }}</span>
        </div>
        <div>
            <span class="text-gray-600 uppercase text-[10px] tracking-wider">O/U</span>
            <span class="text-gray-300 font-semibold ml-1.5">{{ number_format($ou, 1) }}</span>
        </div>
    </div>
    @endif

    {{-- Location --}}
    @if($location)
    <div class="pt-3">
        <p class="text-xs text-gray-600 leading-snug">{{ $location }}</p>
    </div>
    @endif

    {{-- Stream --}}
    @if($stream)
    <div class="border-t border-gray-800/60 pt-2.5 mt-3 text-center">
        <a href="#" class="inline-flex items-center gap-1.5 text-sm font-bold text-purple-400 hover:text-purple-300 transition">
            <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24"><path d="M2.149 0L.537 4.119v16.836h5.731V24h3.224l3.045-3.045h4.657l6.269-6.269V0H2.149zm19.164 13.612l-3.582 3.582H12.85l-3.045 3.045v-3.045H4.806V2.09h16.507v11.522zm-3.582-7.343v6.262h-2.09V6.269h2.09zm-5.731 0v6.262h-2.09V6.269h2.09z"/></svg>
            Live on Twitch
        </a>
    </div>
    @endif

</div>
