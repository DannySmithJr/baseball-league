<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">News Sources</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
            <div class="mb-6 bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
            @endif

            {{-- Add Source --}}
            <div class="bg-white shadow-sm rounded-lg p-6 mb-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Add News Source</h3>
                <form method="POST" action="{{ route('adm.news-sources.store') }}" class="flex flex-wrap gap-3 items-end">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Reporter Name</label>
                        <input type="text" name="name" required placeholder="Jeff Passan"
                            class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-48 focus:ring-2 focus:ring-red-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Outlet</label>
                        <input type="text" name="outlet" required placeholder="sBNN"
                            class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-48 focus:ring-2 focus:ring-red-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Team (blank = league-wide)</label>
                        <select name="team_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-56 focus:ring-2 focus:ring-red-500">
                            <option value="">League-wide</option>
                            @foreach($teams as $t)
                            <option value="{{ $t->team_id }}">{{ $t->name }} {{ $t->nickname ?? '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-500 transition">
                        Add Source
                    </button>
                </form>
            </div>

            {{-- Existing Sources --}}
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wider border-b">
                            <th class="text-left py-3 px-4">Reporter</th>
                            <th class="text-left py-3 px-4">Outlet</th>
                            <th class="text-left py-3 px-4">Team</th>
                            <th class="text-right py-3 px-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($sources as $s)
                        <tr class="hover:bg-gray-50">
                            <td class="py-3 px-4 font-medium text-gray-900">{{ $s->name }}</td>
                            <td class="py-3 px-4 text-gray-600">{{ $s->outlet }}</td>
                            <td class="py-3 px-4 text-gray-600">
                                @if($s->team_name)
                                    {{ $s->team_abbr }} — {{ $s->team_name }}
                                @else
                                    <span class="text-gray-400">League-wide</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                <form method="POST" action="{{ route('adm.news-sources.destroy', $s->source_id) }}" class="inline">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-500 hover:text-red-700 text-xs font-medium">Remove</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                        @if($sources->isEmpty())
                        <tr><td colspan="4" class="py-8 text-center text-gray-400">No news sources configured.</td></tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
