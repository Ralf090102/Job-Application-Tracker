<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // Password is the factory default — the literal string "password",
        // bcrypt-hashed. Single-user personal tool, not multi-tenant (see
        // Roadmap.md Phase 6) — one seeded account is genuinely enough.
        User::factory()->create([
            'name' => 'Ralf',
            'email' => 'ralf.hernandez090102@gmail.com',
        ]);

        $this->call(JobApplicationSeeder::class);
    }
}
