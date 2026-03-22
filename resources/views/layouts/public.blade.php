<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>@yield('title', $settings->league_name) — {{ $settings->league_abbr }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:wght@400;500;600;700&family=Roboto:ital,wght@0,400;0,500;0,700;0,900;1,400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="font-sans antialiased bg-gray-950 text-white min-h-screen flex flex-col">

{{-- Header --}}
<header class="border-b border-gray-800 bg-gray-950/90 backdrop-blur sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
        <div class="flex items-center gap-6">
            <a href="{{ route('home') }}" class="flex items-center gap-3 hover:opacity-90 transition">
                <svg class="h-8 w-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke-width="1.5"/>
                    <path d="M12 2 C8 6 8 18 12 22" stroke-width="1.5"/>
                    <path d="M12 2 C16 6 16 18 12 22" stroke-width="1.5"/>
                    <path d="M2 12 C6 8 18 8 22 12" stroke-width="1.5"/>
                    <path d="M2 12 C6 16 18 16 22 12" stroke-width="1.5"/>
                </svg>
                <span class="text-xl font-bold tracking-tight">{{ $settings->league_abbr }}</span>
            </a>
            <nav class="hidden md:flex items-center gap-1">
                <a href="{{ route('home') }}"
                   class="px-3 py-2 rounded-lg text-sm font-medium transition
                          {{ request()->routeIs('home') ? 'bg-gray-800 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800/60' }}">
                    Home
                </a>
                <a href="{{ route('standings') }}"
                   class="px-3 py-2 rounded-lg text-sm font-medium transition
                          {{ request()->routeIs('standings*') ? 'bg-gray-800 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800/60' }}">
                    Standings
                </a>
                <a href="{{ route('schedule') }}"
                   class="px-3 py-2 rounded-lg text-sm font-medium transition
                          {{ request()->routeIs('schedule*') ? 'bg-gray-800 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800/60' }}">
                    Schedule
                </a>
                <a href="{{ route('teams') }}"
                   class="px-3 py-2 rounded-lg text-sm font-medium transition
                          {{ request()->routeIs('teams*') ? 'bg-gray-800 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800/60' }}">
                    Teams
                </a>
            </nav>
        </div>
        <nav class="flex items-center gap-4">
            @auth
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('adm.index') }}" class="text-sm text-gray-300 hover:text-white transition">Admin</a>
                @else
                    <a href="{{ route('cp.index') }}" class="text-sm text-gray-300 hover:text-white transition">My Panel</a>
                @endif
            @else
                <a href="{{ route('login') }}" class="text-sm text-gray-300 hover:text-white transition">Log in</a>
                @if(Route::has('register'))
                    <a href="{{ route('register') }}" class="text-sm bg-red-600 hover:bg-red-500 text-white px-4 py-2 rounded-lg font-medium transition">Register</a>
                @endif
            @endauth
        </nav>
    </div>
</header>

{{-- Page content --}}
<main class="flex-1">
    @yield('content')
</main>

