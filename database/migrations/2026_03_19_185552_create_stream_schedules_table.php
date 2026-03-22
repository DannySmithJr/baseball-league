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
        if (Schema::hasTable('stream_schedules')) return;

        Schema::create('stream_schedules', function (Blueprint $table) {
            $table->id();
            $table->tinyInteger('day_of_week'); // 0=Sun, 1=Mon, ..., 6=Sat
            $table->string('time_et');          // e.g. "18:00"
            $table->string('label');            // e.g. "Friday Night Game"
            $table->enum('stream_type', ['gotd', 'gotn']);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stream_schedules');
    }
};
