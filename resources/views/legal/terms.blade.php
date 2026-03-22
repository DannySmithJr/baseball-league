@extends('layouts.public')
@section('title', 'Terms of Use')
@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <h1 class="text-2xl font-black text-white mb-2">Terms of Use</h1>
    <p class="text-sm text-gray-500 mb-8">Last updated: March 2026</p>

    <div class="space-y-8 text-sm text-gray-300 leading-relaxed">
        <section>
            <h2 class="text-base font-bold text-white mb-2">1. Acceptance of Terms</h2>
            <p>By accessing or using the sMLB One website and services, you agree to be bound by these Terms of Use. If you do not agree, please do not use this site.</p>
        </section>
        <section>
            <h2 class="text-base font-bold text-white mb-2">2. Use of the Site</h2>
            <p>This site is provided for entertainment and informational purposes related to the sMLB simulated baseball league. You agree not to misuse, scrape, or reproduce content without permission.</p>
        </section>
        <section>
            <h2 class="text-base font-bold text-white mb-2">3. Intellectual Property</h2>
            <p>All content, logos, statistics, and league data are the property of sMLB One Media. Simulation data is generated via OOTP Baseball. Unauthorized reproduction is prohibited.</p>
        </section>
        <section>
            <h2 class="text-base font-bold text-white mb-2">4. Disclaimer of Warranties</h2>
            <p>This site is provided "as is" without warranties of any kind. We do not guarantee the accuracy of simulation data, statistics, or projections.</p>
        </section>
        <section>
            <h2 class="text-base font-bold text-white mb-2">5. Changes to Terms</h2>
            <p>We reserve the right to update these Terms at any time. Continued use of the site after changes constitutes acceptance of the updated Terms.</p>
        </section>
        <section>
            <h2 class="text-base font-bold text-white mb-2">6. Contact</h2>
            <p>Questions about these Terms? <a href="{{ route('legal.contact') }}" class="text-red-400 hover:text-red-300">Contact us</a>.</p>
        </section>
    </div>
</div>
@endsection
