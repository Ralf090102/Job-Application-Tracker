<?php

namespace Tests\Feature;

use App\Enums\AutoApplyCandidateStatus;
use App\Enums\WorkMode;
use App\Models\AutoApplyCandidate;
use App\Models\JobApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AutoApplyCandidateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.auto_apply.ingest_token' => 'test-token']);
    }

    private function tokenHeader(): array
    {
        return ['X-Ingest-Token' => 'test-token'];
    }

    public function test_listing_candidates_requires_a_valid_ingest_token(): void
    {
        $response = $this->getJson('/api/auto-apply/candidates');

        $response->assertStatus(401);
    }

    public function test_rejecting_a_candidate_requires_a_valid_ingest_token(): void
    {
        $candidate = AutoApplyCandidate::factory()->create();

        $response = $this->postJson("/api/auto-apply/candidates/{$candidate->id}/reject");

        $response->assertStatus(401);
    }

    public function test_submitting_a_candidate_requires_a_valid_ingest_token(): void
    {
        $candidate = AutoApplyCandidate::factory()->create();

        $response = $this->postJson("/api/auto-apply/candidates/{$candidate->id}/submit");

        $response->assertStatus(401);
    }

    public function test_cap_status_requires_a_valid_ingest_token(): void
    {
        $response = $this->getJson('/api/auto-apply/candidates/cap-status');

        $response->assertStatus(401);
    }

    public function test_cap_status_reports_remaining_capacity(): void
    {
        config(['services.auto_apply.daily_cap' => 3]);
        AutoApplyCandidate::factory()->create(['status' => AutoApplyCandidateStatus::Submitted]);

        $response = $this->getJson('/api/auto-apply/candidates/cap-status', $this->tokenHeader());

        $response->assertOk();
        $response->assertExactJson(['cap' => 3, 'submitted_today' => 1, 'remaining' => 2]);
    }

    public function test_cap_status_never_reports_negative_remaining(): void
    {
        config(['services.auto_apply.daily_cap' => 1]);
        AutoApplyCandidate::factory()->count(2)->create(['status' => AutoApplyCandidateStatus::Submitted]);

        $response = $this->getJson('/api/auto-apply/candidates/cap-status', $this->tokenHeader());

        $response->assertOk();
        $response->assertJsonPath('remaining', 0);
    }

    public function test_lists_only_candidates_matching_the_status_filter(): void
    {
        AutoApplyCandidate::factory()->create(['status' => AutoApplyCandidateStatus::ReadyForReview]);
        AutoApplyCandidate::factory()->create(['status' => AutoApplyCandidateStatus::Matched]);

        $response = $this->getJson('/api/auto-apply/candidates?status=ready_for_review', $this->tokenHeader());

        $response->assertOk()->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.status', 'ready_for_review');
    }

    public function test_lists_every_candidate_when_no_status_filter_is_given(): void
    {
        AutoApplyCandidate::factory()->create(['status' => AutoApplyCandidateStatus::ReadyForReview]);
        AutoApplyCandidate::factory()->create(['status' => AutoApplyCandidateStatus::Matched]);

        $response = $this->getJson('/api/auto-apply/candidates', $this->tokenHeader());

        $response->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_reject_transitions_a_ready_for_review_candidate_to_rejected(): void
    {
        $candidate = AutoApplyCandidate::factory()->create(['status' => AutoApplyCandidateStatus::ReadyForReview]);

        $response = $this->postJson("/api/auto-apply/candidates/{$candidate->id}/reject", [], $this->tokenHeader());

        $response->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->assertDatabaseHas('auto_apply_candidates', [
            'id' => $candidate->id,
            'status' => 'rejected',
        ]);
    }

    public function test_reject_refuses_a_candidate_that_is_not_ready_for_review(): void
    {
        $candidate = AutoApplyCandidate::factory()->create(['status' => AutoApplyCandidateStatus::Matched]);

        $response = $this->postJson("/api/auto-apply/candidates/{$candidate->id}/reject", [], $this->tokenHeader());

        $response->assertStatus(422);
        $this->assertDatabaseHas('auto_apply_candidates', [
            'id' => $candidate->id,
            'status' => 'matched',
        ]);
    }

    public function test_submit_creates_a_real_job_application_and_marks_the_candidate_submitted(): void
    {
        $candidate = AutoApplyCandidate::factory()->create([
            'status' => AutoApplyCandidateStatus::ReadyForReview,
            'company' => 'Acme Corp',
            'role' => 'Backend Developer',
            'salary_min' => 60000,
            'salary_max' => 90000,
            'posting_url' => 'https://indeed.com/viewjob?jk=abc123',
            'posting_text' => 'Backend Developer role at Acme Corp.',
            'location' => 'Makati',
            'work_mode' => WorkMode::Hybrid,
            'hours_per_week' => 40,
            'match_reasoning' => 'Matches salary floor and remote-friendly criteria.',
            'resume_variant' => 'SD',
            'resume_variant_reason' => 'Best fit for a full-stack role.',
            'tailored_resume_path' => 'C:/app/storage/app/private/auto-apply/1/resume.pdf',
        ]);

        $response = $this->postJson("/api/auto-apply/candidates/{$candidate->id}/submit", [], $this->tokenHeader());

        $response->assertCreated();
        $response->assertJsonPath('data.company', 'Acme Corp');
        $response->assertJsonPath('data.status', 'applied');
        $response->assertJsonPath('data.work_mode', 'hybrid');

        $this->assertDatabaseHas('job_applications', [
            'company' => 'Acme Corp',
            'role' => 'Backend Developer',
            'status' => 'applied',
            'posting_url' => 'https://indeed.com/viewjob?jk=abc123',
            'location' => 'Makati',
            'work_mode' => 'hybrid',
        ]);

        $this->assertDatabaseHas('auto_apply_candidates', [
            'id' => $candidate->id,
            'status' => 'submitted',
        ]);

        $jobApplication = JobApplication::first();
        $this->assertStringContainsString("candidate #{$candidate->id}", $jobApplication->notes);
        $this->assertStringContainsString('Resume variant: SD', $jobApplication->notes);
        $this->assertStringContainsString('Match reasoning: Matches salary floor', $jobApplication->notes);
    }

    public function test_submit_returns_422_for_a_candidate_not_ready_for_review(): void
    {
        $candidate = AutoApplyCandidate::factory()->create(['status' => AutoApplyCandidateStatus::Matched]);

        $response = $this->postJson("/api/auto-apply/candidates/{$candidate->id}/submit", [], $this->tokenHeader());

        $response->assertStatus(422);
        $this->assertDatabaseCount('job_applications', 0);
        $this->assertDatabaseHas('auto_apply_candidates', [
            'id' => $candidate->id,
            'status' => 'matched',
        ]);
    }

    public function test_submit_cannot_be_run_twice_on_the_same_candidate(): void
    {
        $candidate = AutoApplyCandidate::factory()->create(['status' => AutoApplyCandidateStatus::ReadyForReview]);

        $this->postJson("/api/auto-apply/candidates/{$candidate->id}/submit", [], $this->tokenHeader())
            ->assertCreated();

        $response = $this->postJson("/api/auto-apply/candidates/{$candidate->id}/submit", [], $this->tokenHeader());

        $response->assertStatus(422);
        $this->assertDatabaseCount('job_applications', 1);
    }

    public function test_submit_refuses_once_the_daily_cap_is_reached(): void
    {
        config(['services.auto_apply.daily_cap' => 1]);

        $first = AutoApplyCandidate::factory()->create(['status' => AutoApplyCandidateStatus::ReadyForReview]);
        $this->postJson("/api/auto-apply/candidates/{$first->id}/submit", [], $this->tokenHeader())
            ->assertCreated();

        $second = AutoApplyCandidate::factory()->create(['status' => AutoApplyCandidateStatus::ReadyForReview]);
        $response = $this->postJson("/api/auto-apply/candidates/{$second->id}/submit", [], $this->tokenHeader());

        $response->assertStatus(429);
        $response->assertJsonPath('message', 'Daily auto-apply cap reached (1/1 submitted today). Try again tomorrow.');
        $this->assertDatabaseCount('job_applications', 1);
        $this->assertDatabaseHas('auto_apply_candidates', [
            'id' => $second->id,
            'status' => 'ready_for_review',
        ]);
    }

    public function test_submit_succeeds_while_under_the_daily_cap(): void
    {
        config(['services.auto_apply.daily_cap' => 3]);

        foreach (range(1, 3) as $i) {
            $candidate = AutoApplyCandidate::factory()->create(['status' => AutoApplyCandidateStatus::ReadyForReview]);
            $this->postJson("/api/auto-apply/candidates/{$candidate->id}/submit", [], $this->tokenHeader())
                ->assertCreated();
        }

        $this->assertDatabaseCount('job_applications', 3);
    }

    public function test_the_daily_cap_only_counts_todays_submissions(): void
    {
        config(['services.auto_apply.daily_cap' => 1]);

        // A candidate submitted yesterday shouldn't count against today's
        // cap — backdate updated_at directly (Eloquent would otherwise
        // stamp it "now" on every save).
        $yesterday = AutoApplyCandidate::factory()->create(['status' => AutoApplyCandidateStatus::Submitted]);
        DB::table('auto_apply_candidates')
            ->where('id', $yesterday->id)
            ->update(['updated_at' => now()->subDay()]);

        $today = AutoApplyCandidate::factory()->create(['status' => AutoApplyCandidateStatus::ReadyForReview]);
        $response = $this->postJson("/api/auto-apply/candidates/{$today->id}/submit", [], $this->tokenHeader());

        $response->assertCreated();
    }
}
