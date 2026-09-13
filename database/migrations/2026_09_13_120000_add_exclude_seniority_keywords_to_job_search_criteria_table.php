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
        Schema::table('job_search_criteria', function (Blueprint $table) {
            // JSON-encoded array of strings, same text+array-cast pattern as
            // position_keywords. Null means "use the deterministic filter's
            // built-in default list" (see AutoApplyIngestService), not "don't
            // filter" — unlike salary/work_mode, seniority exclusion has no
            // "off" state by design; the user is entry-level.
            $table->text('exclude_seniority_keywords')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_search_criteria', function (Blueprint $table) {
            $table->dropColumn('exclude_seniority_keywords');
        });
    }
};
