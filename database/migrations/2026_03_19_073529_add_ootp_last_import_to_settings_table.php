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
        if (Schema::hasColumn('settings', 'ootp_last_import')) return;

        Schema::table('settings', function (Blueprint $table) {
            $table->timestamp('ootp_last_import')->nullable()->after('youtube_url');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('ootp_last_import');
        });
    }
};
