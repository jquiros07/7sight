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
        Schema::create('camera_recordings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('camera_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('recording');
            $table->unsignedSmallInteger('duration_minutes');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->boolean('cancel_requested')->default(false);
            $table->string('disk')->default('local');
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('process_pid')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('camera_recordings');
    }
};
