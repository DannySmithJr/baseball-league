<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Admin Panel
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Welcome Card --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-1">Admin Control Panel</h3>
                <p class="text-gray-500 text-sm">Manage the league, teams, players, and settings.</p>
            </div>

            {{-- Admin Quick Links --}}
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-red-500 mb-3">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <h4 class="font-semibold text-gray-800 mb-1">Users</h4>
                    <p class="text-gray-500 text-sm">Manage registered users and roles.</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-red-500 mb-3">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/></svg>
                    </div>
                    <h4 class="font-semibold text-gray-800 mb-1">Teams</h4>
                    <p class="text-gray-500 text-sm">Add, edit, and manage teams.</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-red-500 mb-3">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <h4 class="font-semibold text-gray-800 mb-1">Schedule</h4>
                    <p class="text-gray-500 text-sm">Create and manage game schedules.</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-red-500 mb-3">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    </div>
                    <h4 class="font-semibold text-gray-800 mb-1">Scores</h4>
                    <p class="text-gray-500 text-sm">Enter and update game scores.</p>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
