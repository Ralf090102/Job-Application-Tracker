<?php

namespace Tests\Feature;

use App\Enums\WorkMode;
use App\Models\JobSearchCriteria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobSearchCriteriaTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_produces_a_valid_row(): void
    {
        $criteria = JobSearchCriteria::factory()->create();

        $this->assertDatabaseHas('job_search_criteria', [
            'id' => $criteria->id,
            'location' => $criteria->location,
        ]);

        $this->assertIsArray($criteria->position_keywords);
        $this->assertInstanceOf(WorkMode::class, $criteria->work_mode);
        $this->assertIsInt($criteria->hours_min);
        $this->assertIsInt($criteria->hours_max);
    }

    public function test_row_is_readable_and_writable_after_creation(): void
    {
        // Exit criteria: "criteria row readable/writable via tinker" — this
        // exercises the same read/update path tinker would, at the model
        // layer, so the behavior is pinned by an automated test rather than
        // only verified manually.
        $criteria = JobSearchCriteria::factory()->create([
            'avoid_if_rubric' => 'Skip anything requiring on-call weekends.',
        ]);

        $criteria->update(['avoid_if_rubric' => 'Updated rubric text.']);

        $this->assertSame('Updated rubric text.', $criteria->fresh()->avoid_if_rubric);
    }
}
