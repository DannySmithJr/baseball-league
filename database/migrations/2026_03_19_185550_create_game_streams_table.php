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
        if (Schema::hasTable('game_streams')) return;

        Schema::create('game_streams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('game_id');
            $table->enum('stream_type', ['gotd', 'gotn']);
            $table->dateTime('scheduled_at');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_streams');
    }
};
