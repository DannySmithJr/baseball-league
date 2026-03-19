<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            My Panel
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Welcome Card --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-1">Welcome back, {{ auth()->user()->name }}!</h3>
                <p class="text-gray-500 text-sm">This is your SMLB member control panel.</p>
            </div>

            {{-- Quick Links --}}
            <div class="grid sm:grid-cols-3 gap-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-red-500 mb-3">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <h4 class="font-semibold text-gray-800 mb-1">Schedule</h4>
                    <p class="text-gray-500 text-sm">View upcoming games and results.</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-red-500 mb-3">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <h4 class="font-semibold text-gray-800 mb-1">Standings</h4>
                    <p class="text-gray-500 text-sm">Check current league standings.</p>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-red-500 mb-3">
                        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <h4 class="font-semibold text-gray-800 mb-1">My Profile</h4>
                    <p class="text-gray-500 text-sm">Update your account information.</p>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
