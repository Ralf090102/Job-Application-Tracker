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
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->string('company');
            $table->string('role');
            // Plain string, not a native DB enum — see App\Enums\ApplicationStatus
            // for why. Values are validated at the application layer.
            $table->string('status')->default('applied');
            $table->unsignedInteger('salary_min')->nullable();
            $table->unsignedInteger('salary_max')->nullable();
            $table->string('posting_url')->nullable();
            $table->text('posting_text')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
