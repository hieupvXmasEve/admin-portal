<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // Create Units
        $units = [
            ['code' => 'CS101', 'name' => 'Introduction to Computer Science', 'credit_points' => 12.5],
            ['code' => 'CS102', 'name' => 'Programming Fundamentals', 'credit_points' => 12.5],
            ['code' => 'CS201', 'name' => 'Data Structures and Algorithms', 'credit_points' => 12.5],
            ['code' => 'CS301', 'name' => 'Database Systems', 'credit_points' => 12.5],
            ['code' => 'CS401', 'name' => 'Software Engineering', 'credit_points' => 12.5],

            ['code' => 'BUS101', 'name' => 'Introduction to Business', 'credit_points' => 12.5],
            ['code' => 'BUS201', 'name' => 'Marketing Principles', 'credit_points' => 12.5],
            ['code' => 'BUS301', 'name' => 'Financial Management', 'credit_points' => 12.5],
            ['code' => 'BUS401', 'name' => 'Strategic Management', 'credit_points' => 12.5],

            ['code' => 'GC101', 'name' => 'Global Citizenship Foundations', 'credit_points' => 12.5],
            ['code' => 'GC201', 'name' => 'Cultural Studies', 'credit_points' => 12.5],
            ['code' => 'GC301', 'name' => 'International Relations', 'credit_points' => 12.5],

            ['code' => 'VV101', 'name' => 'Vovinam Basics', 'credit_points' => 12.5],
            ['code' => 'VV201', 'name' => 'Advanced Vovinam Techniques', 'credit_points' => 12.5],
            ['code' => 'VV301', 'name' => 'Vovinam Philosophy', 'credit_points' => 12.5],

            ['code' => 'MC101', 'name' => 'Media Production', 'credit_points' => 12.5],
            ['code' => 'MC201', 'name' => 'Digital Storytelling', 'credit_points' => 12.5],
            ['code' => 'MC301', 'name' => 'Advanced Media Technology', 'credit_points' => 12.5],

            // Alternative equivalent unit
            ['code' => 'CS102A', 'name' => 'Programming Fundamentals (Alternative)', 'credit_points' => 12.5],
        ];

        foreach ($units as $unitData) {
            // Check if the unit already exists
            $existing = Unit::where('code', $unitData['code'])->first();
            if (!$existing) {
                Unit::create($unitData);
            }
        }
    }
}
