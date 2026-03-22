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
            $table->boolean('playoff_division_winners')->default(true)->after('season');
            $table->tinyInteger('playoff_wildcards')->default(0)->after('playoff_division_winners');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['playoff_division_winners', 'playoff_wildcards']);
        });
    }
};
