@extends('layouts.public')
@section('title', $player->first_name . ' ' . $player->last_name . ' — Ratings')

@section('content')
@php $activeTab = 'player.ratings'; @endphp
@include('players._header')

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-10">

    @if($ratings)
    @php
        $toScout = fn($v) => (int)$v;

        // OOTP-matched colors from styles.css (datac0-datac6)
        $barBg = fn($v) => match(true) {
            $v >= 80 => 'background:#0099FF',  // datac6 blue
            $v >= 70 => 'background:#00C6A2',  // datac5 teal
            $v >= 60 => 'background:#65E212',  // datac4 green
            $v >= 50 => 'background:#FFE10B',  // datac3 yellow
            $v >= 40 => 'background:#fa7000',  // datac2 orange
            $v >= 30 => 'background:#FF6666',  // datac1 red
            default  => 'background:#555555',  // datac0 gray
        };

        $barBgFaded = fn($v) => match(true) {
            $v >= 80 => 'background:rgba(0,153,255,0.25)',
            $v >= 70 => 'background:rgba(0,198,162,0.25)',
            $v >= 60 => 'background:rgba(101,226,18,0.25)',
            $v >= 50 => 'background:rgba(255,225,11,0.25)',
            $v >= 40 => 'background:rgba(250,112,0,0.25)',
            $v >= 30 => 'background:rgba(255,102,102,0.25)',
            default  => 'background:rgba(85,85,85,0.25)',
        };

        $textColor = fn($v) => match(true) {
            $v >= 80 => 'color:#0099FF',
            $v >= 70 => 'color:#00C6A2',
            $v >= 60 => 'color:#65E212',
            $v >= 50 => 'color:#FFE10B',
            $v >= 40 => 'color:#fa7000',
            $v >= 30 => 'color:#FF6666',
            default  => 'color:#555555',
        };

        $armSlots = [1 => 'Normal', 2 => 'Sidearm', 3 => 'Over the Top', 4 => 'Submarine'];

        // Check if player is a pitcher
        $isPitcherPlayer = (int)($player->position ?? 0) === 1;

        // Check if pitching ratings are meaningful (any > 1)
        $hasRealPitching = max(
            (int)($ratings->pitching_ratings_overall_stuff ?? 0),
            (int)($ratings->pitching_ratings_overall_movement ?? 0),
            (int)($ratings->pitching_ratings_overall_control ?? 0)
        ) > 5;
    @endphp

    <section>
        {{-- Top row: 4 cards — primary pair first --}}
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">

            {{-- Batting Ratings --}}
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-4 {{ $isPitcherPlayer ? 'order-3' : 'order-1' }}">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Basic Batting Ratings</h3>
                    <span class="text-[10px] text-gray-600">Current / Potential</span>
                </div>
                <div class="space-y-3">
                    @foreach([
                        ['Contact', 'batting_ratings_overall_contact', 'batting_ratings_talent_contact', false],
                        ['Avoid K\'s', 'batting_ratings_overall_strikeouts', 'batting_ratings_talent_strikeouts', true],
                        ['BABIP', 'batting_ratings_overall_babip', 'batting_ratings_talent_babip', true],
                        ['Gap Power', 'batting_ratings_overall_gap', 'batting_ratings_talent_gap', false],
                        ['Power', 'batting_ratings_overall_power', 'batting_ratings_talent_power', false],
                        ['Eye', 'batting_ratings_overall_eye', 'batting_ratings_talent_eye', false],
                    ] as [$label, $curKey, $potKey, $indent])
                    @php
                        $cur = $toScout($ratings->$curKey ?? 0);
                        $pot = $toScout($ratings->$potKey ?? 0);
                    @endphp
                    <div class="{{ $indent ? 'ml-4' : '' }}">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs {{ $indent ? 'text-gray-500 text-[11px]' : 'text-gray-400' }}">{{ $label }}</span>
                            <span class="text-xs font-mono">
                                <span class="font-bold" style="{{ $textColor($cur) }}">{{ $cur }}</span>
                                <span class="text-gray-600"> / </span>
                                <span class="font-bold" style="{{ $textColor($pot) }}">{{ $pot }}</span>
                            </span>
                        </div>
                        <div class="relative h-2.5 bg-gray-800/80 rounded overflow-hidden">
                            @if($pot > $cur)
                            <div class="h-full rounded absolute top-0 left-0" style="{{ $barBgFaded($pot) }}; width: {{ min($pot, 100) }}%"></div>
                            @endif
                            <div class="h-full rounded relative" style="{{ $barBg($cur) }}; width: {{ min($cur, 100) }}%"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Batting vs R / vs L --}}
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-4 {{ $isPitcherPlayer ? 'order-4' : 'order-2' }}">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Batting Splits</h3>
                @foreach([
                    ['vs Right', 'batting_ratings_vsr_'],
                    ['vs Left', 'batting_ratings_vsl_'],
                ] as [$splitLabel, $prefix])
                <div class="mb-5 last:mb-0">
                    <p class="text-[10px] text-gray-500 uppercase tracking-wider mb-2">{{ $splitLabel }}</p>
                    <div class="space-y-1.5">
                        @foreach(['contact'=>'Con','gap'=>'Gap','power'=>'Pow','eye'=>'Eye','strikeouts'=>'K\'s'] as $key => $lbl)
                        @php $v = $toScout($ratings->{$prefix.$key} ?? 0); @endphp
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] text-gray-500 w-8 shrink-0">{{ $lbl }}</span>
                            <div class="flex-1 h-2 bg-gray-800/80 rounded overflow-hidden">
                                <div class="h-full rounded" style="{{ $barBg($v) }}; width: {{ min($v, 100) }}%"></div>
                            </div>
                            <span class="text-xs font-bold font-mono w-7 text-right" style="{{ $textColor($v) }}">{{ $v }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Pitching Ratings --}}
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-4 {{ $isPitcherPlayer ? 'order-1' : 'order-3' }}">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Pitching Ratings</h3>
                    <span class="text-[10px] text-gray-600">Current / Potential</span>
                </div>
                <div class="space-y-3">
                    @foreach([
                        ['Stuff', 'pitching_ratings_overall_stuff', 'pitching_ratings_talent_stuff', false],
                        ['Movement', 'pitching_ratings_overall_movement', 'pitching_ratings_talent_movement', false],
                        ['HR Allowed', 'pitching_ratings_overall_hra', 'pitching_ratings_talent_hra', true],
                        ['PBABIP', 'pitching_ratings_overall_pbabip', 'pitching_ratings_talent_pbabip', true],
                        ['Control', 'pitching_ratings_overall_control', 'pitching_ratings_talent_control', false],
                    ] as [$label, $curKey, $potKey, $indent])
                    @php
                        $cur = $toScout($ratings->$curKey ?? 0);
                        $pot = $toScout($ratings->$potKey ?? 0);
                    @endphp
                    <div class="{{ $indent ? 'ml-4' : '' }}">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs {{ $indent ? 'text-gray-500 text-[11px]' : 'text-gray-400' }}">{{ $label }}</span>
                            <span class="text-xs font-mono">
                                <span class="font-bold" style="{{ $textColor($cur) }}">{{ $cur }}</span>
                                <span class="text-gray-600"> / </span>
                                <span class="font-bold" style="{{ $textColor($pot) }}">{{ $pot }}</span>
                            </span>
                        </div>
                        <div class="relative h-2.5 bg-gray-800/80 rounded overflow-hidden">
                            @if($pot > $cur)
                            <div class="h-full rounded absolute top-0 left-0" style="{{ $barBgFaded($pot) }}; width: {{ min($pot, 100) }}%"></div>
                            @endif
                            <div class="h-full rounded relative" style="{{ $barBg($cur) }}; width: {{ min($cur, 100) }}%"></div>
                        </div>
                    </div>
                    @endforeach

                    {{-- Pitch Arsenal --}}
                    @php
                        $pitchNames = [
                            'fastball'=>'Fastball','slider'=>'Slider','curveball'=>'Curveball',
                            'screwball'=>'Screwball','forkball'=>'Forkball','changeup'=>'Changeup',
                            'sinker'=>'Sinker','splitter'=>'Splitter','knuckleball'=>'Knuckleball',
                            'cutter'=>'Cutter','circlechange'=>'Circle Change','knucklecurve'=>'Knuckle Curve',
                        ];
                        $hasPitches = false;
                        foreach ($pitchNames as $k => $n) {
                            if (($ratings->{'pitching_ratings_pitches_'.$k} ?? 0) > 0 || ($ratings->{'pitching_ratings_pitches_talent_'.$k} ?? 0) > 0) {
                                $hasPitches = true; break;
                            }
                        }
                    @endphp
                    @if($hasPitches)
                    @php
                        // Collect pitches with ratings, sort by current desc, limit to 6
                        $activePitches = [];
                        foreach ($pitchNames as $k => $n) {
                            $c = $toScout($ratings->{'pitching_ratings_pitches_'.$k} ?? 0);
                            $p = $toScout($ratings->{'pitching_ratings_pitches_talent_'.$k} ?? 0);
                            if ($c > 0 || $p > 0) {
                                $activePitches[] = ['key' => $k, 'label' => $n, 'cur' => $c, 'pot' => $p];
                            }
                        }
                        usort($activePitches, fn($a, $b) => $b['cur'] <=> $a['cur']);
                        $activePitches = array_slice($activePitches, 0, 6);
                    @endphp
                    <div class="border-t border-gray-800 pt-3 mt-3">
                        <p class="text-[10px] text-gray-500 uppercase tracking-wider mb-2">Pitch Arsenal</p>
                        <div class="space-y-2">
                        @foreach($activePitches as $pitch)
                        @php
                            $cur = $pitch['cur'];
                            $pot = $pitch['pot'];
                            $label = $pitch['label'];
                        @endphp
                        <div>
                            <div class="flex items-center justify-between mb-0.5">
                                <span class="text-xs text-gray-400">{{ $label }}</span>
                                <span class="text-xs font-mono">
                                    <span class="font-bold" style="{{ $textColor($cur) }}">{{ $cur }}</span>
                                    <span class="text-gray-600"> / </span>
                                    <span class="font-bold" style="{{ $textColor($pot) }}">{{ $pot }}</span>
                                </span>
                            </div>
                            <div class="relative h-2 bg-gray-800/80 rounded overflow-hidden">
                                @if($pot > $cur)
                                <div class="h-full rounded absolute top-0 left-0" style="{{ $barBgFaded($pot) }}; width: {{ min($pot, 100) }}%"></div>
                                @endif
                                <div class="h-full rounded relative" style="{{ $barBg($cur) }}; width: {{ min($cur, 100) }}%"></div>
                            </div>
                        </div>
                        @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Other Pitching --}}
                    <div class="border-t border-gray-800 pt-3 mt-1">
                        <p class="text-[10px] text-gray-500 uppercase tracking-wider mb-2">Other Pitching</p>
                        <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 text-xs">
                            <span class="text-gray-400">Velocity</span>
                            <span class="text-white text-right font-mono">{{ $ratings->pitching_ratings_misc_velocity }}-{{ $ratings->pitching_ratings_misc_velocity_target }} (Max)</span>
                            <span class="text-gray-400">GB %</span>
                            <span class="text-white text-right font-mono">{{ $ratings->pitching_ratings_misc_ground_fly }}%</span>
                            <span class="text-gray-400">Arm Slot</span>
                            <span class="text-white text-right">{{ $armSlots[$ratings->pitching_ratings_misc_arm_slot] ?? 'Normal' }}</span>
                        </div>
                        @foreach([
                            ['Stamina', $toScout($ratings->pitching_ratings_misc_stamina)],
                            ['Hold Runners', $toScout($ratings->pitching_ratings_misc_hold)],
                        ] as [$barLabel, $barVal])
                        <div class="mt-2">
                            <div class="flex items-center justify-between mb-0.5">
                                <span class="text-xs text-gray-400">{{ $barLabel }}</span>
                                <span class="text-xs font-bold font-mono" style="{{ $textColor($barVal) }}">{{ $barVal }}</span>
                            </div>
                            <div class="h-2 bg-gray-800/80 rounded overflow-hidden">
                                <div class="h-full rounded" style="{{ $barBg($barVal) }}; width: {{ min($barVal, 100) }}%"></div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
            {{-- Pitching Splits --}}
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-4 {{ $isPitcherPlayer ? 'order-2' : 'order-4' }}">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Pitching Splits</h3>
                @foreach([
                    ['vs Right', 'pitching_ratings_vsr_'],
                    ['vs Left', 'pitching_ratings_vsl_'],
                ] as [$splitLabel, $prefix])
                <div class="mb-5 last:mb-0">
                    <p class="text-[10px] text-gray-500 uppercase tracking-wider mb-2">{{ $splitLabel }}</p>
                    <div class="space-y-1.5">
                        @foreach(['stuff'=>'Stuff','movement'=>'Mov','control'=>'Ctrl','hra'=>'HRA','pbabip'=>'PBABIP'] as $key => $lbl)
                        @php $v = $toScout($ratings->{$prefix.$key} ?? 0); @endphp
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] text-gray-500 w-10 shrink-0">{{ $lbl }}</span>
                            <div class="flex-1 h-2 bg-gray-800/80 rounded overflow-hidden">
                                <div class="h-full rounded" style="{{ $barBg($v) }}; width: {{ min($v, 100) }}%"></div>
                            </div>
                            <span class="text-xs font-bold font-mono w-7 text-right" style="{{ $textColor($v) }}">{{ $v }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>

        </div>

        {{-- Bottom row: 3 cards full width --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">

            {{-- Defensive Ratings --}}
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Defensive Ratings</h3>
                @php
                    $cArm = $toScout($ratings->fielding_ratings_catcher_arm);
                    $cAbi = $toScout($ratings->fielding_ratings_catcher_ability);
                    $cFrm = $toScout($ratings->fielding_ratings_catcher_framing);
                    $ifRng = $toScout($ratings->fielding_ratings_infield_range);
                    $ifArm = $toScout($ratings->fielding_ratings_infield_arm);
                    $ifErr = $toScout($ratings->fielding_ratings_infield_error);
                    $tdp   = $toScout($ratings->fielding_ratings_turn_doubleplay);
                    $ofRng = $toScout($ratings->fielding_ratings_outfield_range);
                    $ofArm = $toScout($ratings->fielding_ratings_outfield_arm);
                    $ofErr = $toScout($ratings->fielding_ratings_outfield_error);

                    $defRatings = [];
                    if ($cArm > 0 || $cAbi > 0) {
                        $defRatings[] = ['Catcher Ability', $cAbi];
                        $defRatings[] = ['Catcher Arm', $cArm];
                        $defRatings[] = ['Catcher Framing', $cFrm];
                    }
                    if ($ifRng > 0 || $ifArm > 0) {
                        $defRatings[] = ['Infield Range', $ifRng];
                        $defRatings[] = ['Infield Error', $ifErr];
                        $defRatings[] = ['Infield Arm', $ifArm];
                        $defRatings[] = ['Turn DP', $tdp];
                    }
                    if ($ofRng > 0 || $ofArm > 0) {
                        $defRatings[] = ['Outfield Range', $ofRng];
                        $defRatings[] = ['Outfield Error', $ofErr];
                        $defRatings[] = ['Outfield Arm', $ofArm];
                    }
                @endphp
                <div class="space-y-2">
                    @foreach($defRatings as [$label, $val])
                    <div>
                        <div class="flex items-center justify-between mb-0.5">
                            <span class="text-xs text-gray-400">{{ $label }}</span>
                            <span class="text-xs font-bold font-mono" style="{{ $textColor($val) }}">{{ $val }}</span>
                        </div>
                        <div class="h-2 bg-gray-800/80 rounded overflow-hidden">
                            <div class="h-full rounded" style="{{ $barBg($val) }}; width: {{ min($val, 100) }}%"></div>
                        </div>
                    </div>
                    @endforeach
                    @if(empty($defRatings))
                    <p class="text-gray-600 text-xs">No defensive ratings available.</p>
                    @endif
                </div>
            </div>

            {{-- Position Ratings --}}
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest">Position Ratings</h3>
                    <span class="text-[10px] text-gray-600">Current / Potential</span>
                </div>
                @php
                    $posNames = [1=>'Pitcher',2=>'Catcher',3=>'First Base',4=>'Second Base',5=>'Third Base',6=>'Shortstop',7=>'Left Field',8=>'Center Field',9=>'Right Field'];
                @endphp
                <div class="space-y-2">
                    @for($pi = 1; $pi <= 9; $pi++)
                    @php
                        $cur = $toScout($ratings->{'fielding_rating_pos'.$pi} ?? 0);
                        $pot = $toScout($ratings->{'fielding_rating_pos'.$pi.'_pot'} ?? 0);
                    @endphp
                    @if($cur > 0 || $pot > 0)
                    <div>
                        <div class="flex items-center justify-between mb-0.5">
                            <span class="text-xs text-gray-400">{{ $posNames[$pi] ?? $pi }}</span>
                            <span class="text-xs font-mono">
                                <span class="font-bold" style="{{ $textColor($cur) }}">{{ $cur }}</span>
                                <span class="text-gray-600"> / </span>
                                <span class="font-bold" style="{{ $textColor($pot) }}">{{ $pot }}</span>
                            </span>
                        </div>
                        <div class="relative h-2 bg-gray-800/80 rounded overflow-hidden">
                            @if($pot > $cur)
                            <div class="h-full rounded absolute top-0 left-0" style="{{ $barBgFaded($pot) }}; width: {{ min($pot, 100) }}%"></div>
                            @endif
                            <div class="h-full rounded relative" style="{{ $barBg($cur) }}; width: {{ min($cur, 100) }}%"></div>
                        </div>
                    </div>
                    @endif
                    @endfor
                </div>
            </div>

            {{-- Run / Bunt --}}
            <div class="bg-gray-900 border border-gray-800 rounded-xl p-4">
                <h3 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">Run / Bunt</h3>
                @php
                    $runRatings = [
                        ['Speed', $toScout($ratings->running_ratings_speed)],
                        ['Steal Tendency', $toScout($ratings->running_ratings_stealing_rate)],
                        ['Stealing', $toScout($ratings->running_ratings_stealing)],
                        ['Baserunning', $toScout($ratings->running_ratings_baserunning)],
                        ['Sac Bunt', $toScout($ratings->batting_ratings_misc_bunt)],
                        ['Bunt for Hit', $toScout($ratings->batting_ratings_misc_bunt_for_hit)],
                    ];
                @endphp
                <div class="space-y-2">
                    @foreach($runRatings as [$label, $val])
                    <div>
                        <div class="flex items-center justify-between mb-0.5">
                            <span class="text-xs text-gray-400">{{ $label }}</span>
                            <span class="text-xs font-bold font-mono" style="{{ $textColor($val) }}">{{ $val }}</span>
                        </div>
                        <div class="h-2 bg-gray-800/80 rounded overflow-hidden">
                            <div class="h-full rounded" style="{{ $barBg($val) }}; width: {{ min($val, 100) }}%"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

        </div>

        {{-- OSA Overall --}}
        @php
            $osaOvr = ($ratings->overall_rating ?? 0) / 2;
            $osaPot = ($ratings->talent_rating ?? 0) / 2;
            $renderStars = function($stars) {
                $html = '';
                $full = (int)floor($stars);
                $half = ($stars - $full) >= 0.5;
                $empty = 5 - $full - ($half ? 1 : 0);
                for ($i = 0; $i < $full; $i++) $html .= '<span class="text-yellow-400">&#9733;</span>';
                if ($half) $html .= '<span style="position:relative;display:inline-block;color:#374151">&#9733;<span style="position:absolute;left:0;top:0;overflow:hidden;width:50%;color:#facc15">&#9733;</span></span>';
                for ($i = 0; $i < $empty; $i++) $html .= '<span class="text-gray-700">&#9733;</span>';
                return $html;
            };
        @endphp
        <div class="mt-5 flex items-center gap-8">
            <div class="text-sm">
                <span class="text-gray-500">Overall</span>
                <span class="ml-2 text-lg">{!! $renderStars($osaOvr) !!}</span>
            </div>
            <div class="text-sm">
                <span class="text-gray-500">Potential</span>
                <span class="ml-2 text-lg">{!! $renderStars($osaPot) !!}</span>
            </div>
        </div>
    </section>
    @else
    <p class="text-gray-500">No ratings available for this player.</p>
    @endif

</div>
@endsection
