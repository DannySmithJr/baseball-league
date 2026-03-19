<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SMLB — Sunday Morning League Baseball</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-950 text-white">

    {{-- Header --}}
    <header class="border-b border-gray-800 bg-gray-950/90 backdrop-blur sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
            <div class="flex items-center gap-3">
                <svg class="h-8 w-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke-width="1.5"/>
                    <path d="M12 2 C8 6 8 18 12 22" stroke-width="1.5"/>
                    <path d="M12 2 C16 6 16 18 12 22" stroke-width="1.5"/>
                    <path d="M2 12 C6 8 18 8 22 12" stroke-width="1.5"/>
                    <path d="M2 12 C6 16 18 16 22 12" stroke-width="1.5"/>
                </svg>
                <span class="text-xl font-bold tracking-tight">SMLB</span>
            </div>
            <nav class="flex items-center gap-4">
                @auth
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('adm') }}" class="text-sm text-gray-300 hover:text-white transition">Admin Panel</a>
                    @else
                        <a href="{{ route('cp') }}" class="text-sm text-gray-300 hover:text-white transition">My Panel</a>
                    @endif
                @else
                    <a href="{{ route('login') }}" class="text-sm text-gray-300 hover:text-white transition">Log in</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" class="text-sm bg-red-600 hover:bg-red-500 text-white px-4 py-2 rounded-lg font-medium transition">Register</a>
                    @endif
                @endauth
            </nav>
        </div>
    </header>

    {{-- Hero --}}
    <section class="relative overflow-hidden py-24 sm:py-36">
        <div class="absolute inset-0 bg-gradient-to-br from-red-900/20 via-gray-950 to-blue-900/20"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="inline-flex items-center gap-2 bg-red-600/20 border border-red-500/30 text-red-400 text-sm font-medium px-4 py-1.5 rounded-full mb-6">
                <span class="w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                Season in progress
            </div>
            <h1 class="text-5xl sm:text-7xl font-extrabold tracking-tight mb-6">
                Sunday Morning<br>
                <span class="text-red-500">League Baseball</span>
            </h1>
            <p class="text-xl text-gray-400 max-w-2xl mx-auto mb-10">
                Your home for schedules, standings, stats, and everything SMLB. Follow your team all season long.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                @auth
                    @if(auth()->user()->isAdmin())
                        <a href="{{ route('adm') }}" class="bg-red-600 hover:bg-red-500 text-white font-semibold px-8 py-3 rounded-lg transition text-lg">Go to Admin Panel</a>
                    @else
                        <a href="{{ route('cp') }}" class="bg-red-600 hover:bg-red-500 text-white font-semibold px-8 py-3 rounded-lg transition text-lg">Go to My Panel</a>
                    @endif
                @else
                    <a href="{{ route('register') }}" class="bg-red-600 hover:bg-red-500 text-white font-semibold px-8 py-3 rounded-lg transition text-lg">Join the League</a>
                    <a href="{{ route('login') }}" class="bg-gray-800 hover:bg-gray-700 text-white font-semibold px-8 py-3 rounded-lg transition text-lg">Sign In</a>
                @endauth
            </div>
        </div>
    </section>

    {{-- Stats Bar --}}
    <section class="border-y border-gray-800 bg-gray-900/50 py-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-3 gap-8 text-center">
            <div>
                <div class="text-4xl font-bold text-red-500">8</div>
                <div class="text-gray-400 mt-1 text-sm uppercase tracking-wide">Teams</div>
            </div>
            <div>
                <div class="text-4xl font-bold text-red-500">120+</div>
                <div class="text-gray-400 mt-1 text-sm uppercase tracking-wide">Players</div>
            </div>
            <div>
                <div class="text-4xl font-bold text-red-500">10+</div>
                <div class="text-gray-400 mt-1 text-sm uppercase tracking-wide">Seasons</div>
            </div>
        </div>
    </section>

    {{-- Features --}}
    <section class="py-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-center mb-16">Everything you need to follow the league</h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-8">
                <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
                    <div class="text-red-500 mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Schedule</h3>
                    <p class="text-gray-400 text-sm">View the full season schedule, game times, and locations for every team.</p>
                </div>
                <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
                    <div class="text-red-500 mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Standings</h3>
                    <p class="text-gray-400 text-sm">Live standings updated after every game. See who's leading the division.</p>
                </div>
                <div class="bg-gray-900 border border-gray-800 rounded-xl p-6">
                    <div class="text-red-500 mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-2">Player Stats</h3>
                    <p class="text-gray-400 text-sm">Batting averages, ERA, RBIs and more. Full stats for every player.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Footer --}}
    <footer class="border-t border-gray-800 py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2 text-gray-500 text-sm">
                <svg class="h-5 w-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <circle cx="12" cy="12" r="10" stroke-width="1.5"/>
                </svg>
                SMLB &copy; {{ date('Y') }}
            </div>
            <div class="text-gray-600 text-sm">Sunday Morning League Baseball</div>
        </div>
    </footer>

</body>
</html>
