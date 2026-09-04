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
        Schema::create('auto_apply_candidates', function (Blueprint $table) {
            $table->id();
            // Dedup key: Phase 2's ingest endpoint checks this before storing
            // a new row from a JSearch result.
            $table->string('posting_url')->unique();
            $table->string('company');
            $table->string('role');
            $table->unsignedInteger('salary_min')->nullable();
            $table->unsignedInteger('salary_max')->nullable();
            // Singular value pulled from the posting itself, distinct from
            // job_search_criteria's hours_min/hours_max range preference.
            $table->unsignedInteger('hours_per_week')->nullable();
            $table->string('location')->nullable();
            // Plain string, not a native DB enum — see App\Enums\WorkMode.
            $table->string('work_mode')->nullable();
            // Plain string column + PHP enum cast, same pattern as v1's
            // ApplicationStatus — see App\Enums\AutoApplyCandidateStatus.
            $table->string('status')->default('discovered');
            $table->text('match_reasoning')->nullable();
            $table->string('tailored_resume_path')->nullable();
            $table->string('resume_variant')->nullable();
            $table->text('resume_variant_reason')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auto_apply_candidates');
    }
};
