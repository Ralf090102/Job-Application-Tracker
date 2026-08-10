<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\JobApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobApplicationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_all_job_applications(): void
    {
        JobApplication::factory()->count(3)->create();

        $response = $this->getJson('/api/job-applications');

        $response->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_store_creates_a_job_application(): void
    {
        $payload = [
            'company' => 'Acme Corp',
            'role' => 'Backend Developer',
            'status' => ApplicationStatus::Saved->value,
            'salary_min' => 40000,
            'salary_max' => 60000,
            'posting_url' => 'https://example.com/jobs/123',
            'posting_text' => 'We are looking for...',
            'notes' => null,
        ];

        $response = $this->postJson('/api/job-applications', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.company', 'Acme Corp')
            ->assertJsonPath('data.status', 'saved');

        $this->assertDatabaseHas('job_applications', [
            'company' => 'Acme Corp',
            'role' => 'Backend Developer',
        ]);
    }

    public function test_store_rejects_an_invalid_payload(): void
    {
        $response = $this->postJson('/api/job-applications', [
            'company' => '',
            'role' => 'Backend Developer',
            'status' => 'not-a-real-status',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['company', 'status']);
    }

    public function test_show_returns_a_single_job_application(): void
    {
        $jobApplication = JobApplication::factory()->create();

        $response = $this->getJson("/api/job-applications/{$jobApplication->id}");

        $response->assertOk()->assertJsonPath('data.id', $jobApplication->id);
    }

    public function test_update_modifies_a_job_application(): void
    {
        $jobApplication = JobApplication::factory()->create([
            'status' => ApplicationStatus::Saved,
        ]);

        $response = $this->patchJson("/api/job-applications/{$jobApplication->id}", [
            'status' => ApplicationStatus::Applied->value,
        ]);

        $response->assertOk()->assertJsonPath('data.status', 'applied');

        $this->assertDatabaseHas('job_applications', [
            'id' => $jobApplication->id,
            'status' => 'applied',
        ]);
    }

    public function test_destroy_deletes_a_job_application(): void
    {
        $jobApplication = JobApplication::factory()->create();

        $response = $this->deleteJson("/api/job-applications/{$jobApplication->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('job_applications', [
            'id' => $jobApplication->id,
        ]);
    }
}
