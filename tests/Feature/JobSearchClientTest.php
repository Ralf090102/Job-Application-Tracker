<?php

namespace Tests\Feature;

use App\Exceptions\JobSearchException;
use App\Models\JobSearchCriteria;
use App\Services\JobSearchClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class JobSearchClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_throws_a_clear_error_and_sends_no_request_when_api_key_is_blank(): void
    {
        // Regression: a blank JSEARCH_API_KEY (it ships empty in
        // .env.example) used to send a request with an empty
        // x-rapidapi-key header, surfacing as a confusing wrapped RapidAPI
        // 401/403 instead of a clear config error (/bug-sweep 2026-09-04).
        config(['services.jsearch.key' => null]);
        Http::fake();

        $criteria = JobSearchCriteria::factory()->create(['position_keywords' => ['engineer']]);

        try {
            app(JobSearchClient::class)->search($criteria);
            $this->fail('Expected JobSearchException was not thrown.');
        } catch (JobSearchException $e) {
            $this->assertStringContainsString('JSEARCH_API_KEY', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_throws_a_clear_error_and_sends_no_request_when_host_is_blank(): void
    {
        config(['services.jsearch.host' => null]);
        Http::fake();

        $criteria = JobSearchCriteria::factory()->create(['position_keywords' => ['engineer']]);

        try {
            app(JobSearchClient::class)->search($criteria);
            $this->fail('Expected JobSearchException was not thrown.');
        } catch (JobSearchException $e) {
            $this->assertStringContainsString('JSEARCH_API_HOST', $e->getMessage());
        }

        Http::assertNothingSent();
    }
}
