<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // game_logs — biggest offender: 3 queries × ~5.7s each with no index
        $this->idx('game_logs', 'idx_game_logs_game_id',   ['game_id']);
        $this->idx('game_logs', 'idx_game_logs_game_type', ['game_id', 'type']);

        $this->idx('players_at_bat_batting_stats',  'idx_atbat_game_id',          ['game_id']);
        $this->idx('players_game_batting',          'idx_pgb_game_id',            ['game_id']);
        $this->idx('players_game_pitching_stats',   'idx_pgp_game_id',            ['game_id']);
        $this->idx('players_career_fielding_stats', 'idx_pcf_player_year_split',  ['player_id', 'year', 'split_id']);
        $this->idx('players_career_batting_stats',  'idx_pcbs_player_year_split', ['player_id', 'year', 'split_id']);

        // Preview / Schedule page joins
        $this->idx('games',                        'idx_games_played_date',      ['played', 'date']);
        $this->idx('games',                        'idx_games_played_type',      ['played', 'game_type']);
        $this->idx('players_game_batting',          'idx_pgb_level_team',        ['level_id', 'team_id']);
        $this->idx('players_game_batting',          'idx_pgb_level_player',      ['level_id', 'player_id']);
        $this->idx('players_game_pitching_stats',   'idx_pgp_level_team',        ['level_id', 'team_id']);
        $this->idx('players_game_pitching_stats',   'idx_pgp_level_gs',          ['level_id', 'gs']);

        // Team page — single-team lookups + player joins
        $this->idx('players',                        'idx_players_pk',            ['player_id']);
        $this->idx('players_game_batting',           'idx_pgb_team',              ['team_id']);
        $this->idx('players_game_pitching_stats',    'idx_pgp_team',              ['team_id']);
        $this->idx('team_roster',                    'idx_tr_team_list',          ['team_id', 'list_id']);

        // Season stats (career tables)
        $this->idx('players_career_batting_stats',   'idx_pcbs_split_year_ab',    ['split_id', 'year', 'ab']);
        $this->idx('team_roster',                    'idx_tr_player_team',        ['player_id', 'team_id']);
        $this->idx('players_streak',                 'idx_ps_streak_ended',       ['streak_id', 'has_ended', 'value']);

        // Player page — career stats lookups
        $this->idx('players_career_batting_stats',   'idx_pcbs_player_split',     ['player_id', 'split_id']);
        $this->idx('players_career_pitching_stats',  'idx_pcps_player_split',     ['player_id', 'split_id']);

        // Game page
        $this->idx('game_logs',                      'idx_gl_game_type',          ['game_id', 'type']);
        $this->idx('players_at_bat_batting_stats',   'idx_atbat_game_team',       ['game_id', 'team_id']);
        $this->idx('players_career_fielding_stats',  'idx_pcf_pid_year_split',    ['player_id', 'year', 'split_id']);
    }

    public function down(): void
    {
        $map = [
            'game_logs'                     => ['idx_game_logs_game_id', 'idx_game_logs_game_type'],
            'players_at_bat_batting_stats'  => ['idx_atbat_game_id'],
            'players_game_batting'          => ['idx_pgb_game_id'],
            'players_game_pitching_stats'   => ['idx_pgp_game_id'],
            'players_career_fielding_stats' => ['idx_pcf_player_year_split'],
            'players_career_batting_stats'  => ['idx_pcbs_player_year_split'],
            'games'                         => ['idx_games_played_date', 'idx_games_played_type'],
            'players_game_batting'          => ['idx_pgb_level_team', 'idx_pgb_level_player'],
            'players_game_pitching_stats'   => ['idx_pgp_level_team', 'idx_pgp_level_gs'],
        ];
        foreach ($map as $table => $names) {
            foreach ($names as $name) {
                try { Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name)); } catch (\Exception) {}
            }
        }
    }

    private function idx(string $table, string $name, array $cols): void
    {
        try {
            Schema::table($table, fn (Blueprint $t) => $t->index($cols, $name));
        } catch (\Exception) {
            // already exists — skip
        }
    }
};
