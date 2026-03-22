<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('league_player_awards', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('player_id');          // OOTP player_id
            $table->string('award_slug', 50);              // e.g. 'ccp_of_year'
            $table->string('award_name', 100);             // display name
            $table->smallInteger('year');
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->index('player_id');
            $table->unique(['player_id', 'award_slug', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('league_player_awards');
    }
};
