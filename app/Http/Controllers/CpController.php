<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CpController extends Controller
{
    public function index()
    {
        $user     = auth()->user();
        $settings = DB::table('settings')->first();
        $isGm     = $user->isGm();
        $hasCcp   = $user->hasCcp();
        $gmRecord = $isGm ? $user->gmRecord() : null;
        $pendingGm = $user->hasPendingGm();
        $ccp      = $hasCcp ? $user->ccp() : null;

        // GM slots grouped by status
        $gmSlots = DB::table('gms as gm')
            ->join('teams as t', 't.team_id', '=', 'gm.team_id')
            ->leftJoin('users as u', 'u.id', '=', 'gm.user_id')
            ->leftJoin('team_record as tr', 'tr.team_id', '=', 'gm.team_id')
            ->where('t.level', 1)->where('t.allstar_team', 0)
            ->select('gm.*', 't.name as team_name', 't.nickname as team_nickname', 't.abbr as team_abbr',
                     'u.name as user_name', 'tr.w', 'tr.l')
            ->orderBy('gm.status')->orderBy('t.name')
            ->get();

        $pendingTeams   = $gmSlots->where('status', 1);
        $availableTeams = $gmSlots->where('status', 0);
        $filledTeams    = $gmSlots->where('status', 2);

        // Farm rankings for GM signup page
        $ootp = app(\App\Services\OotpService::class);
        $farmRankings = collect($ootp->farmRankings())->keyBy('parent_team_id');

        // CCP player info
        $ccpPlayer = null;
        if ($ccp && $ccp->player_id) {
            $ccpPlayer = DB::table('players as p')
                ->leftJoin('team_roster as roster', function ($j) {
                    $j->on('roster.player_id', '=', 'p.player_id')->where('roster.list_id', 1);
                })
                ->leftJoin('teams as t', 't.team_id', '=', 'roster.team_id')
                ->where('p.player_id', $ccp->player_id)
                ->first(['p.player_id', 'p.first_name', 'p.last_name', 'p.position', 'p.age',
                          't.name as team_name', 't.abbr as team_abbr']);
        }

        return view('cp.index', compact(
            'user', 'settings', 'isGm', 'hasCcp', 'gmRecord', 'pendingGm',
            'ccp', 'ccpPlayer',
            'pendingTeams', 'availableTeams', 'filledTeams', 'farmRankings',
        ));
    }

    public function requestGm(Request $request)
    {
        $request->validate(['team_id' => 'required|integer']);
        $user = auth()->user();
        $teamId = (int) $request->team_id;

        // Check not already a GM or pending
        if ($user->isGm() || $user->hasPendingGm()) {
            return back()->with('error', 'You already have a GM assignment or pending request.');
        }

        // Check slot is open
        $slot = DB::table('gms')->where('team_id', $teamId)->where('status', 0)->first();
        if (!$slot) {
            return back()->with('error', 'This team is not available.');
        }

        DB::table('gms')->where('gm_id', $slot->gm_id)->update([
            'user_id' => $user->id,
            'status'  => 1,  // pending
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Your GM request has been submitted for approval.');
    }

    public function updateAccount(Request $request)
    {
        $request->validate([
            'twitch_username' => 'nullable|string|max:50',
        ]);

        $user = auth()->user();
        $user->twitch_username = $request->twitch_username;
        $user->save();

        return back()->with('success', 'Account updated.');
    }
}
