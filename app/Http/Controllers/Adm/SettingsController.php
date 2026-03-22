<?php

namespace App\Http\Controllers\Adm;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function edit()
    {
        $settings = Setting::instance();
        return view('adm.settings', compact('settings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'league_name'              => 'required|string|max:255',
            'league_abbr'              => 'required|string|max:20',
            'registration_enabled'     => 'boolean',
            'gm_registration_enabled'  => 'boolean',
            'ccp_registration_enabled' => 'boolean',
            'twitch_url'               => 'nullable|url|max:255',
            'discord_url'              => 'nullable|url|max:255',
            'stats_url'                => 'nullable|url|max:255',
            'league_file_url'          => 'nullable|url|max:255',
            'kofi_url'                 => 'nullable|url|max:255',
            'x_url'                    => 'nullable|url|max:255',
            'youtube_url'              => 'nullable|url|max:255',
            'season'                   => 'required|integer|min:1',
            'next_sim'                 => 'nullable|date',
            'sim_length'               => 'required|integer|min:1|max:365',
        ]);

        $validated['registration_enabled']     = $request->boolean('registration_enabled');
        $validated['gm_registration_enabled']  = $request->boolean('gm_registration_enabled');
        $validated['ccp_registration_enabled'] = $request->boolean('ccp_registration_enabled');

        Setting::instance()->update($validated);

        return back()->with('success', 'Settings saved.');
    }

}
