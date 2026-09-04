<?php

namespace Tests\Feature;

use App\Enums\AutoApplyCandidateStatus;
use App\Models\AutoApplyCandidate;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoApplyCandidateTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_produces_a_valid_row(): void
    {
        $candidate = AutoApplyCandidate::factory()->create();

        $this->assertDatabaseHas('auto_apply_candidates', [
            'id' => $candidate->id,
            'posting_url' => $candidate->posting_url,
            'company' => $candidate->company,
            'role' => $candidate->role,
        ]);

        $this->assertInstanceOf(AutoApplyCandidateStatus::class, $candidate->status);
    }

    public function test_candidate_round_trips_through_every_status_value(): void
    {
        // Exit criteria: "a seeded fake candidate round-trips through every
        // status value."
        $candidate = AutoApplyCandidate::factory()->create([
            'status' => AutoApplyCandidateStatus::Discovered,
        ]);

        foreach (AutoApplyCandidateStatus::cases() as $status) {
            $candidate->update(['status' => $status]);

            $this->assertSame($status, $candidate->fresh()->status);
            $this->assertDatabaseHas('auto_apply_candidates', [
                'id' => $candidate->id,
                'status' => $status->value,
            ]);
        }
    }

    public function test_posting_url_is_unique(): void
    {
        $existing = AutoApplyCandidate::factory()->create();

        $this->expectException(QueryException::class);

        AutoApplyCandidate::factory()->create([
            'posting_url' => $existing->posting_url,
        ]);
    }
}
