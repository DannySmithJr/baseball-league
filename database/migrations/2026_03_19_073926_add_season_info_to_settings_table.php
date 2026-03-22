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
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('season')->default(1)->after('ootp_last_import');
            $table->dateTime('next_sim')->nullable()->after('season');
            $table->unsignedTinyInteger('sim_length')->default(7)->after('next_sim');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['season', 'next_sim', 'sim_length']);
        });
    }
};
