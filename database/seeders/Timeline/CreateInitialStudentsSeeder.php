<?php

declare(strict_types=1);

namespace Database\Seeders\Timeline;

use App\Models\Student;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Specialization;
use App\Models\CurriculumVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class CreateInitialStudentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates 100 initial students with applicant status
     */
    public function run(): void
    {
        $this->command->info('👥 Creating 100 initial students...');

        // Clean existing students and related data
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Student::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Get required data
        $campuses = Campus::all();
        $programs = Program::with('specializations')->get();
        $curriculumVersions = CurriculumVersion::all();

        if ($campuses->isEmpty() || $programs->isEmpty() || $curriculumVersions->isEmpty()) {
            throw new \Exception('Required data not found. Please run InitialSetup seeders first.');
        }

        $faker = Faker::create();

        // Create 100 students
        for ($i = 1; $i <= 100; $i++) {
            $campus = $campuses->random();
            $program = $programs->random();
            $specialization = $program->specializations->random();

            // Find curriculum version for this program/specialization
            $curriculumVersion = $curriculumVersions->where('program_id', $program->id)
                ->where('specialization_id', $specialization->id)
                ->first();

            if (!$curriculumVersion) {
                // Fallback to any curriculum version for this program
                $curriculumVersion = $curriculumVersions->where('program_id', $program->id)->first();
            }

            if (!$curriculumVersion) {
                $this->command->warn("No curriculum version found for program {$program->name}, skipping student {$i}");
                continue;
            }

            // Generate student data
            $gender = $faker->randomElement(['male', 'female']);
            $firstName = $faker->firstName($gender);
            $lastName = $faker->lastName;
            $fullName = $firstName . ' ' . $lastName;

            // Generate student ID based on campus and year
            $year = 2024;
            $campusCode = $this->getCampusCode($campus->name);
            $studentId = $this->generateStudentId($campusCode, $year, $i);

            $student = Student::create([
                'student_id' => $studentId,
                'full_name' => $fullName,
                'email' => strtolower($firstName . '.' . $lastName . '@student.swinburne.edu.au'),
                'phone' => $faker->phoneNumber,
                'date_of_birth' => $faker->dateTimeBetween('-25 years', '-18 years')->format('Y-m-d'),
                'gender' => $gender,
                'nationality' => $faker->randomElement(['Australian', 'Malaysian', 'Vietnamese', 'Chinese', 'Indian', 'Indonesian']),
                'national_id' => $faker->unique()->numerify('##########'),
                'address' => $faker->address,
                'campus_id' => $campus->id,
                'program_id' => $program->id,
                'specialization_id' => $specialization->id,
                'curriculum_version_id' => $curriculumVersion->id,
                'admission_date' => '2024-02-01', // Default admission date - will be updated in admission seeder
                'expected_graduation_date' => null, // Will be calculated after admission
                'emergency_contact_name' => $faker->name,
                'emergency_contact_phone' => $faker->phoneNumber,
                'emergency_contact_relationship' => $faker->randomElement(['Parent', 'Guardian', 'Spouse', 'Sibling']),
                'high_school_name' => $faker->company . ' High School',
                'high_school_graduation_year' => $faker->numberBetween(2020, 2024),
                'entrance_exam_score' => $faker->randomFloat(2, 60, 100),
                'admission_notes' => $faker->optional(0.3)->sentence,
                'status' => 'inactive', // Initial status (inactive until admission process completes)
            ]);

            if ($i % 20 === 0) {
                $this->command->info("  Created {$i} students...");
            }
        }

        $this->command->info('✅ Created 100 initial students with applicant status!');
    }

    private function getCampusCode(string $campusName): string
    {
        $codes = [
            'Swinburne Hà Nội' => 'HN',
            'Swinburne Hồ Chí Minh' => 'HCM',
            'Swinburne Đà Nẵng' => 'DN',
            'Swinburne Cần Thơ' => 'CT',
        ];

        return $codes[$campusName] ?? 'UNK';
    }

    private function generateStudentId(string $campusCode, int $year, int $sequence): string
    {
        return $campusCode . $year . str_pad((string)$sequence, 3, '0', STR_PAD_LEFT);
    }
}
