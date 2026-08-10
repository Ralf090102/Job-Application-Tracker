<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Enums\WorkMode;
use App\Models\JobApplication;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobApplication>
 */
class JobApplicationFactory extends Factory
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
            'company' => $this->faker->company(),
            'role' => $this->faker->jobTitle(),
            'status' => $this->faker->randomElement(ApplicationStatus::cases()),
            'salary_min' => $salaryMin,
            'salary_max' => $salaryMax,
            'posting_url' => $this->faker->url(),
            'posting_text' => $this->faker->paragraphs(3, true),
            'location' => $this->faker->city(),
            'work_mode' => $this->faker->randomElement(WorkMode::cases()),
            'red_flags' => $this->faker->boolean(20) ? [$this->faker->sentence()] : [],
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
