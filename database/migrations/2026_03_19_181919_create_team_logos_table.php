<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_logos', function (Blueprint $table) {
            $table->id('img_id');
            $table->unsignedInteger('team_id');       // OOTP teams.team_id
            $table->smallInteger('year_from');         // era start year
            $table->smallInteger('year_to');           // era end year (same as year_from for single-year eras)
            $table->string('logo_file', 120);          // filename in public/images/logos/
            $table->char('color0', 6)->nullable();     // primary color hex (no #)
            $table->char('color1', 6)->nullable();     // secondary
            $table->char('color2', 6)->nullable();
            $table->char('color3', 6)->nullable();
            $table->char('color4', 6)->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'year_from']);
            $table->index('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_logos');
    }
};
