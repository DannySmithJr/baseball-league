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
        Schema::create('ccps', function (Blueprint $table) {
            $table->id('ccp_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedInteger('player_id');           // OOTP player_id
            $table->date('createdDate')->nullable();
            $table->string('createdMsg', 255)->nullable();
            $table->smallInteger('draftYear')->nullable();
            $table->tinyInteger('draftRound')->nullable();
            $table->smallInteger('draftPick')->nullable();
            $table->unsignedInteger('draftTeam')->nullable(); // OOTP team_id
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique('player_id'); // one CCP per OOTP player
            $table->unique('user_id');   // one player per user
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ccps');
    }
};
