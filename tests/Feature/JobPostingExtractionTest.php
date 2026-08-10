<?php

namespace Tests\Feature;

use App\Contracts\JobPostingExtractor;
use App\Exceptions\JobPostingExtractionException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobPostingExtractionTest extends TestCase
{
    use RefreshDatabase;

    public function test_extracts_structured_fields_from_posting_text(): void
    {
        $this->mock(JobPostingExtractor::class, function ($mock) {
            $mock->shouldReceive('extract')
                ->once()
                ->with('Backend Developer at Acme. PHP/Laravel. Salary 50k-70k. Hybrid, Makati.')
                ->andReturn([
                    'company' => 'Acme',
                    'role' => 'Backend Developer',
                    'salary_min' => 50000,
                    'salary_max' => 70000,
                    'location' => 'Makati',
                    'work_mode' => 'hybrid',
                    'red_flags' => [],
                ]);
        });

        $response = $this->postJson('/api/job-applications/extract', [
            'posting_text' => 'Backend Developer at Acme. PHP/Laravel. Salary 50k-70k. Hybrid, Makati.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.company', 'Acme')
            ->assertJsonPath('data.work_mode', 'hybrid')
            ->assertJsonPath('data.red_flags', []);

        // Nothing persisted — this endpoint only extracts, it never saves.
        $this->assertDatabaseCount('job_applications', 0);
    }

    public function test_rejects_posting_text_that_is_too_short(): void
    {
        $response = $this->postJson('/api/job-applications/extract', [
            'posting_text' => 'too short',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['posting_text']);
    }

    public function test_surfaces_a_clear_error_when_extraction_fails(): void
    {
        $this->mock(JobPostingExtractor::class, function ($mock) {
            $mock->shouldReceive('extract')
                ->once()
                ->andThrow(new JobPostingExtractionException("Couldn't reach Ollama — is it running?"));
        });

        $response = $this->postJson('/api/job-applications/extract', [
            'posting_text' => str_repeat('Real job posting text goes here. ', 3),
        ]);

        $response->assertStatus(502)->assertJsonPath('message', "Couldn't reach Ollama — is it running?");
    }
}
