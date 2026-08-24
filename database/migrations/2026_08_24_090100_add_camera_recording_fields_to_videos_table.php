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
        Schema::table('videos', function (Blueprint $table) {
            $table->foreignId('camera_recording_id')->nullable()->after('workspace_id')->constrained('camera_recordings')->nullOnDelete();
            $table->unsignedInteger('clip_start_seconds')->nullable()->after('camera_recording_id');
            $table->unsignedInteger('clip_end_seconds')->nullable()->after('clip_start_seconds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('camera_recording_id');
            $table->dropColumn(['clip_start_seconds', 'clip_end_seconds']);
        });
    }
};
