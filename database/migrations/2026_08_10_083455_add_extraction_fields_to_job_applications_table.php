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
        Schema::table('job_applications', function (Blueprint $table) {
            $table->string('location')->nullable()->after('posting_url');
            // Plain string, not a native DB enum — same reasoning as `status`
            // in the original migration (see Roadmap.md Phase 2).
            $table->string('work_mode')->nullable()->after('location');
            // JSON-encoded array of strings (short human-readable concerns),
            // written by the Phase 5 extraction endpoint; also editable by
            // hand like any other field.
            $table->text('red_flags')->nullable()->after('notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropColumn(['location', 'work_mode', 'red_flags']);
        });
    }
};
