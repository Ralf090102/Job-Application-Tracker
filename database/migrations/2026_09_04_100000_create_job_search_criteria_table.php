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
        Schema::create('job_search_criteria', function (Blueprint $table) {
            $table->id();
            // JSON-encoded array of strings, same text+array-cast pattern as
            // job_applications.red_flags — "keyword(s)" implies more than one.
            $table->text('position_keywords')->nullable();
            $table->unsignedInteger('hours_min')->nullable();
            $table->unsignedInteger('hours_max')->nullable();
            // Optional/preferred, not a hard requirement — nullable means
            // "don't filter on salary," not "$0".
            $table->unsignedInteger('salary_min')->nullable();
            $table->unsignedInteger('salary_max')->nullable();
            // Plain string, not a native DB enum — see App\Enums\WorkMode.
            $table->string('work_mode')->nullable();
            $table->string('location')->nullable();
            // Free-text rubric fed to the LLM negative-flag pass (Phase 2).
            $table->text('avoid_if_rubric')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_search_criteria');
    }
};
