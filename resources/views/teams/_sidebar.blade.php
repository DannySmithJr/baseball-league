{{-- Team sidebar — left column on all team pages --}}
@php
$teamId   = $team->team_id;
$isParent = (int)($team->parent_team_id ?? 0) === 0;
@endphp

{{-- Organization / Minor Leagues --}}
@if($isParent && isset($affiliates) && $affiliates->isNotEmpty())
<div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-800">
        <h2 class="font-semibold text-sm text-gray-400 uppercase tracking-wider">Organization</h2>
    </div>
    <div class="p-2 space-y-1">
        {{-- Parent team --}}
        <a href="{{ route('team', $teamId) }}"
           class="block px-3 py-2 rounded-lg transition
                  {{ request()->routeIs('team') && request()->route('id') == $teamId
                     ? 'bg-red-900/20 border border-red-800/40'
                     : 'hover:bg-gray-800/50' }}">
            <p class="text-sm text-red-400 font-semibold">{{ $team->name }} {{ $team->nickname ?? '' }}</p>
            @if($record)
            @php
                $parentLeads = isset($divisionStandings) && $divisionStandings
                    && $divisionStandings['teams']->first()
                    && (int)$divisionStandings['teams']->first()->team_id === $teamId;
            @endphp
            <div class="flex items-center justify-between mt-0.5">
                <span class="text-xs text-gray-500">MLB</span>
                <span class="text-sm font-bold {{ $parentLeads ? 'text-yellow-400' : 'text-gray-400' }}">{{ $record->w }}-{{ $record->l }}</span>
            </div>
            @endif
        </a>

        {{-- Affiliates --}}
        @foreach($affiliates as $aff)
        @php
            $recClass = !empty($aff->leads_division) ? 'text-yellow-400'
                : (($aff->w > $aff->l) ? 'text-green-400' : (($aff->w < $aff->l) ? 'text-red-400' : 'text-gray-400'));
        @endphp
        <a href="{{ route('team', $aff->team_id) }}"
           class="block px-3 py-2 rounded-lg hover:bg-gray-800/50 transition">
            <p class="text-sm text-white font-medium">{{ $aff->name }} {{ $aff->nickname ?? '' }}</p>
            <div class="flex items-center justify-between mt-0.5">
                <span class="text-xs text-gray-500">{{ $aff->level_label }}</span>
                <span class="text-sm font-bold {{ $recClass }}">{{ $aff->w }}-{{ $aff->l }}</span>
            </div>
        </a>
        @endforeach
    </div>
</div>
@elseif(!$isParent && isset($parentTeam) && $parentTeam)
{{-- Minor league team — show parent org link --}}
<div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-800">
        <h2 class="font-semibold text-sm text-gray-400 uppercase tracking-wider">Organization</h2>
    </div>
    <div class="p-3">
        <a href="{{ route('team', $parentTeam->team_id) }}"
           class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-800/50 transition">
            <div class="min-w-0">
                <p class="text-sm text-red-400 font-semibold truncate">{{ $parentTeam->name }} {{ $parentTeam->nickname ?? '' }}</p>
                <p class="text-[10px] text-gray-500">Parent Organization</p>
            </div>
        </a>
    </div>
</div>
@endif

{{-- Team Info --}}
<div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-800">
        <h2 class="font-semibold text-sm text-gray-400 uppercase tracking-wider">Team Info</h2>
    </div>
    <div class="p-4 space-y-2 text-sm">
        @foreach([
            'Ballpark'  => $team->park_name ?? null,
            'League'    => $team->sub_league_name ?? null,
            'Division'  => $team->division_name ?? null,
        ] as $label => $val)
        @if($val)
        <div class="flex justify-between gap-2">
            <span class="text-gray-500">{{ $label }}</span>
            <span class="text-gray-300 text-right">{{ $val }}</span>
        </div>
        @endif
        @endforeach
        @if($record)
        <div class="flex justify-between gap-2">
            <span class="text-gray-500">Record</span>
            <span class="text-gray-300">{{ $record->w }}-{{ $record->l }}</span>
        </div>
        @if(isset($record->streak))
        <div class="flex justify-between gap-2">
            <span class="text-gray-500">Streak</span>
            <span class="text-gray-300">{{ $record->streak > 0 ? 'W'.$record->streak : ($record->streak < 0 ? 'L'.abs($record->streak) : '—') }}</span>
        </div>
        @endif
        @endif
    </div>
</div>
