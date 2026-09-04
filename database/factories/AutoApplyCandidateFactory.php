<?php

namespace Database\Factories;

use App\Enums\AutoApplyCandidateStatus;
use App\Enums\WorkMode;
use App\Models\AutoApplyCandidate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutoApplyCandidate>
 */
class AutoApplyCandidateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $salaryMin = $this->faker->numberBetween(20000, 900000);
        $salaryMax = min(1000000, $salaryMin + $this->faker->numberBetween(10000, 200000));

        return [
            // unique() matters here: posting_url has a real DB-level unique
            // constraint (it's the dedup key), and tests create several rows
            // per run — plain url() risks a rare Faker collision.
            'posting_url' => $this->faker->unique()->url(),
            'company' => $this->faker->company(),
            'role' => $this->faker->jobTitle(),
            'salary_min' => $salaryMin,
            'salary_max' => $salaryMax,
            'hours_per_week' => $this->faker->numberBetween(10, 40),
            'location' => $this->faker->city(),
            'work_mode' => $this->faker->randomElement(WorkMode::cases()),
            'status' => $this->faker->randomElement(AutoApplyCandidateStatus::cases()),
            'posting_text' => $this->faker->optional()->paragraphs(3, true),
            'match_reasoning' => $this->faker->optional()->paragraph(),
            'tailored_resume_path' => $this->faker->optional()->filePath(),
            'resume_variant' => $this->faker->optional()->word(),
            'resume_variant_reason' => $this->faker->optional()->sentence(),
        ];
    }
}
