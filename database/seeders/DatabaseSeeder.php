<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            QuestionnaireSeeder::class,
            AlumniSeeder::class,
            ResponseSeeder::class,
            UserSeeder::class,
            NewsSeeder::class,
            JobPostingSeeder::class,
            QuestionBankSeeder::class,
            CtaSeeder::class,
        ]);

        // Contoh user dummy (opsional)
        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        //     'role_id' => 1, // Super Admin
        // ]);
    }
}
