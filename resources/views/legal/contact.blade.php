@extends('layouts.public')
@section('title', 'Contact Us')
@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <h1 class="text-2xl font-black text-white mb-2">Contact Us</h1>
    <p class="text-sm text-gray-500 mb-8">We'd love to hear from you.</p>

    <div class="space-y-8 text-sm text-gray-300 leading-relaxed">
        <section>
            <h2 class="text-base font-bold text-white mb-2">General Inquiries</h2>
            <p>For general questions about the sMLB league, site features, or content, reach out through our community channels.</p>
        </section>
        <section>
            <h2 class="text-base font-bold text-white mb-2">Community</h2>
            <div class="space-y-2 mt-3">
                @if($settings->discord_url)
                <p><span class="text-gray-500">Discord:</span> <a href="{{ $settings->discord_url }}" target="_blank" rel="noopener" class="text-red-400 hover:text-red-300">Join our Discord server</a></p>
                @endif
                @if($settings->twitch_url)
                <p><span class="text-gray-500">Twitch:</span> <a href="{{ $settings->twitch_url }}" target="_blank" rel="noopener" class="text-red-400 hover:text-red-300">Watch us on Twitch</a></p>
                @endif
                @if($settings->x_url)
                <p><span class="text-gray-500">X (Twitter):</span> <a href="{{ $settings->x_url }}" target="_blank" rel="noopener" class="text-red-400 hover:text-red-300">Follow on X</a></p>
                @endif
            </div>
        </section>
        <section>
            <h2 class="text-base font-bold text-white mb-2">Privacy & Legal</h2>
            <p>For privacy requests, data deletion, or legal matters, please reach out via our community Discord and direct message an administrator.</p>
        </section>
        <section>
            <h2 class="text-base font-bold text-white mb-2">Bug Reports</h2>
            <p>Found an issue with the site? Please report it through our Discord so we can get it fixed quickly.</p>
        </section>
    </div>
</div>
@endsection
