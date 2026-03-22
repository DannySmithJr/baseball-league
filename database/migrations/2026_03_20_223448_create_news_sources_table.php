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
        if (Schema::hasTable('news_sources')) return;

        // News sources: league-wide or per-team beat writers
        Schema::create('news_sources', function (Blueprint $table) {
            $table->increments('source_id');
            $table->string('name', 100);           // "Jeff Passan", "Ken Rosenthal"
            $table->string('outlet', 100);          // "ESPN", "The Athletic", "Baltimore Sun"
            $table->unsignedInteger('team_id')->nullable(); // null = league-wide, set = team beat writer
            $table->string('avatar', 255)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index('team_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_sources');
    }
};
