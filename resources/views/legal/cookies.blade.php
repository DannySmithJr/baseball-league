@extends('layouts.public')
@section('title', 'Cookies Settings')
@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <h1 class="text-2xl font-black text-white mb-2">Cookies Settings</h1>
    <p class="text-sm text-gray-500 mb-8">Last updated: March 2026</p>

    <div class="space-y-8 text-sm text-gray-300 leading-relaxed">
        <section>
            <h2 class="text-base font-bold text-white mb-2">What Are Cookies?</h2>
            <p>Cookies are small text files stored on your device when you visit a website. They help the site remember your preferences and keep you logged in between visits.</p>
        </section>
        <section>
            <h2 class="text-base font-bold text-white mb-2">Cookies We Use</h2>
            <div class="space-y-4 mt-3">
                <div class="border border-gray-800 rounded-lg p-4">
                    <p class="font-semibold text-white mb-1">Essential Cookies <span class="text-xs text-green-400 font-normal ml-2">Always Active</span></p>
                    <p class="text-gray-400">Required for the site to function. These include your session cookie (keeps you logged in) and CSRF token (protects against cross-site attacks). These cannot be disabled.</p>
                </div>
                <div class="border border-gray-800 rounded-lg p-4">
                    <p class="font-semibold text-white mb-1">Preference Cookies <span class="text-xs text-gray-500 font-normal ml-2">Optional</span></p>
                    <p class="text-gray-400">Remember your settings and preferences across visits, such as display options and UI state.</p>
                </div>
                <div class="border border-gray-800 rounded-lg p-4">
                    <p class="font-semibold text-white mb-1">Analytics Cookies <span class="text-xs text-gray-500 font-normal ml-2">Optional</span></p>
                    <p class="text-gray-400">Help us understand how visitors use the site so we can improve it. No personally identifiable information is collected.</p>
                </div>
            </div>
        </section>
        <section>
            <h2 class="text-base font-bold text-white mb-2">Managing Cookies</h2>
            <p>You can control and delete cookies through your browser settings. Disabling non-essential cookies will not affect your ability to use the core features of this site. Refer to your browser's help documentation for instructions.</p>
        </section>
        <section>
            <h2 class="text-base font-bold text-white mb-2">Questions?</h2>
            <p><a href="{{ route('legal.contact') }}" class="text-red-400 hover:text-red-300">Contact us</a> if you have any questions about how we use cookies.</p>
        </section>
    </div>
</div>
@endsection
