@extends('layouts.public')
@section('title', $player->first_name . ' ' . $player->last_name . ' — Ratings')

@section('content')
@php $activeTab = 'player.ratings'; @endphp
@include('players._header')

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-10">

    @if($ratings)
    @php

        // Ratings are stored on 1-100 scale (OOTP setting), display as-is
        $toScout = fn($v) => (int)$v;

        // OOTP color scale (1-100)
        $ratingColor = fn($v) => match(true) {
            $v >= 80 => 'ootp-80',
            $v >= 70 => 'ootp-70',
            $v >= 60 => 'ootp-60',
            $v >= 50 => 'ootp-50',
            $v >= 40 => 'ootp-40',
            $v >= 30 => 'ootp-30',
            default  => 'ootp-20',
        };

        $armSlots = [1 => 'Normal', 2 => 'Sidearm', 3 => 'Over the Top', 4 => 'Submarine'];
    @endphp
    <section>
        <h2 class="text-xs font-bold text-red-500 uppercase tracking-widest mb-3">Ratings</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">

            {{-- Batting Ratings --}}
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="py-2 px-3 text-left">Batting</th>
                            <th class="py-2 px-3 text-center w-12">Con</th>
                            <th class="py-2 px-3 text-center w-12">Gap</th>
                            <th class="py-2 px-3 text-center w-12">Pow</th>
                            <th class="py-2 px-3 text-center w-12">Eye</th>
                            <th class="py-2 px-3 text-center w-12">K's</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40">
                        @foreach([
                            ['label' => 'Current', 'prefix' => 'batting_ratings_overall_'],
                            ['label' => 'vs R',    'prefix' => 'batting_ratings_vsr_'],
                            ['label' => 'vs L',    'prefix' => 'batting_ratings_vsl_'],
                            ['label' => 'Potential','prefix' => 'batting_ratings_talent_'],
                        ] as $row)
                        @php
                            $con = $toScout($ratings->{$row['prefix'].'contact'});
                            $gap = $toScout($ratings->{$row['prefix'].'gap'});
                            $pow = $toScout($ratings->{$row['prefix'].'power'});
                            $eye = $toScout($ratings->{$row['prefix'].'eye'});
                            $ks  = $toScout($ratings->{$row['prefix'].'strikeouts'});
                        @endphp
                        <tr>
                            <td class="py-1.5 px-3 text-gray-400 text-xs">{{ $row['label'] }}</td>
                            <td class="py-1.5 px-3 text-center {{ $ratingColor($con) }}">{{ $con }}</td>
                            <td class="py-1.5 px-3 text-center {{ $ratingColor($gap) }}">{{ $gap }}</td>
                            <td class="py-1.5 px-3 text-center {{ $ratingColor($pow) }}">{{ $pow }}</td>
                            <td class="py-1.5 px-3 text-center {{ $ratingColor($eye) }}">{{ $eye }}</td>
                            <td class="py-1.5 px-3 text-center {{ $ratingColor($ks) }}">{{ $ks }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pitching Ratings --}}
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="py-2 px-3 text-left">Pitching</th>
                            <th class="py-2 px-3 text-center w-14">Stuff</th>
                            <th class="py-2 px-3 text-center w-14">Move</th>
                            <th class="py-2 px-3 text-center w-14">Ctrl</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40">
                        @foreach([
                            ['label' => 'Current', 'prefix' => 'pitching_ratings_overall_'],
                            ['label' => 'vs R',    'prefix' => 'pitching_ratings_vsr_'],
                            ['label' => 'vs L',    'prefix' => 'pitching_ratings_vsl_'],
                            ['label' => 'Potential','prefix' => 'pitching_ratings_talent_'],
                        ] as $row)
                        @php
                            $stuff = $toScout($ratings->{$row['prefix'].'stuff'});
                            $move  = $toScout($ratings->{$row['prefix'].'movement'});
                            $ctrl  = $toScout($ratings->{$row['prefix'].'control'});
                        @endphp
                        <tr>
                            <td class="py-1.5 px-3 text-gray-400 text-xs">{{ $row['label'] }}</td>
                            <td class="py-1.5 px-3 text-center {{ $ratingColor($stuff) }}">{{ $stuff }}</td>
                            <td class="py-1.5 px-3 text-center {{ $ratingColor($move) }}">{{ $move }}</td>
                            <td class="py-1.5 px-3 text-center {{ $ratingColor($ctrl) }}">{{ $ctrl }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Fielding --}}
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="py-2 px-3 text-left">Fielding</th>
                            <th class="py-2 px-3 text-center w-10">C</th>
                            <th class="py-2 px-3 text-center w-10">IF</th>
                            <th class="py-2 px-3 text-center w-10">OF</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40">
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
                        @endphp
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Range</td><td class="py-1.5 px-3 text-center {{ $ratingColor($cAbi) }}">{{ $cAbi ?: '' }}</td><td class="py-1.5 px-3 text-center {{ $ratingColor($ifRng) }}">{{ $ifRng }}</td><td class="py-1.5 px-3 text-center {{ $ratingColor($ofRng) }}">{{ $ofRng }}</td></tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Error</td><td class="py-1.5 px-3 text-center"></td><td class="py-1.5 px-3 text-center {{ $ratingColor($ifErr) }}">{{ $ifErr }}</td><td class="py-1.5 px-3 text-center {{ $ratingColor($ofErr) }}">{{ $ofErr }}</td></tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Arm</td><td class="py-1.5 px-3 text-center {{ $ratingColor($cArm) }}">{{ $cArm ?: '' }}</td><td class="py-1.5 px-3 text-center {{ $ratingColor($ifArm) }}">{{ $ifArm }}</td><td class="py-1.5 px-3 text-center {{ $ratingColor($ofArm) }}">{{ $ofArm }}</td></tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Turn DP</td><td class="py-1.5 px-3 text-center"></td><td class="py-1.5 px-3 text-center {{ $ratingColor($tdp) }}">{{ $tdp }}</td><td class="py-1.5 px-3 text-center"></td></tr>
                        @if($cArm > 0)
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">C Block</td><td class="py-1.5 px-3 text-center {{ $ratingColor($cAbi) }}">{{ $cAbi }}</td><td class="py-1.5 px-3 text-center"></td><td class="py-1.5 px-3 text-center"></td></tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">C Frame</td><td class="py-1.5 px-3 text-center {{ $ratingColor($cFrm) }}">{{ $cFrm }}</td><td class="py-1.5 px-3 text-center"></td><td class="py-1.5 px-3 text-center"></td></tr>
                        @endif
                    </tbody>
                </table>
            </div>

            {{-- Position Ratings --}}
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="py-2 px-3 text-left">Position</th>
                            <th class="py-2 px-3 text-center w-20">Cur / Pot</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40">
                        @php
                            $posNames = [1=>'Pitcher',2=>'Catcher',3=>'First',4=>'Second',5=>'Third',6=>'Short',7=>'Left',8=>'Center',9=>'Right'];
                        @endphp
                        @for($pi = 1; $pi <= 9; $pi++)
                        @php
                            $cur = $toScout($ratings->{'fielding_rating_pos'.$pi} ?? 0);
                            $pot = $toScout($ratings->{'fielding_rating_pos'.$pi.'_pot'} ?? 0);
                        @endphp
                        @if($cur > 0 || $pot > 0)
                        <tr>
                            <td class="py-1.5 px-3 text-gray-400 text-xs">{{ $posNames[$pi] ?? $pi }}</td>
                            <td class="py-1.5 px-3 text-center">
                                <span class="{{ $ratingColor($cur) }}">{{ $cur }}</span>
                                <span class="text-gray-600"> / </span>
                                <span class="{{ $ratingColor($pot) }}">{{ $pot }}</span>
                            </td>
                        </tr>
                        @endif
                        @endfor
                    </tbody>
                </table>
            </div>

            {{-- Run/Bunt --}}
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="py-2 px-3 text-left" colspan="2">Run / Bunt</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40">
                        @php
                            $spd = $toScout($ratings->running_ratings_speed);
                            $stlAgg = $toScout($ratings->running_ratings_stealing_rate);
                            $stl = $toScout($ratings->running_ratings_stealing);
                            $run = $toScout($ratings->running_ratings_baserunning);
                            $bunt = $toScout($ratings->batting_ratings_misc_bunt);
                            $bfh  = $toScout($ratings->batting_ratings_misc_bunt_for_hit);
                        @endphp
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Speed</td><td class="py-1.5 px-3 text-right {{ $ratingColor($spd) }}">{{ $spd }}</td></tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Stl Aggr</td><td class="py-1.5 px-3 text-right {{ $ratingColor($stlAgg) }}">{{ $stlAgg }}</td></tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Stealing</td><td class="py-1.5 px-3 text-right {{ $ratingColor($stl) }}">{{ $stl }}</td></tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Running</td><td class="py-1.5 px-3 text-right {{ $ratingColor($run) }}">{{ $run }}</td></tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Sac Bunt</td><td class="py-1.5 px-3 text-right {{ $ratingColor($bunt) }}">{{ $bunt }}</td></tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Bunt for Hit</td><td class="py-1.5 px-3 text-right {{ $ratingColor($bfh) }}">{{ $bfh }}</td></tr>
                    </tbody>
                </table>
            </div>

            {{-- Pitch Ratings + Other Pitching --}}
            @if($isPitcher)
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-[11px] text-gray-500 uppercase tracking-wider border-b border-gray-800">
                            <th class="py-2 px-3 text-left">Pitch</th>
                            <th class="py-2 px-3 text-center w-12">Cur</th>
                            <th class="py-2 px-3 text-center w-12">Pot</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800/40">
                        @php
                            $pitchNames = [
                                'fastball'=>'Fastball','slider'=>'Slider','curveball'=>'Curveball',
                                'screwball'=>'Screwball','forkball'=>'Forkball','changeup'=>'Changeup',
                                'sinker'=>'Sinker','splitter'=>'Splitter','knuckleball'=>'Knuckleball',
                                'cutter'=>'Cutter','circlechange'=>'Circle Change','knucklecurve'=>'Knuckle Curve',
                            ];
                        @endphp
                        @foreach($pitchNames as $key => $label)
                        @php
                            $cur = $toScout($ratings->{'pitching_ratings_pitches_'.$key} ?? 0);
                            $pot = $toScout($ratings->{'pitching_ratings_pitches_talent_'.$key} ?? 0);
                        @endphp
                        @if($cur > 20 || $pot > 20)
                        <tr>
                            <td class="py-1.5 px-3 text-gray-400 text-xs">{{ $label }}</td>
                            <td class="py-1.5 px-3 text-center {{ $ratingColor($cur) }}">{{ $cur }}</td>
                            <td class="py-1.5 px-3 text-center {{ $ratingColor($pot) }}">{{ $pot }}</td>
                        </tr>
                        @endif
                        @endforeach
                        <tr class="border-t border-gray-700">
                            <td colspan="3" class="py-1 px-3"></td>
                        </tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Velocity</td><td colspan="2" class="py-1.5 px-3 text-right text-white">{{ $ratings->pitching_ratings_misc_velocity }}-{{ $ratings->pitching_ratings_misc_velocity_target }} (Max)</td></tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">GB %</td><td colspan="2" class="py-1.5 px-3 text-right text-white">{{ $ratings->pitching_ratings_misc_ground_fly }}%</td></tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Arm Slot</td><td colspan="2" class="py-1.5 px-3 text-right text-white">{{ $armSlots[$ratings->pitching_ratings_misc_arm_slot] ?? 'Normal' }}</td></tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Stamina</td><td colspan="2" class="py-1.5 px-3 text-right {{ $ratingColor($toScout($ratings->pitching_ratings_misc_stamina)) }}">{{ $toScout($ratings->pitching_ratings_misc_stamina) }}</td></tr>
                        <tr><td class="py-1.5 px-3 text-gray-400 text-xs">Hold</td><td colspan="2" class="py-1.5 px-3 text-right {{ $ratingColor($toScout($ratings->pitching_ratings_misc_hold)) }}">{{ $toScout($ratings->pitching_ratings_misc_hold) }}</td></tr>
                    </tbody>
                </table>
            </div>
            @endif

        </div>

        {{-- OSA Overall --}}
        <div class="mt-3 text-xs text-gray-500">
            OSA Ovr <span class="text-white font-bold">{{ $toScout($ratings->overall) }}</span>
            Pot <span class="text-white font-bold">{{ $toScout($ratings->talent) }}</span>
        </div>
    </section>
    @else
    <p class="text-gray-500">No ratings available for this player.</p>
    @endif

</div>
@endsection
