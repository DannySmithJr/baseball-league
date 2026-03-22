{{-- Team horizontal nav bar --}}
@php
$teamId   = $team->team_id;
$isParent = (int)($team->parent_team_id ?? 0) === 0;
$teamNav = [
    ['route' => 'team',           'label' => 'Home'],
    ['route' => 'team.history',   'label' => 'History'],
    ['route' => 'team.injuries',  'label' => 'Injuries'],
];
if ($isParent) {
    $teamNav[] = ['route' => 'team.finances', 'label' => 'Finances'];
    $teamNav[] = ['route' => 'team.minors',   'label' => 'Farm'];
}
@endphp
<div class="border-b border-gray-800 bg-gray-900/60">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <nav class="flex gap-1 -mb-px overflow-x-auto">
            @foreach($teamNav as $nav)
            <a href="{{ route($nav['route'], $teamId) }}"
               class="whitespace-nowrap px-4 py-3 text-sm font-medium border-b-2 transition
                      {{ request()->routeIs($nav['route'])
                         ? 'border-red-500 text-white'
                         : 'border-transparent text-gray-400 hover:text-white hover:border-gray-600' }}">
                {{ $nav['label'] }}
            </a>
            @endforeach
        </nav>
    </div>
</div>
