<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TeamLogosSeeder extends Seeder
{
    // Map base logo name → OOTP team_id
    private const TEAM_IDS = [
        'atlanta_braves'        => 13,
        'baltimore_orioles'     => 1,
        'boston_red_sox'        => 2,
        'california_angels'     => 3,
        'chicago_white_sox'     => 4,
        'chicago_cubs'          => 14,
        'cincinnati_reds'       => 15,
        'cleveland_guardians'   => 5,
        'detroit_tigers'        => 6,
        'houston_astros'        => 16,
        'kansas_city_royals'    => 7,
        'los_angeles_dodgers'   => 17,
        'milwaukee_brewers'     => 8,
        'minnesota_twins'       => 9,
        'montreal_expos'        => 18,
        'new_york_mets'         => 19,
        'new_york_yankees'      => 10,
        'oakland_athletics'     => 11,
        'philadelphia_phillies' => 20,
        'pittsburgh_pirates'    => 21,
        'san_diego_padres'      => 22,
        'san_francisco_giants'  => 23,
        'st_louis_cardinals'    => 24,
        'washington_senators'   => 12,
    ];

    public function run(): void
    {
        $logosDir = 'C:/Users/danny/Documents/Out of the Park Developments/OOTP Baseball 27/logos';
        $rows = [];

        foreach (self::TEAM_IDS as $base => $teamId) {
            $files = glob("{$logosDir}/{$base}_*.oi");
            if (!$files) continue;

            foreach ($files as $path) {
                $filename = basename($path, '.oi');

                // Skip small_50 thumbnails
                if (str_ends_with($filename, '_small_50')) continue;

                // Strip the base_ prefix
                $rest = substr($filename, strlen($base) + 1);

                // Year part must start with 1 or 2 (real year, not a hex color code)
                if (!preg_match('/^([12]\d{3}(?:-[12]\d{3})?)(?:_|$)/', $rest, $ym)) continue;

                $eraPart = $ym[1]; // e.g. "1970-1988" or "1998"
                [$fromStr, $toStr] = str_contains($eraPart, '-')
                    ? explode('-', $eraPart, 2)
                    : [$eraPart, $eraPart];

                // Extract color codes (6-char hex) from the remainder
                preg_match_all('/([0-9A-Fa-f]{6})/', substr($rest, strlen($eraPart)), $cm);
                $colors = array_slice($cm[1] ?? [], 0, 5);

                $suffix   = ($fromStr === $toStr) ? $fromStr : "{$fromStr}-{$toStr}";
                $logoFile = "{$base}_{$suffix}.png";

                $rows[] = [
                    'team_id'   => $teamId,
                    'year_from' => (int)$fromStr,
                    'year_to'   => (int)$toStr,
                    'logo_file' => $logoFile,
                    'color0'    => strtoupper($colors[0] ?? '') ?: null,
                    'color1'    => strtoupper($colors[1] ?? '') ?: null,
                    'color2'    => strtoupper($colors[2] ?? '') ?: null,
                    'color3'    => strtoupper($colors[3] ?? '') ?: null,
                    'color4'    => strtoupper($colors[4] ?? '') ?: null,
                ];
            }
        }

        // Deduplicate by team_id + year_from (keep first match)
        $seen = [];
        $unique = [];
        foreach ($rows as $row) {
            $key = $row['team_id'] . '_' . $row['year_from'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $row;
            }
        }

        DB::table('team_logos')->truncate();
        foreach (array_chunk($unique, 50) as $chunk) {
            DB::table('team_logos')->insert($chunk);
        }

        $this->command->info('Seeded ' . count($unique) . ' team logo records.');
    }
}
