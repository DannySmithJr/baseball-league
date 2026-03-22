@extends('layouts.public')

@section('title', $settings->league_name)

@section('content')

{{-- Hero --}}
<section class="relative overflow-hidden py-24 sm:py-36">
    <div class="absolute inset-0 bg-gradient-to-br from-red-900/20 via-gray-950 to-blue-900/20"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <div class="inline-flex items-center gap-2 bg-red-600/20 border border-red-500/30 text-red-400 text-sm font-medium px-4 py-1.5 rounded-full mb-6">
            <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
            @if($ootp->seasonYear())
                Season {{ $settings->season }} &mdash; {{ $ootp->seasonYear() }}
            @else
                Season in progress
            @endif
        </div>
        <h1 class="text-5xl sm:text-7xl font-extrabold tracking-tight mb-4">
            {{ $settings->league_name }}
        </h1>
        @if($ootp->gameDate())
            <p class="text-red-400 font-semibold text-lg mb-4">
                {{ \Carbon\Carbon::parse($ootp->gameDate())->format('F j, Y') }}
            </p>
        @endif
        <p class="text-xl text-gray-400 max-w-3xl mx-auto mb-10">
            The premier online sim baseball league. Take the field as a GM or Player and write your legacy.
        </p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            @auth
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('adm.index') }}" class="bg-red-600 hover:bg-red-500 text-white font-semibold px-8 py-3 rounded-lg transition text-lg">Go to Admin Panel</a>
                @else
                    <a href="{{ route('cp.index') }}" class="bg-red-600 hover:bg-red-500 text-white font-semibold px-8 py-3 rounded-lg transition text-lg">Go to My Panel</a>
                @endif
            @else
                <a href="{{ route('register') }}" class="bg-red-600 hover:bg-red-500 text-white font-semibold px-8 py-3 rounded-lg transition text-lg">Join the League</a>
                <a href="#how-it-works" class="border border-gray-600 hover:border-red-500 text-gray-300 hover:text-red-400 font-semibold px-8 py-3 rounded-lg transition text-lg">Learn More</a>
            @endauth
        </div>
    </div>
</section>

{{-- Live Stats Bar --}}
<section class="border-y border-gray-800 bg-gray-900/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-8 text-center">
            <div>
                <div class="text-4xl font-extrabold text-red-500">{{ $stats['teams'] ?: '—' }}</div>
                <div class="text-gray-400 text-sm mt-1 uppercase tracking-wider">Teams</div>
            </div>
            <div>
                <div class="text-4xl font-extrabold text-red-500">{{ $settings->season ?? '—' }}</div>
                <div class="text-gray-400 text-sm mt-1 uppercase tracking-wider">Season</div>
            </div>
            <div>
                <div class="text-4xl font-extrabold text-red-500">{{ $stats['gms'] ?: '—' }}</div>
                <div class="text-gray-400 text-sm mt-1 uppercase tracking-wider">Active GMs</div>
            </div>
            <div>
                <div class="text-4xl font-extrabold text-red-500">{{ $stats['ccps'] ?: '—' }}</div>
                <div class="text-gray-400 text-sm mt-1 uppercase tracking-wider">CCP's</div>
            </div>
            <div>
                <div class="text-4xl font-extrabold text-red-500">{{ $stats['members'] ?: '—' }}</div>
                <div class="text-gray-400 text-sm mt-1 uppercase tracking-wider">Members</div>
            </div>
        </div>
    </div>
</section>

{{-- How It Works --}}
<section id="how-it-works" class="py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl sm:text-4xl font-extrabold text-center mb-4">How It Works</h2>
        <p class="text-center text-gray-400 max-w-2xl mx-auto mb-14">
            {{ $settings->league_name }} uses Out of the Park Baseball to simulate a full Major League Baseball experience. Here's how you can get involved.
        </p>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-8 text-center">
                <div class="w-16 h-16 bg-red-600/20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold mb-2">1. Create an Account</h3>
                <p class="text-gray-400 text-sm">Sign up for free and set up your profile. Pick a username and get ready to join the action.</p>
            </div>
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-8 text-center">
                <div class="w-16 h-16 bg-red-600/20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold mb-2">2. Request a Role</h3>
                <p class="text-gray-400 text-sm">Apply to become a General Manager and run a team, or request to be a custom player in the league.</p>
            </div>
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-8 text-center">
                <div class="w-16 h-16 bg-red-600/20 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <h3 class="text-xl font-bold mb-2">3. Play Ball</h3>
                <p class="text-gray-400 text-sm">Manage your roster, make trades, set lineups, and compete for the championship. Games sim regularly.</p>
            </div>
        </div>
    </div>
</section>

