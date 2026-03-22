@extends('layouts.public')
@section('title', $player->first_name . ' ' . $player->last_name . ' — Game Log')

@section('content')
@php $activeTab = 'player.gamelog'; @endphp
@include('players._header')

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-10">

    <div class="bg-gray-900 border border-gray-800 rounded-xl p-8 text-center">
        <p class="text-gray-400 text-lg">Game log coming soon</p>
    </div>

</div>
@endsection
