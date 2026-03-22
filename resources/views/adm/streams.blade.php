<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Stream Schedule</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="mb-2 bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-lg text-sm">
                    {{ session('success') }}
                </div>
            @endif

            {{-- Add New Entry Form --}}
            <div class="bg-white shadow-sm rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Add Schedule Entry</h3>

                <form method="POST" action="{{ route('adm.streams.store') }}">
                    @csrf

                    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">

                        {{-- Day of Week --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Day of Week</label>
                            <select name="day_of_week"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                                <option value="0" {{ old('day_of_week') == '0' ? 'selected' : '' }}>Sunday</option>
                                <option value="1" {{ old('day_of_week') == '1' ? 'selected' : '' }}>Monday</option>
                                <option value="2" {{ old('day_of_week') == '2' ? 'selected' : '' }}>Tuesday</option>
                                <option value="3" {{ old('day_of_week') == '3' ? 'selected' : '' }}>Wednesday</option>
                                <option value="4" {{ old('day_of_week') == '4' ? 'selected' : '' }}>Thursday</option>
                                <option value="5" {{ old('day_of_week', '5') == '5' ? 'selected' : '' }}>Friday</option>
                                <option value="6" {{ old('day_of_week') == '6' ? 'selected' : '' }}>Saturday</option>
                            </select>
                            @error('day_of_week')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>

                        {{-- Time ET --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Time (ET)</label>
                            <input type="time" name="time_et" value="{{ old('time_et', '18:00') }}"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                            @error('time_et')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>

                        {{-- Label --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Label</label>
                            <input type="text" name="label" value="{{ old('label') }}" placeholder="e.g. Friday Night Game"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                            @error('label')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>

                        {{-- Stream Type --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Stream Type</label>
                            <select name="stream_type"
                                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-500">
                                <option value="gotd" {{ old('stream_type') == 'gotd' ? 'selected' : '' }}>Game of the Day</option>
                                <option value="gotn" {{ old('stream_type') == 'gotn' ? 'selected' : '' }}>Game of the Night</option>
                            </select>
                            @error('stream_type')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>

                    </div>

                    <div class="mt-4 flex justify-end">
                        <button type="submit"
                            class="bg-red-600 hover:bg-red-500 text-white font-semibold px-6 py-2 rounded-lg transition text-sm">
                            Add Entry
                        </button>
                    </div>
                </form>
            </div>

            {{-- Existing Entries Table --}}
            <div class="bg-white shadow-sm rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="text-lg font-semibold text-gray-900">Current Schedule</h3>
                </div>

                @if($schedules->isEmpty())
                    <div class="px-6 py-8 text-center text-gray-400 text-sm">
                        No schedule entries yet. Add one above.
                    </div>
                @else
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600 uppercase text-xs tracking-wider">
                            <tr>
                                <th class="px-6 py-3 text-left">Day</th>
                                <th class="px-6 py-3 text-left">Time (ET)</th>
                                <th class="px-6 py-3 text-left">Label</th>
                                <th class="px-6 py-3 text-left">Type</th>
                                <th class="px-6 py-3 text-center">Active</th>
                                <th class="px-6 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @php
                                $days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                            @endphp
                            @foreach($schedules as $entry)
                            <tr class="hover:bg-gray-50 transition">
                                <td class="px-6 py-3 font-medium text-gray-800">
                                    {{ $days[$entry->day_of_week] ?? $entry->day_of_week }}
                                </td>
                                <td class="px-6 py-3 text-gray-700">{{ $entry->time_et }}</td>
                                <td class="px-6 py-3 text-gray-700">{{ $entry->label }}</td>
                                <td class="px-6 py-3">
                                    @if($entry->stream_type === 'gotd')
                                        <span class="inline-block bg-blue-100 text-blue-700 text-xs font-semibold px-2 py-0.5 rounded">Game of the Day</span>
                                    @else
                                        <span class="inline-block bg-indigo-100 text-indigo-700 text-xs font-semibold px-2 py-0.5 rounded">Game of the Night</span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-center">
                                    @if($entry->active)
                                        <span class="inline-block w-3 h-3 rounded-full bg-green-400" title="Active"></span>
                                    @else
                                        <span class="inline-block w-3 h-3 rounded-full bg-gray-300" title="Inactive"></span>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <form method="POST" action="{{ route('adm.streams.destroy', $entry->id) }}"
                                          onsubmit="return confirm('Delete this schedule entry?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="text-red-500 hover:text-red-700 text-xs font-semibold transition">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
