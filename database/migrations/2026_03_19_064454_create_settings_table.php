<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('league_name')->default('Sunday Morning League Baseball');
            $table->string('league_abbr')->default('SMLB');
            $table->boolean('registration_enabled')->default(true);
            $table->boolean('gm_registration_enabled')->default(false);
            $table->boolean('ccp_registration_enabled')->default(false);
            $table->string('twitch_url')->nullable();
            $table->string('discord_url')->nullable();
            $table->string('stats_url')->nullable();
            $table->string('league_file_url')->nullable();
            $table->string('kofi_url')->nullable();
            $table->string('x_url')->nullable();
            $table->string('youtube_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
