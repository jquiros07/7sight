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
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->softDeletes();
            $table->unique(['slug', 'deleted_at']);
        });

        Schema::table('videos', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropUnique(['slug', 'deleted_at']);
            $table->dropSoftDeletes();
            $table->unique('slug');
        });
    }
};