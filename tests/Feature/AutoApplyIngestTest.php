<?php

namespace Tests\Feature;

use App\Contracts\JobPostingMatcher;
use App\Enums\WorkMode;
use App\Jobs\TailorResumeCandidate;
use App\Models\AutoApplyCandidate;
use App\Models\JobSearchCriteria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AutoApplyIngestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.auto_apply.ingest_token' => 'test-token']);

        // These tests are about ingest/matching logic, not tailoring — fake
        // the queue so TailorResumeCandidate is recorded, not actually run
        // synchronously (QUEUE_CONNECTION=sync in tests). Without this,
        // every ingest test pays real job-dispatch overhead for a step none
        // of them assert on, which measurably slowed the whole suite down.
        Queue::fake();
    }

    private function postIngest(array $postings): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(
            '/api/auto-apply/ingest',
            ['postings' => $postings],
            ['X-Ingest-Token' => 'test-token'],
        );
    }

    private function rawPosting(array $overrides = []): array
    {
        return array_merge([
            'job_apply_link' => 'https://indeed.com/viewjob?jk=abc123',
            'employer_name' => 'Acme Corp',
            'job_title' => 'Backend Developer',
            'job_min_salary' => 50000,
            'job_max_salary' => 70000,
            'job_city' => 'Makati',
            'job_state' => 'NCR',
            'job_is_remote' => false,
            'job_description' => 'Backend Developer role at Acme Corp, PHP/Laravel.',
        ], $overrides);
    }

    public function test_rejects_requests_without_a_valid_ingest_token(): void
    {
        $response = $this->postJson('/api/auto-apply/ingest', ['postings' => []]);

        $response->assertStatus(401);
    }

    public function test_rejects_requests_with_the_wrong_ingest_token(): void
    {
        $response = $this->postJson(
            '/api/auto-apply/ingest',
            ['postings' => []],
            ['X-Ingest-Token' => 'wrong-token'],
        );

        $response->assertStatus(401);
    }

    public function test_creates_a_matched_candidate_when_no_criteria_row_exists(): void
    {
        $this->mock(JobPostingMatcher::class, function ($mock) {
            $mock->shouldNotReceive('evaluate');
        });

        $response = $this->postIngest([$this->rawPosting()]);

        $response->assertOk()->assertJsonPath('matched', 1);
        $this->assertDatabaseCount('auto_apply_candidates', 1);
        $this->assertDatabaseHas('auto_apply_candidates', [
            'posting_url' => 'https://indeed.com/viewjob?jk=abc123',
            'company' => 'Acme Corp',
            'role' => 'Backend Developer',
            'status' => 'matched',
        ]);
    }

    public function test_skips_a_posting_whose_url_already_exists(): void
    {
        AutoApplyCandidate::factory()->create([
            'posting_url' => 'https://indeed.com/viewjob?jk=abc123',
        ]);

        $response = $this->postIngest([$this->rawPosting()]);

        $response->assertOk()
            ->assertJsonPath('matched', 0)
            ->assertJsonPath('skipped_duplicate', 1);
        $this->assertDatabaseCount('auto_apply_candidates', 1);
    }

    public function test_skips_a_posting_below_the_criteria_salary_floor(): void
    {
        JobSearchCriteria::factory()->create(['salary_min' => 80000, 'salary_max' => null]);

        $response = $this->postIngest([$this->rawPosting(['job_max_salary' => 60000])]);

        $response->assertOk()
            ->assertJsonPath('matched', 0)
            ->assertJsonPath('skipped_non_match', 1);
        $this->assertDatabaseCount('auto_apply_candidates', 0);
    }

    public function test_skips_a_posting_with_the_wrong_work_mode(): void
    {
        JobSearchCriteria::factory()->create(['work_mode' => WorkMode::Remote]);

        $response = $this->postIngest([$this->rawPosting(['job_is_remote' => false])]);

        $response->assertOk()
            ->assertJsonPath('matched', 0)
            ->assertJsonPath('skipped_non_match', 1);
        $this->assertDatabaseCount('auto_apply_candidates', 0);
    }

    public function test_a_posting_worded_to_trip_the_negative_flag_rubric_is_not_stored(): void
    {
        // salary/hours/work_mode left null so the deterministic pass always
        // lets this posting through — this test is only about the LLM half.
        $criteria = JobSearchCriteria::factory()->create([
            'salary_min' => null,
            'salary_max' => null,
            'hours_min' => null,
            'hours_max' => null,
            'work_mode' => null,
            'avoid_if_rubric' => 'Avoid postings that require unpaid overtime.',
        ]);

        $this->mock(JobPostingMatcher::class, function ($mock) use ($criteria) {
            $mock->shouldReceive('evaluate')
                ->once()
                ->with(\Mockery::type('string'), $criteria->avoid_if_rubric)
                ->andReturn(['matches' => false, 'reasoning' => 'Posting requires unpaid overtime.']);
        });

        $response = $this->postIngest([$this->rawPosting([
            'job_description' => 'Backend Developer. Must be willing to work unpaid overtime.',
        ])]);

        $response->assertOk()
            ->assertJsonPath('matched', 0)
            ->assertJsonPath('skipped_non_match', 1);
        $this->assertDatabaseCount('auto_apply_candidates', 0);
    }

    public function test_stores_the_matchers_reasoning_when_a_posting_passes(): void
    {
        // salary/hours/work_mode left null so the deterministic pass always
        // lets this posting through — this test is only about the LLM half.
        $criteria = JobSearchCriteria::factory()->create([
            'salary_min' => null,
            'salary_max' => null,
            'hours_min' => null,
            'hours_max' => null,
            'work_mode' => null,
            'avoid_if_rubric' => 'Avoid postings that require unpaid overtime.',
        ]);

        $this->mock(JobPostingMatcher::class, function ($mock) use ($criteria) {
            $mock->shouldReceive('evaluate')
                ->once()
                ->with(\Mockery::type('string'), $criteria->avoid_if_rubric)
                ->andReturn(['matches' => true, 'reasoning' => 'No mention of unpaid overtime.']);
        });

        $response = $this->postIngest([$this->rawPosting()]);

        $response->assertOk()->assertJsonPath('matched', 1);
        $this->assertDatabaseHas('auto_apply_candidates', [
            'posting_url' => 'https://indeed.com/viewjob?jk=abc123',
            'match_reasoning' => 'No mention of unpaid overtime.',
        ]);
    }

    public function test_a_malformed_posting_missing_required_fields_is_skipped(): void
    {
        $response = $this->postIngest([$this->rawPosting(['job_apply_link' => null])]);

        $response->assertOk()
            ->assertJsonPath('matched', 0)
            ->assertJsonPath('skipped_non_match', 1);
        $this->assertDatabaseCount('auto_apply_candidates', 0);
    }

    public function test_falls_back_to_job_google_link_when_job_apply_link_is_an_empty_string(): void
    {
        // Regression: the ?? fallback only triggered on null, not on an
        // empty string, silently discarding postings that had a usable
        // fallback URL (/bug-sweep 2026-09-04).
        $response = $this->postIngest([$this->rawPosting([
            'job_apply_link' => '',
            'job_google_link' => 'https://jobs.google.com/some-posting',
        ])]);

        $response->assertOk()->assertJsonPath('matched', 1);
        $this->assertDatabaseHas('auto_apply_candidates', [
            'posting_url' => 'https://jobs.google.com/some-posting',
        ]);
    }

    public function test_hybrid_criteria_work_mode_does_not_reject_every_posting(): void
    {
        // Regression: JSearch's job_is_remote can only ever resolve a
        // posting's work_mode to Remote or Onsite, so a criteria row set to
        // Hybrid used to reject every posting, forever, silently
        // (/bug-sweep 2026-09-04). salary/hours left null so the factory's
        // random bounds can't incidentally reject this posting for an
        // unrelated reason.
        JobSearchCriteria::factory()->create([
            'work_mode' => WorkMode::Hybrid,
            'salary_min' => null,
            'salary_max' => null,
            'hours_min' => null,
            'hours_max' => null,
        ]);

        $response = $this->postIngest([$this->rawPosting(['job_is_remote' => false])]);

        $response->assertOk()->assertJsonPath('matched', 1);
    }

    public function test_dispatches_a_tailoring_job_for_each_matched_candidate(): void
    {
        // Regression: tailoring used to run synchronously inline, which
        // could block the request for 20-40+ minutes on a real batch
        // (/bug-sweep 2026-09-04) — it must now be queued instead.
        // (Queue::fake() already active — see setUp().)
        $response = $this->postIngest([$this->rawPosting()]);

        $response->assertOk()->assertJsonPath('matched', 1);

        Queue::assertPushed(TailorResumeCandidate::class, function ($job) {
            return $job->candidate->posting_url === 'https://indeed.com/viewjob?jk=abc123';
        });
    }
}