{{-- Footer --}}
<footer class="border-t border-gray-800 bg-gray-900/60 mt-auto">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">

        {{-- Row 1: Logo + socials --}}
        <div class="flex flex-wrap items-center gap-6">
            {{-- sMLB logo --}}
            <a href="{{ route('home') }}" class="shrink-0 flex items-center gap-3 hover:opacity-80 transition">
                <svg class="h-10 w-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke-width="1.5"/>
                    <path d="M12 2 C8 6 8 18 12 22" stroke-width="1.5"/>
                    <path d="M12 2 C16 6 16 18 12 22" stroke-width="1.5"/>
                    <path d="M2 12 C6 8 18 8 22 12" stroke-width="1.5"/>
                    <path d="M2 12 C6 16 18 16 22 12" stroke-width="1.5"/>
                </svg>
                <span class="text-lg font-bold tracking-tight text-white">{{ $settings->league_abbr }}</span>
            </a>

            <span class="text-gray-500 text-sm font-semibold">Connect with {{ $settings->league_abbr }}:</span>

            {{-- Social icons --}}
            <div class="flex items-center gap-5">
                @if($settings->x_url)
                <a href="{{ $settings->x_url }}" target="_blank" rel="noopener" class="text-gray-500 hover:text-white transition" title="X / Twitter">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.737-8.835L1.254 2.25H8.08l4.253 5.622 5.91-5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                </a>
                @endif
                @if($settings->youtube_url)
                <a href="{{ $settings->youtube_url }}" target="_blank" rel="noopener" class="text-gray-500 hover:text-white transition" title="YouTube">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                </a>
                @endif
                @if($settings->twitch_url)
                <a href="{{ $settings->twitch_url }}" target="_blank" rel="noopener" class="text-[#9146FF] hover:text-white transition" title="Twitch">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M11.571 4.714h1.715v5.143H11.57zm4.715 0H18v5.143h-1.714zM6 0L1.714 4.286v15.428h5.143V24l4.286-4.286h3.428L22.286 12V0zm14.571 11.143l-3.428 3.428h-3.429l-3 3v-3H6.857V1.714h13.714z"/></svg>
                </a>
                @endif
                @if($settings->discord_url)
                <a href="{{ $settings->discord_url }}" target="_blank" rel="noopener" class="text-gray-500 hover:text-white transition" title="Discord">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0 12.64 12.64 0 0 0-.617-1.25.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057c.002.022.015.043.03.054a19.9 19.9 0 0 0 5.993 3.03.078.078 0 0 0 .084-.028 14.09 14.09 0 0 0 1.226-1.994.076.076 0 0 0-.041-.106 13.107 13.107 0 0 1-1.872-.892.077.077 0 0 1-.008-.128 10.2 10.2 0 0 0 .372-.292.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.198.373.292a.077.077 0 0 1-.006.127 12.299 12.299 0 0 1-1.873.892.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028 19.839 19.839 0 0 0 6.002-3.03.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"/></svg>
                </a>
                @endif
                @if($settings->kofi_url)
                <a href="{{ $settings->kofi_url }}" target="_blank" rel="noopener" class="text-gray-500 hover:text-white transition" title="Ko-fi">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.881 8.948c-.773-4.085-4.859-4.593-4.859-4.593H.723c-.604 0-.679.798-.679.798s-.082 7.324-.022 11.822c.164 2.424 2.586 2.672 2.586 2.672s8.267-.023 11.966-.049c2.438-.426 2.683-2.566 2.658-3.734 4.352.24 7.422-2.831 6.649-6.916zm-11.062 3.511c-1.246 1.453-4.011 3.976-4.011 3.976s-.121.119-.31.019c-.073-.049-.157-.78-.157-.78l-.643-6.185s-.033-.545.344-.545c.207 0 .43.07.43.07l4.523 2.026c.344.172.274.484.274.484s.038.45-.45.955zm2.135-1.024c-.702.822-1.428 1.646-1.428 1.646s-.228.282-.397.282c-.168 0-.335-.282-.335-.282s-1.07-1.315-1.07-1.993c0-.678.609-1.24 1.358-1.24.748 0 1.358.562 1.358 1.24 0 .104-.018.208-.05.307-.079.237.564.04.564.04z"/></svg>
                </a>
                @endif
                @if($settings->stats_url)
                <a href="{{ $settings->stats_url }}" target="_blank" rel="noopener" class="text-gray-500 hover:text-white transition text-xs font-bold" title="Stats Site">STATS</a>
                @endif
            </div>
        </div>

        {{-- Row 2: Legal links --}}
        <div class="flex flex-wrap items-center gap-x-6 gap-y-1 text-sm text-gray-500">
            <button onclick="document.getElementById('wpa-modal').style.display='flex'" class="hover:text-gray-300 transition">How We Score It</button>
            <a href="{{ route('legal.terms') }}" class="hover:text-gray-300 transition">Terms of Use</a>
            <a href="{{ route('legal.privacy') }}" class="hover:text-gray-300 transition">Privacy Policy</a>
            <a href="{{ route('legal.notices') }}" class="hover:text-gray-300 transition">Legal Notices</a>
            <a href="{{ route('legal.contact') }}" class="hover:text-gray-300 transition">Contact Us</a>
            <a href="{{ route('legal.do-not-sell') }}" class="hover:text-gray-300 transition">Do Not Sell or Share My Personal Data</a>
            <a href="{{ route('legal.cookies') }}" class="hover:text-gray-300 transition">Cookies Settings</a>
        </div>

        {{-- Row 3: Copyright --}}
        @php $founded = 2026; $yr = (int)date('Y'); @endphp
        <p class="text-sm text-gray-400">&copy; {{ $yr > $founded ? $founded.'-'.$yr : $founded }} {{ $settings->league_abbr }} One Media. All rights reserved.</p>

    </div>
</footer>

{{-- WPA Explanation Modal --}}
<div id="wpa-modal" style="display:none" class="fixed inset-0 z-50 items-center justify-center px-4">
    <div class="absolute inset-0 bg-black/70" onclick="document.getElementById('wpa-modal').style.display='none'"></div>
    <div class="relative bg-gray-900 border border-gray-700 rounded-xl max-w-lg w-full p-8 shadow-2xl">
        <button onclick="document.getElementById('wpa-modal').style.display='none'"
                class="absolute top-4 right-4 text-gray-600 hover:text-gray-300 text-xl leading-none">&times;</button>

        <div class="flex items-center gap-3 mb-6">
            <div class="w-10 h-10 rounded-full bg-gray-800 border border-gray-600 flex items-center justify-center shrink-0">
                <svg class="h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke-width="1.5"/>
                </svg>
            </div>
            <h2 class="text-lg font-bold text-white">How We Score It</h2>
        </div>

        <div class="space-y-4 text-sm text-gray-300 leading-relaxed">
            <p>
                <span class="text-white font-semibold">Players of the Game</span> are determined using
                <span class="text-white font-semibold">Win Probability Added (WPA)</span> — a stat that measures
                how much each individual play moved the needle toward a win.
            </p>
            <p>
                Every at-bat changes a team's probability of winning based on the game situation — the score,
                inning, outs, and runners on base. WPA credits the batter or pitcher for that change.
                A walk-off home run in the 9th carries far more weight than a leadoff single in the 1st.
            </p>
            <p>
                We display WPA multiplied by 100 for readability. A score of
                <span class="text-white font-semibold">41.32</span> means that player increased their team's
                win probability by <span class="text-white font-semibold">41.32 percentage points</span> across
                all their plate appearances or pitching outings in that game.
            </p>
            <p>
                The two players with the highest WPA — regardless of team or position — are named Players of the Game.
                Some games may feature two hitters, two pitchers, or one of each.
            </p>
            <p>
                In tight, low-scoring games WPA scores will naturally be lower — no single play shifts win probability dramatically when the game stays close throughout. A dominant performance in a blowout can also score lower than a clutch hit in a one-run game.
            </p>
            <p class="text-gray-500 text-xs pt-2 border-t border-gray-800">
                WPA data sourced from OOTP Baseball simulation engine.
            </p>
        </div>
    </div>
</div>

@stack('scripts')
</body>
</html>