{{-- Season Leaders — Flip Cards --}}
@if($batters->isNotEmpty() || $pitchers->isNotEmpty())
<section class="border-y border-gray-800 bg-gray-900/40 py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

        <h2 class="text-3xl sm:text-4xl font-extrabold text-center mb-2">
            @if($legendYear)
                {{ $legendYear }} Season <span class="text-red-500">Leaders</span>
            @else
                All-Time <span class="text-red-500">Legends</span>
            @endif
        </h2>
        <p class="text-center text-gray-500 text-sm mb-14">
            Hover a card to see league awards &nbsp;·&nbsp;
            @if($legendYear) Top {{ $batters->count() }} by WAR @endif
        </p>

        @php
        $awardNames = \App\Services\OotpService::AWARD_NAMES;
        $fmtBatStat = fn($v) => $v>=1 ? '1.000' : '.'.str_pad((string)round($v*1000),3,'0',STR_PAD_LEFT);
        @endphp

        @foreach([
            ['label' => 'Top Hitters',  'players' => $batters,  'type' => 'batter'],
            ['label' => 'Top Pitchers', 'players' => $pitchers, 'type' => 'pitcher'],
        ] as $group)
        @if($group['players']->isNotEmpty())

        <p class="text-sm font-bold tracking-widest text-red-500 uppercase text-center my-10">{{ $group['label'] }}</p>
        <div class="cards-row">

            @foreach($group['players'] as $p)
            @php
                $pid      = $p->player_id;
                $posLabel = $ootp->positionName((int)$p->position);
                $isRP     = (int)$p->position === 2;

                // ── FRONT: season stats ──
                if ($group['type'] === 'batter') {
                    $ab  = (int)$p->ab; $h = (int)$p->h;
                    $hr  = (int)$p->hr;
                    $avg = $ab > 0 ? $h / $ab : 0;
                    $seasonStats = [
                        ['label'=>'AVG', 'val'=>$fmtBatStat($avg)],
                        ['label'=>'HR',  'val'=>$hr],
                        ['label'=>'RBI', 'val'=>(int)$p->rbi],
                    ];
                } else {
                    $outs = (int)$p->outs;
                    $ip   = $outs / 3;
                    $era  = $ip > 0 ? number_format(((int)$p->er / $ip) * 9, 2) : '—';
                    $seasonStats = $isRP ? [
                        ['label'=>'SV',  'val'=>(int)$p->sv],
                        ['label'=>'ERA', 'val'=>$era],
                        ['label'=>'K',   'val'=>(int)$p->k],
                    ] : [
                        ['label'=>'W',   'val'=>(int)$p->w],
                        ['label'=>'ERA', 'val'=>$era],
                        ['label'=>'K',   'val'=>(int)$p->k],
                    ];
                }

                // ── BACK: career totals ──
                if ($group['type'] === 'batter') {
                    $cb = $careerBatting[$pid] ?? null;
                    $careerStats  = $cb ? [
                        ['label'=>'AVG', 'val'=>$fmtBatStat($cb->avg)],
                        ['label'=>'HR',  'val'=>(int)$cb->hr],
                        ['label'=>'RBI', 'val'=>(int)$cb->rbi],
                    ] : [];
                    $careerWarVal = $cb ? number_format((float)($cb->war ?? 0), 1) : '—';
                } else {
                    $cp = $careerPitching[$pid] ?? null;
                    $careerStats  = $cp ? ($isRP ? [
                        ['label'=>'SV',  'val'=>(int)$cp->sv],
                        ['label'=>'ERA', 'val'=>$cp->era],
                        ['label'=>'K',   'val'=>(int)$cp->k],
                    ] : [
                        ['label'=>'W',   'val'=>(int)$cp->w],
                        ['label'=>'ERA', 'val'=>$cp->era],
                        ['label'=>'K',   'val'=>(int)$cp->k],
                    ]) : [];
                    $careerWarVal = $cp ? number_format((float)($cp->war ?? 0), 1) : '—';
                }

                // Career awards: group by award_id, count wins (finish=1)
                $rawAwards     = $careerAwards[$pid] ?? [];
                $groupedAwards = [];
                foreach ($rawAwards as $aw) {
                    $key  = $aw->award_id; // int (OOTP) or 'league_slug' string
                    $name = isset($aw->award_name)
                        ? $aw->award_name                        // league award
                        : ($awardNames[(int)$key] ?? '');        // OOTP award
                    if (!isset($groupedAwards[$key])) $groupedAwards[$key] = ['wins' => 0, 'name' => $name];
                    if ((int)$aw->finish === 1) $groupedAwards[$key]['wins']++;
                }
                $groupedAwards = array_filter($groupedAwards, fn($a) => $a['wins'] > 0);

                // Top award for front face
                $topAwardText = null;
                if (!empty($groupedAwards)) {
                    $firstAid = array_key_first($groupedAwards);
                    $wins = $groupedAwards[$firstAid]['wins'];
                    $topAwardText = ($wins > 1 ? $wins.'x ' : '') . ($awardNames[$firstAid] ?? '');
                }
            @endphp

            {{-- Flip Card --}}
            <div class="card-wrap">
                <div class="card-inner"
                     onmouseenter="this.style.transform='rotateY(180deg)'"
                     onmouseleave="this.style.transform='rotateY(0)'">

                    {{-- FRONT --}}
                    <div class="card-face card-front">
                        <div class="card-pos-badge"><span>{{ $posLabel }}</span></div>
                        <div class="card-name-zone">
                            <p class="card-name">{{ $p->first_name }} {{ $p->last_name }}</p>
                            <p class="card-team">{{ trim(($p->team_name ?? '').' '.($p->team_nickname ?? '')) ?: ($p->team_abbr ?? '') }}</p>
                            @php $twitchName = $ccpTwitch[$pid] ?? null; @endphp
                            @if($twitchName)
                                <p class="card-twitch-label">Twitch User</p>
                                <p class="card-twitch-name">{{ strtoupper($twitchName) }}</p>
                            @else
                                <p class="card-nickname"></p>
                            @endif
                        </div>
                        <p class="card-season">{{ $legendYear }} Season</p>
                        <div class="card-stats-strip">
                            @foreach($seasonStats as $s)
                            <div class="card-stat-item">
                                <span class="card-stat-val">{{ $s['val'] }}</span>
                                <span class="card-stat-label">{{ $s['label'] }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- BACK --}}
                    <div class="card-face card-back">
                        <div class="card-back-name-zone">
                            <p class="card-back-name">{{ $p->first_name }} {{ $p->last_name }}</p>
                            <p class="card-back-nickname">@if($p->nick_name)"{{ $p->nick_name }}"@endif</p>
                            <p class="card-back-team">{{ $p->team_abbr ?? '' }} &middot; {{ $posLabel }}</p>
                        </div>
                        <div class="card-career-zone">
                            @if(!empty($careerStats))
                            <div class="card-career-stats">
                                @foreach($careerStats as $s)
                                <div class="card-career-stat-item">
                                    <span class="card-career-val">{{ $s['val'] }}</span>
                                    <span class="card-career-label">{{ $s['label'] }}</span>
                                </div>
                                @endforeach
                            </div>
                            <p class="card-career-war">Career WAR <span>{{ $careerWarVal }}</span></p>
                            @endif
                        </div>
                        <div class="card-awards-zone">
                            @if(!empty($groupedAwards))
                                <p class="card-awards-header">Career Awards</p>
                                @foreach($groupedAwards as $cnt)
                                    <p class="card-award-line">{{ $cnt['wins'] > 1 ? $cnt['wins'].'x ' : '' }}{{ $cnt['name'] }}</p>
                                @endforeach
                            @else
                                <p class="card-award-none">No career awards</p>
                            @endif
                        </div>
                        <div class="card-profile-link">
                            <a href="{{ route('player', $pid) }}">View Profile →</a>
                        </div>
                    </div>

                </div>
            </div>
            @endforeach

        </div>
        @endif
        @endforeach

    </div>
