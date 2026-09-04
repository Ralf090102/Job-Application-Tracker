<?php

namespace Database\Factories;

use App\Enums\WorkMode;
use App\Models\JobSearchCriteria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JobSearchCriteria>
 */
class JobSearchCriteriaFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $hoursMin = $this->faker->numberBetween(10, 20);
        $hoursMax = $hoursMin + $this->faker->numberBetween(5, 20);

        $salaryMin = $this->faker->numberBetween(20000, 900000);
        $salaryMax = min(1000000, $salaryMin + $this->faker->numberBetween(10000, 200000));

        return [
            'position_keywords' => $this->faker->words(3),
            'hours_min' => $hoursMin,
            'hours_max' => $hoursMax,
            'salary_min' => $salaryMin,
            'salary_max' => $salaryMax,
            'work_mode' => $this->faker->randomElement(WorkMode::cases()),
            'location' => $this->faker->city(),
            'avoid_if_rubric' => $this->faker->paragraph(),
        ];
    }
}
