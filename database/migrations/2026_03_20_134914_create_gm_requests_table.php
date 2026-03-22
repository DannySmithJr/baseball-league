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
        Schema::dropIfExists('gm_teams');
        Schema::dropIfExists('gm_requests');

        if (Schema::hasTable('gms')) return;

        Schema::create('gms', function (Blueprint $table) {
            $table->increments('gm_id');
            $table->unsignedInteger('team_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->tinyInteger('status')->default(0); // 0=open, 1=pending, 2=filled
            $table->string('email_address', 191)->nullable();
            $table->string('email_pass', 191)->nullable();
            $table->timestamps();

            $table->unique('team_id');
            $table->index('user_id');
            $table->index('status');
        });

        // Seed all MLB teams as open
        $mlbTeams = \DB::table('teams')->where('level', 1)->where('allstar_team', 0)->pluck('team_id');
        foreach ($mlbTeams as $tid) {
            \DB::table('gms')->insert([
                'team_id' => $tid,
                'status' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gms');
    }
};
