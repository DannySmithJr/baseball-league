@extends('layouts.public')
@section('title', 'Do Not Sell or Share My Personal Data')
@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <h1 class="text-2xl font-black text-white mb-2">Do Not Sell or Share My Personal Data</h1>
    <p class="text-sm text-gray-500 mb-8">Last updated: March 2026</p>

    <div class="space-y-8 text-sm text-gray-300 leading-relaxed">
        <section>
            <h2 class="text-base font-bold text-white mb-2">Our Commitment</h2>
            <p>sMLB One Media does not sell your personal data to third parties. We do not share personal information with advertisers or data brokers.</p>
        </section>
        <section>
            <h2 class="text-base font-bold text-white mb-2">What Data We Hold</h2>
            <p>If you have created an account, we hold your username and email address. This data is used solely to operate your account and is never sold or shared for commercial purposes.</p>
        </section>
        <section>
            <h2 class="text-base font-bold text-white mb-2">Your Rights</h2>
            <p>Depending on your jurisdiction, you may have the right to:</p>
            <ul class="list-disc list-inside mt-2 space-y-1 text-gray-400">
                <li>Know what personal data we hold about you</li>
                <li>Request correction of inaccurate data</li>
                <li>Request deletion of your data</li>
                <li>Opt out of any data sharing</li>
            </ul>
        </section>
        <section>
            <h2 class="text-base font-bold text-white mb-2">How to Exercise Your Rights</h2>
            <p>To request access to, correction of, or deletion of your personal data, please <a href="{{ route('legal.contact') }}" class="text-red-400 hover:text-red-300">contact us</a> via our community Discord.</p>
        </section>
        <section>
            <h2 class="text-base font-bold text-white mb-2">Third-Party Links</h2>
            <p>This site may link to third-party platforms (Twitch, YouTube, Discord). We are not responsible for their data practices. Please review their respective privacy policies.</p>
        </section>
    </div>
</div>
@endsection
