<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analysis_jobs', function (Blueprint $table) {
            $table->timestamp('flagged_for_review_at')->nullable()->after('completed_at');
            $table->foreignId('flagged_by')->nullable()->after('flagged_for_review_at')->constrained('users')->nullOnDelete();
            $table->text('flagged_review_note')->nullable()->after('flagged_by');
        });
    }

    public function down(): void
    {
        Schema::table('analysis_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('flagged_by');
            $table->dropColumn(['flagged_for_review_at', 'flagged_review_note']);
        });
    }
};
