<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'league_name', 'league_abbr',
        'registration_enabled', 'gm_registration_enabled', 'ccp_registration_enabled',
        'twitch_url', 'discord_url', 'stats_url', 'league_file_url',
        'kofi_url', 'x_url', 'youtube_url',
        'ootp_last_import',
        'season', 'next_sim', 'sim_length',
    ];

    protected $casts = [
        'registration_enabled'     => 'boolean',
        'gm_registration_enabled'  => 'boolean',
        'ccp_registration_enabled' => 'boolean',
    ];

    public static function instance(): self
    {
        return self::firstOrCreate(['id' => 1]);
    }
}
