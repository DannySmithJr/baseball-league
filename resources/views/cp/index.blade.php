<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Control Panel</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
            <div class="mb-4 bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
            @endif
            @if(session('error'))
            <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
            @endif

            <div class="flex gap-6">

                {{-- ═══ LEFT SIDEBAR MENU ═══ --}}
                <div class="w-52 shrink-0">
                    <nav class="space-y-0.5 sticky top-24">
                        <p class="px-3 py-2 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Menu</p>

                        @if($isGm)
                            <a href="#team-management" class="block px-3 py-2 rounded-lg text-sm font-semibold text-gray-800 bg-gray-100">Team Management</a>
                            <a href="#gm-teams" class="block px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition">GM Teams</a>
                            <a href="#player-management" class="block px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition">Player Management</a>
                            <a href="#account" class="block px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition">Account Settings</a>
                        @elseif($hasCcp)
                            <a href="#player-management" class="block px-3 py-2 rounded-lg text-sm font-semibold text-gray-800 bg-gray-100">Player Management</a>
                            <a href="#account" class="block px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition">Account Settings</a>
                            <a href="#gm-teams" class="block px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition">Become a GM</a>
                        @else
                            <a href="#account" class="block px-3 py-2 rounded-lg text-sm font-semibold text-gray-800 bg-gray-100">Account Settings</a>
                            <a href="#gm-teams" class="block px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition">Become a GM</a>
                            <a href="#player-management" class="block px-3 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition">Create a Player</a>
                        @endif
                    </nav>
                </div>

                {{-- ═══ RIGHT PANEL ═══ --}}
                <div class="flex-1 space-y-6 min-w-0">

                    {{-- Welcome --}}
                    <div class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900">Welcome, {{ $user->name }}</h3>
                        <p class="text-gray-500 text-sm mt-1">
                            {{ $settings->league_abbr ?? 'League' }} Control Panel
                            @if($isGm && $gmRecord)
                                — <span class="text-red-600 font-semibold">GM: {{ $gmRecord->team_name }} {{ $gmRecord->team_nickname ?? '' }}</span>
                            @elseif($pendingGm)
                                — <span class="text-yellow-600 font-semibold">GM Request Pending</span>
                            @elseif($hasCcp && $ccpPlayer)
                                — <span class="text-red-600 font-semibold">{{ $ccpPlayer->first_name }} {{ $ccpPlayer->last_name }} ({{ $ccpPlayer->team_abbr ?? 'FA' }})</span>
                            @endif
                        </p>
                    </div>

                    @if($isGm)
                    {{-- ══ TEAM MANAGEMENT ══ --}}
                    <div id="team-management" class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Team Management</h3>
                        @if($gmRecord)
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border">
                                <div>
                                    <p class="font-bold text-gray-900">{{ $gmRecord->team_name }} {{ $gmRecord->team_nickname ?? '' }}</p>
                                    <p class="text-sm text-gray-500">{{ $gmRecord->team_abbr }}</p>
                                </div>
                                <a href="{{ route('team', $gmRecord->team_id) }}" class="text-sm text-red-600 hover:text-red-500 font-medium">View Team →</a>
                            </div>

                            {{-- GM Email --}}
                            <div class="border rounded-lg p-4">
                                <h4 class="font-semibold text-gray-800 text-sm mb-2">Team Email</h4>
                                @if($gmRecord->email_address)
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between"><span class="text-gray-500">Address</span><span class="text-gray-900 font-mono">{{ $gmRecord->email_address }}</span></div>
                                    <div class="flex justify-between"><span class="text-gray-500">Mailbox</span>
                                        <span class="text-gray-600">
                                            <span class="inline-block w-24 bg-gray-200 rounded-full h-2 mr-2 align-middle">
                                                <span class="block bg-green-500 rounded-full h-2" style="width: 5%"></span>
                                            </span>
                                            512 MB
                                        </span>
                                    </div>
                                </div>
                                @else
                                <p class="text-gray-400 text-sm italic">No email assigned yet. Contact an admin to set up your team mailbox.</p>
                                @endif
                            </div>

                            {{-- Internal Messages --}}
                            <div class="border rounded-lg p-4">
                                <h4 class="font-semibold text-gray-800 text-sm mb-2">Team Inbox</h4>
                                <p class="text-gray-400 text-sm italic">Internal messaging coming soon.</p>
                            </div>
                        </div>
                        @endif
                    </div>
                    @endif

                    {{-- ══ GM TEAMS ══ --}}
                    <div id="gm-teams" class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ $isGm ? 'League GMs' : 'Become a GM' }}</h3>

                        {{-- Pending --}}
                        @if($pendingTeams->isNotEmpty())
                        <div class="mb-6">
                            <h4 class="text-xs font-bold text-yellow-600 uppercase tracking-wider mb-2">Pending Approval</h4>
                            <div class="space-y-1">
                                @foreach($pendingTeams as $slot)
                                <div class="flex items-center justify-between p-3 rounded-lg border border-yellow-200 bg-yellow-50">
                                    <div>
                                        <p class="font-medium text-gray-900 text-sm">{{ $slot->team_name }} {{ $slot->team_nickname ?? '' }}</p>
                                        <p class="text-xs text-gray-500">{{ $slot->team_abbr }} · {{ $slot->w ?? 0 }}-{{ $slot->l ?? 0 }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-yellow-700 font-medium">{{ $slot->user_name ?? 'Unknown' }}</p>
                                        <p class="text-[10px] text-yellow-500">Pending</p>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- Available --}}
                        @if($availableTeams->isNotEmpty())
                        <div class="mb-6">
                            <h4 class="text-xs font-bold text-green-600 uppercase tracking-wider mb-2">Available Teams</h4>
                            <div class="space-y-2">
                                @foreach($availableTeams as $slot)
                                @php $farm = $farmRankings[$slot->team_id] ?? null; @endphp
                                <div class="flex items-center justify-between p-3 rounded-lg border border-gray-200 hover:border-green-300 hover:bg-green-50/50 transition">
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-gray-900 text-sm">{{ $slot->team_name }} {{ $slot->team_nickname ?? '' }}</p>
                                        <p class="text-xs text-gray-500">
                                            {{ $slot->team_abbr }} · Record: {{ $slot->w ?? 0 }}-{{ $slot->l ?? 0 }}
                                            @if($farm)
                                            · Farm Rank: #{{ $farm['rank'] }} ({{ $farm['elite_count'] }} elite, {{ $farm['prospect_count'] }} total)
                                            @endif
                                        </p>
                                        @if($farm && $farm['top_prospect'])
                                        <p class="text-[11px] text-gray-400 mt-0.5">Top prospect: {{ $farm['top_prospect'] }} (pot {{ $farm['top_talent'] }}, age {{ $farm['top_age'] }})</p>
                                        @endif
                                    </div>
                                    @if(!$isGm && !$pendingGm && ($settings->gm_registration_enabled ?? false))
                                    <form method="POST" action="{{ route('cp.requestGm') }}" class="ml-3 shrink-0">
                                        @csrf
                                        <input type="hidden" name="team_id" value="{{ $slot->team_id }}">
                                        <button type="submit" class="px-3 py-1.5 text-xs font-semibold text-white bg-red-600 hover:bg-red-500 rounded-lg transition">
                                            Request GM
                                        </button>
                                    </form>
                                    @endif
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        {{-- Filled --}}
                        @if($filledTeams->isNotEmpty())
                        <div>
                            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2">Filled Teams</h4>
                            <div class="space-y-1">
                                @foreach($filledTeams as $slot)
                                @php $farm = $farmRankings[$slot->team_id] ?? null; @endphp
                                <div class="flex items-center justify-between p-3 rounded-lg border border-gray-100 bg-gray-50/50">
                                    <div>
                                        <p class="font-medium text-gray-700 text-sm">{{ $slot->team_name }} {{ $slot->team_nickname ?? '' }}</p>
                                        <p class="text-xs text-gray-400">
                                            {{ $slot->team_abbr }} · {{ $slot->w ?? 0 }}-{{ $slot->l ?? 0 }}
                                            @if($farm) · Farm #{{ $farm['rank'] }} @endif
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-gray-600 font-medium">{{ $slot->user_name ?? '—' }}</p>
                                        <p class="text-[10px] text-green-500">Active GM</p>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        @if(!($settings->gm_registration_enabled ?? false) && !$isGm)
                        <p class="text-gray-400 text-sm mt-4">GM registration is currently closed.</p>
                        @endif
                    </div>

                    @if($hasCcp || !$isGm)
                    {{-- ══ PLAYER MANAGEMENT ══ --}}
                    <div id="player-management" class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ $hasCcp ? 'Player Management' : 'Create a Player' }}</h3>

                        @if($hasCcp && $ccpPlayer)
                        <div class="space-y-4">
                            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg border">
                                <div>
                                    <p class="font-bold text-gray-900">{{ $ccpPlayer->first_name }} {{ $ccpPlayer->last_name }}</p>
                                    <p class="text-sm text-gray-500">
                                        {{ \App\Services\OotpService::POSITIONS[$ccpPlayer->position] ?? '' }}
                                        · Age {{ $ccpPlayer->age ?? '?' }}
                                        · {{ $ccpPlayer->team_abbr ?? 'Free Agent' }}
                                    </p>
                                </div>
                                <a href="{{ route('player', $ccpPlayer->player_id) }}" class="text-sm text-red-600 hover:text-red-500 font-medium">View Player →</a>
                            </div>
                            @if($ccp->draftYear)
                            <p class="text-sm text-gray-500"><span class="font-medium text-gray-700">Draft:</span> {{ $ccp->draftYear }} Rd {{ $ccp->draftRound }}, Pick {{ $ccp->draftPick }}</p>
                            @endif
                        </div>
                        @elseif(!$hasCcp)
                        <div class="text-center py-6">
                            @if($settings->ccp_registration_enabled ?? false)
                            <p class="text-gray-500 mb-4">Create a player to enter the league.</p>
                            <a href="#" class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-500 transition">Create Player</a>
                            @else
                            <p class="text-gray-400">Player creation is currently closed.</p>
                            @endif
                        </div>
                        @endif
                    </div>
                    @endif

                    {{-- ══ ACCOUNT SETTINGS ══ --}}
                    <div id="account" class="bg-white shadow-sm sm:rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Account Settings</h3>

                        <form method="POST" action="{{ route('cp.updateAccount') }}" class="space-y-4">
                            @csrf
                            @method('PATCH')

                            <div class="grid sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                                    <p class="text-gray-900 py-2">{{ $user->name }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                                    <p class="text-gray-900 py-2">{{ $user->email }}</p>
                                </div>
                                <div>
                                    <label for="twitch_username" class="block text-sm font-medium text-gray-700 mb-1">Twitch Username</label>
                                    <input type="text" name="twitch_username" id="twitch_username"
                                        value="{{ old('twitch_username', $user->twitch_username) }}"
                                        placeholder="your_twitch_name"
                                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Member Since</label>
                                    <p class="text-gray-900 py-2">{{ $user->created_at?->format('M j, Y') ?? '—' }}</p>
                                </div>
                            </div>

                            <div class="flex items-center justify-between pt-3 border-t">
                                <a href="{{ route('profile') }}" class="text-sm text-gray-500 hover:text-gray-700">Edit Password / Email →</a>
                                <button type="submit" class="px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-500 transition">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
