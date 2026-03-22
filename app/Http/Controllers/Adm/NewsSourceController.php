<?php

namespace App\Http\Controllers\Adm;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NewsSourceController extends Controller
{
    public function index()
    {
        $sources = DB::table('news_sources as ns')
            ->leftJoin('teams as t', 't.team_id', '=', 'ns.team_id')
            ->select('ns.*', 't.name as team_name', 't.nickname as team_nickname', 't.abbr as team_abbr')
            ->orderByRaw('ns.team_id IS NULL DESC, t.name')
            ->get();

        $teams = DB::table('teams')->where('level', 1)->where('allstar_team', 0)
            ->orderBy('name')->get(['team_id', 'name', 'nickname']);

        return view('adm.news-sources', compact('sources', 'teams'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name'    => 'required|string|max:100',
            'outlet'  => 'required|string|max:100',
            'team_id' => 'nullable|integer',
        ]);

        DB::table('news_sources')->insert([
            'name'       => $request->name,
            'outlet'     => $request->outlet,
            'team_id'    => $request->team_id ?: null,
            'active'     => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'News source added.');
    }

    public function destroy(int $id)
    {
        DB::table('news_sources')->where('source_id', $id)->delete();
        return back()->with('success', 'News source removed.');
    }
}
