<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">League Settings</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-6 bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-lg text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <form method="POST" action="{{ route('adm.settings.update') }}">
                @csrf
                @method('PATCH')

                {{-- Season Info --}}
                <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Season Info</h3>
                    <div class="grid sm:grid-cols-2 gap-4">

                        {{-- Season (manual) --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Season #</label>
                            <input type="number" name="season" value="{{ old('season', $settings->season ?? 1) }}" min="1"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                        </div>

                        {{-- Sim Length (manual) --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Sim Length (days)</label>
                            <input type="number" name="sim_length" value="{{ old('sim_length', $settings->sim_length ?? 7) }}" min="1" max="365"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                        </div>

                        {{-- Next Sim (manual) --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Next Sim</label>
                            <input type="datetime-local" name="next_sim"
                                value="{{ old('next_sim', $settings->next_sim ? \Carbon\Carbon::parse($settings->next_sim)->format('Y-m-d\TH:i') : '') }}"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                        </div>

                        {{-- Year (read-only from leagues table) --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Year
                                <span class="text-gray-400 font-normal text-xs ml-1">(from OOTP)</span>
                            </label>
                            <input type="text" readonly
                                value="{{ $ootp->seasonYear() ?? '—' }}"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-500 cursor-not-allowed">
                        </div>

                        {{-- Game Date (read-only from leagues table) --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Game Date
                                <span class="text-gray-400 font-normal text-xs ml-1">(from OOTP)</span>
                            </label>
                            <input type="text" readonly
                                value="{{ $ootp->gameDate() ? \Carbon\Carbon::parse($ootp->gameDate())->format('F j, Y') : '—' }}"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-500 cursor-not-allowed">
                        </div>

                    </div>
                </div>

                {{-- League Info --}}
                <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">League Info</h3>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">League Name</label>
                            <input type="text" name="league_name" value="{{ old('league_name', $settings->league_name) }}"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                            @error('league_name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">League Abbreviation</label>
                            <input type="text" name="league_abbr" value="{{ old('league_abbr', $settings->league_abbr) }}"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                            @error('league_abbr')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </div>

                {{-- Registration Toggles --}}
                <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Registration</h3>
                    <div class="space-y-4">
                        @foreach([
                            'registration_enabled'     => 'Site Registration',
                            'gm_registration_enabled'  => 'GM Registration',
                            'ccp_registration_enabled' => 'CCP Registration',
                        ] as $field => $label)
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-700">{{ $label }}</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="{{ $field }}" value="1" class="sr-only peer"
                                    {{ old($field, $settings->$field) ? 'checked' : '' }}>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-red-500 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-red-600"></div>
                            </label>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- External URLs --}}
                <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">External Links</h3>
                    <div class="space-y-4">
                        @foreach([
                            'twitch_url'      => 'Twitch URL',
                            'discord_url'     => 'Discord URL',
                            'stats_url'       => 'Stats+ URL',
                            'league_file_url' => 'League File URL',
                            'kofi_url'        => 'Ko-Fi URL',
                            'x_url'           => 'X (Twitter) URL',
                            'youtube_url'     => 'YouTube URL',
                        ] as $field => $label)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">{{ $label }}</label>
                            <input type="url" name="{{ $field }}" value="{{ old($field, $settings->$field) }}"
                                placeholder="https://"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                            @error($field)<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="bg-red-600 hover:bg-red-500 text-white font-semibold px-6 py-2 rounded-lg transition text-sm">
                        Save Settings
                    </button>
                </div>
            </form>

        </div>
    </div>
</x-app-layout>
