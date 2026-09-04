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
        Schema::table('auto_apply_candidates', function (Blueprint $table) {
            // The raw JSearch job description. Phase 1 didn't include this —
            // discovered as a real gap while wiring Phase 2's LLM rubric
            // matcher (App\Services\OllamaJobPostingMatcher), which needs
            // the full posting text to evaluate against job_search_criteria's
            // avoid_if_rubric. Also feeds Phase 3's resume tailoring.
            $table->text('posting_text')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('auto_apply_candidates', function (Blueprint $table) {
            $table->dropColumn('posting_text');
        });
    }
};
