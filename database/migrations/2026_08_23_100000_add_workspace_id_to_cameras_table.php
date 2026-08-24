<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Cameras predate the workspace relation added here. There's no
     * production deployment yet, so existing rows are wiped rather than
     * backfilled into a guessed workspace.
     */
    public function up(): void
    {
        DB::table('cameras')->delete();

        Schema::table('cameras', function (Blueprint $table) {
            $table->foreignId('workspace_id')->after('id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cameras', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workspace_id');
        });
    }
};
