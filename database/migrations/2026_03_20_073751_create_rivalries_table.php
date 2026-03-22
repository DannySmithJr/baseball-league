<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('rivalries')) return;

        Schema::create('rivalries', function (Blueprint $table) {
            $table->increments('rival_id');
            $table->unsignedInteger('team0_id');
            $table->unsignedInteger('team1_id');
            $table->unsignedInteger('gm0_id');
            $table->unsignedInteger('gm1_id');
            $table->boolean('approved')->default(false);
            $table->timestamps();

            $table->index(['team0_id', 'team1_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rivalries');
    }
};
