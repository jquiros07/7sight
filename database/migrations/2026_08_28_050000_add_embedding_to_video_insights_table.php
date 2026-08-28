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
        Schema::table('video_insights', function (Blueprint $table) {
            $table->json('embedding')->nullable()->after('text_detection');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('video_insights', function (Blueprint $table) {
            $table->dropColumn('embedding');
        });
    }
};
