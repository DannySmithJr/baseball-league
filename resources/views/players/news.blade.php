@extends('layouts.public')
@section('title', $player->first_name . ' ' . $player->last_name . ' — News')

@section('content')

@include('players._header')

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <h2 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-4">News</h2>

    @if($news->isNotEmpty())
    @php
        $newsSources = \DB::table('news_sources')->where('active', true)->get();
        $leagueSource = $newsSources->firstWhere('team_id', null);
        $teamSources  = $newsSources->whereNotNull('team_id')->keyBy('team_id');

        $seasonYear = app(\App\Services\OotpService::class)->seasonYear() ?? (int)date('Y');
        $newsTeamLogos = [];
        foreach (\DB::table('teams')->where('level', 1)->get(['team_id', 'logo_file_name']) as $t) {
            $base = pathinfo($t->logo_file_name ?? '', PATHINFO_FILENAME);
            $newsTeamLogos[(int)$t->team_id] = \App\Services\OotpService::logoForYear($base, $seasonYear);
        }
    @endphp
    <div class="space-y-3">
        @foreach($news as $article)
        @php
            $articleTeamId = (int)$article->team_id_0;
            $source = $teamSources[$articleTeamId] ?? $leagueSource ?? null;
            $teamLogo = $newsTeamLogos[$articleTeamId] ?? null;
            $parsedBody = \App\Services\OotpService::parseMessageBody($article->body);
        @endphp
        <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
            <div class="flex items-start gap-3">
                <div class="shrink-0">
                    @if($teamLogo)
                        <img src="/images/logos/{{ $teamLogo }}" class="w-10 h-10 object-contain" alt="">
                    @else
                        <div class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center text-red-500">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M2 12h20M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>
                        </div>
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-0.5">
                        <span class="text-xs font-bold text-gray-400 uppercase">
                            @if($articleTeamId && isset($newsTeamLogos[$articleTeamId]))
                                {{ \DB::table('teams')->where('team_id', $articleTeamId)->value('nickname') ?? '' }}
                            @else
                                {{ $settings->league_abbr ?? 'sMLB' }}
                            @endif
                        </span>
                    </div>
                    <h3 class="text-sm font-bold text-white mb-1">{{ $article->subject }}</h3>
                    <p class="text-sm text-gray-300 leading-relaxed mb-2">{!! $parsedBody !!}</p>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-400 font-semibold">{{ $source->name ?? $leagueSource->name ?? 'BNN' }}</span>
                        <span class="text-xs text-gray-500">·</span>
                        <span class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($article->date)->format('M j, Y') }}</span>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="bg-gray-900 border border-gray-800 rounded-xl p-12 text-center text-gray-500 text-sm">
        No news articles found for this player.
    </div>
    @endif

</div>
@endsection
