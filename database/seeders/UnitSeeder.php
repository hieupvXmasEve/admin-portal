<?php

declare(strict_types=1);

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
        // Clear existing units
        Unit::query()->delete();

        // Create Units based on Bachelor of Computer Science curriculum
        $units = [
            // Computer Science Core Units
            ['code' => 'COS10009', 'name' => 'Introduction to Programming', 'credit_points' => 12.5],
            ['code' => 'COS10004', 'name' => 'Computer Systems', 'credit_points' => 12.5],
            ['code' => 'COS10026', 'name' => 'Computing Technology Inquiry Project', 'credit_points' => 12.5],
            ['code' => 'TNE10006', 'name' => 'Networks and Switching', 'credit_points' => 12.5],
            ['code' => 'COS10025', 'name' => 'Technology in an Indigenous Context Project', 'credit_points' => 12.5],
            ['code' => 'COS20007', 'name' => 'Object Oriented Programming', 'credit_points' => 12.5],
            ['code' => 'COS20019', 'name' => 'Cloud Computing Technology', 'credit_points' => 12.5],
            ['code' => 'COS20031', 'name' => 'Computing Technology Design Project', 'credit_points' => 12.5],
            ['code' => 'COS30019', 'name' => 'Introduction to Artificial Intelligence', 'credit_points' => 12.5],
            ['code' => 'COS30049', 'name' => 'Computing Technology Innovation Project', 'credit_points' => 12.5],
            ['code' => 'SWE30003', 'name' => 'Software Architecture and Design', 'credit_points' => 12.5],
            ['code' => 'COS30018', 'name' => 'Intelligent Systems', 'credit_points' => 12.5],
            ['code' => 'SWE40005', 'name' => 'Computing Technology Project A', 'credit_points' => 12.5],
            ['code' => 'COS40007', 'name' => 'Artificial Intelligence for Engineering', 'credit_points' => 12.5],
            ['code' => 'SWE30032', 'name' => 'Software Engineering Project', 'credit_points' => 12.5],
            ['code' => 'COS40006', 'name' => 'Computing Technology Project B', 'credit_points' => 12.5],
            // Additional units to satisfy curriculum requirements (total >= 27)
            ['code' => 'COS20024', 'name' => 'Data Structures and Algorithms', 'credit_points' => 12.5],
            ['code' => 'COS20096', 'name' => 'Database Systems', 'credit_points' => 12.5],
            ['code' => 'COS30017', 'name' => 'Software Testing and Reliability', 'credit_points' => 12.5],
            ['code' => 'COS30043', 'name' => 'Interface Design and Development', 'credit_points' => 12.5],
            ['code' => 'COS30045', 'name' => 'Advanced Network Design', 'credit_points' => 12.5],
            ['code' => 'COS40009', 'name' => 'Advanced Algorithms', 'credit_points' => 12.5],
            ['code' => 'COS40011', 'name' => 'Parallel and Distributed Computing', 'credit_points' => 12.5],
            ['code' => 'TNE20003', 'name' => 'Internet Technologies', 'credit_points' => 12.5],
            ['code' => 'COS10021', 'name' => 'Web Programming', 'credit_points' => 12.5],
            ['code' => 'COS20015', 'name' => 'IT Project Management', 'credit_points' => 12.5],
            ['code' => 'COS30082', 'name' => 'Data Visualization', 'credit_points' => 12.5],
            ['code' => 'COS40019', 'name' => 'Machine Learning Applications', 'credit_points' => 12.5],
        ];

        foreach ($units as $unitData) {
            Unit::create($unitData);
        }
    }
}
