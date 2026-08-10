<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\JobApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_produces_a_valid_row(): void
    {
        $jobApplication = JobApplication::factory()->create();

        $this->assertDatabaseHas('job_applications', [
            'id' => $jobApplication->id,
            'company' => $jobApplication->company,
            'role' => $jobApplication->role,
        ]);

        $this->assertInstanceOf(ApplicationStatus::class, $jobApplication->status);
        $this->assertIsInt($jobApplication->salary_min);
        $this->assertIsInt($jobApplication->salary_max);
    }
}
