<?php

namespace Tests\Feature;

use App\Exceptions\JobSearchException;
use App\Models\JobSearchCriteria;
use App\Services\JobSearchClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoApplySearchCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_fails_cleanly_when_no_criteria_row_exists(): void
    {
        $this->artisan('auto-apply:search')
            ->expectsOutputToContain('No job_search_criteria row exists yet')
            ->assertExitCode(1);
    }

    public function test_fails_cleanly_when_the_jsearch_call_throws(): void
    {
        // Regression: this call previously had no try/catch, so a JSearch
        // outage/rate-limit dumped an unhandled stack trace instead of a
        // clean CLI error like every other failure path in this phase
        // (/bug-sweep 2026-09-04).
        JobSearchCriteria::factory()->create();

        $this->mock(JobSearchClient::class, function ($mock) {
            $mock->shouldReceive('search')->once()->andThrow(
                new JobSearchException('JSearch is down.')
            );
        });

        $this->artisan('auto-apply:search')
            ->expectsOutputToContain('JSearch call failed: JSearch is down.')
            ->assertExitCode(1);
    }
}
