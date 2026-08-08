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
        Schema::table('analysis_jobs', function (Blueprint $table) {
            $table->unsignedTinyInteger('attempts')->default(0)->after('status');
            $table->string('external_job_id')->nullable()->after('attempts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('analysis_jobs', function (Blueprint $table) {
            $table->dropColumn(['attempts', 'external_job_id']);
        });
    }
};
