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
        Schema::create('analysis_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_job_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedInteger('occurrences')->default(1);
            $table->float('avg_confidence')->nullable();
            $table->float('min_confidence')->nullable();
            $table->float('max_confidence')->nullable();
            $table->decimal('first_seen_at', 10, 3)->nullable();
            $table->decimal('last_seen_at', 10, 3)->nullable();
            $table->json('data')->nullable();
            $table->timestamps();

            $table->unique(['analysis_job_id', 'label']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analysis_results');
    }
};