</section>
@endif

{{-- Requirements --}}
<section id="requirements" class="py-24">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl sm:text-4xl font-extrabold text-center mb-4">Requirements</h2>
        <p class="text-center text-gray-400 max-w-2xl mx-auto mb-14">
            Joining {{ $settings->league_name }} is easy. Here's what you need to participate.
        </p>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl mx-auto">
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
                <h3 class="font-bold text-red-500 text-lg mb-4">For General Managers</h3>
                <ul class="space-y-3 text-gray-400 text-sm">
                    @foreach([
                        'Active participation — check in regularly and manage your team',
                        'Join our Discord server for league communication',
                        'Respect league rules and fellow members',
                        'Knowledge of baseball or willingness to learn',
                    ] as $req)
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ $req }}
                    </li>
                    @endforeach
                </ul>
            </div>
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
                <h3 class="font-bold text-red-500 text-lg mb-4">For Players (CCP)</h3>
                <ul class="space-y-3 text-gray-400 text-sm">
                    @foreach([
                        'Create a free account on this site',
                        'Submit a player request with your desired position and attributes',
                        'Join Discord to follow your player\'s progress',
                        'No prior experience needed — just a love for the game',
                    ] as $req)
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ $req }}
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</section>

{{-- CTA --}}
<section class="border-t border-gray-800 bg-gray-900/40">
    <div class="max-w-4xl mx-auto px-4 py-24 text-center">
        <h2 class="text-3xl sm:text-4xl font-extrabold mb-4">Ready to Play Ball?</h2>
        <p class="text-gray-400 text-lg mb-10">
            Join {{ $settings->league_name }} today and become part of the premier sim baseball community.
            Whether you want to manage a franchise or create a star player, there's a spot for you.
        </p>
        <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="{{ route('register') }}" class="bg-red-600 hover:bg-red-500 text-white font-semibold px-8 py-3 rounded-lg text-lg transition">
                Create Your Account
            </a>
            @if($settings->discord_url)
                <a href="{{ $settings->discord_url }}" target="_blank" rel="noopener"
                   class="border border-indigo-500 text-indigo-400 hover:bg-indigo-500 hover:text-white font-semibold px-8 py-3 rounded-lg text-lg transition">
                    Join Our Discord
                </a>
            @endif
        </div>
    </div>
</section>

@endsection
