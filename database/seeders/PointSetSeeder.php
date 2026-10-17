<?php

namespace Database\Seeders;

use App\Models\PointSet;
use Illuminate\Database\Seeder;

class PointSetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PointSet::firstOrCreate(
            ['name' => 'Données de test 2026'],
            ['valid_from' => '2026-01-01', 'valid_to' => null],
        );
    }
}
