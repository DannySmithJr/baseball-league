@extends('layouts.public')
@section('title', 'Privacy Policy')
@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <h1 class="text-2xl font-black text-white mb-2">Privacy Policy</h1>
    <p class="text-sm text-gray-500 mb-8">Last updated: March 2026</p>

    <div class="space-y-8 text-sm text-gray-300 leading-relaxed">
        <section>
            <h2 class="text-base font-bold text-white mb-2">1. Information We Collect</h2>
            <p>We may collect your name, email address, and account credentials when you register. We also collect usage data such as pages visited and features used.</p>
        </section>
        <section>
            <h2 class="text-base font-bold text-white mb-2">2. How We Use Your Information</h2>
            <p>Your information is used to operate the site, provide your account, send league updates, and improve our services. We do not sell your personal data to third parties.</p>
        </section>
        <section>
            <h2 class="text-base font-bold text-white mb-2">3. Cookies</h2>
            <p>We use cookies to maintain your session and remember preferences. You can manage cookie settings at any time. See our <a href="{{ route('legal.cookies') }}" class="text-red-400 hover:text-red-300">Cookies Settings</a> page for details.</p>
        </section>
        <section>
            <h2 class="text-base font-bold text-white mb-2">4. Data Retention</h2>
            <p>We retain account data for as long as your account is active. You may request deletion of your data at any time by contacting us.</p>
        </section>
        <section>
            <h2 class="text-base font-bold text-white mb-2">5. Third-Party Services</h2>
            <p>We may link to third-party platforms such as Twitch, YouTube, or social media. These services have their own privacy policies which we encourage you to review.</p>
        </section>
        <section>
            <h2 class="text-base font-bold text-white mb-2">6. Contact</h2>
            <p>Privacy questions? <a href="{{ route('legal.contact') }}" class="text-red-400 hover:text-red-300">Contact us</a>.</p>
        </section>
    </div>
</div>
@endsection
