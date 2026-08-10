<?php

namespace Database\Seeders;

use App\Models\JobApplication;
use Illuminate\Database\Seeder;

class JobApplicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        JobApplication::factory(8)->create();
    }
}
