<?php

namespace App\Http\Controllers\Adm;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StreamScheduleController extends Controller
{
    public function index()
    {
        if (! auth()->user()->isAdmin()) {
            return redirect()->route('home');
        }

        $schedules = DB::table('stream_schedules')->orderBy('day_of_week')->orderBy('time_et')->get();

        return view('adm.streams', compact('schedules'));
    }

    public function store(Request $request)
    {
        if (! auth()->user()->isAdmin()) {
            return redirect()->route('home');
        }

        $validated = $request->validate([
            'day_of_week' => 'required|integer|min:0|max:6',
            'time_et'     => 'required|string|max:10',
            'label'       => 'required|string|max:255',
            'stream_type' => 'required|in:gotd,gotn',
        ]);

        DB::table('stream_schedules')->insert(array_merge($validated, [
            'active'     => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        return redirect()->route('adm.streams')->with('success', 'Schedule entry added.');
    }

    public function destroy($id)
    {
        if (! auth()->user()->isAdmin()) {
            return redirect()->route('home');
        }

        DB::table('stream_schedules')->where('id', $id)->delete();

        return redirect()->route('adm.streams')->with('success', 'Schedule entry deleted.');
    }
}
