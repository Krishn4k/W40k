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
        // Reference data comes from `php artisan bsdata:import`; SpaceMarinesSeeder and PointSetSeeder
        // only provide a small offline data set (used by the tests).
        $this->call(GameFormatSeeder::class);

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            ['name' => 'Test User', 'password' => 'password', 'email_verified_at' => now()],
        )->forceFill(['is_admin' => true])->save();
    }
}
