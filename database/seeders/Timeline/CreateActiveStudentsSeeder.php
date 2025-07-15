<?php

declare(strict_types=1);

namespace Database\Seeders\Timeline;

use App\Models\Student;
use App\Models\Campus;
use App\Models\Program;
use App\Models\Specialization;
use App\Models\CurriculumVersion;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;
use Carbon\Carbon;

class CreateActiveStudentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates 100 active students with user accounts
     */
    public function run(): void
    {
        $this->command->info('👥 Creating 100 active students with user accounts...');

        // Check if students already exist
        $existingStudentsCount = Student::count();
        if ($existingStudentsCount >= 100) {
            $this->command->info("✅ Students already exist ({$existingStudentsCount} found). Skipping creation.");
            return;
        }

        // Get required data
        // get campus Hanoi
        $campus = Campus::where('code', 'HN')->first();
        $programs = Program::with('specializations')->get();
        $curriculumVersions = CurriculumVersion::all();

        if ($programs->isEmpty() || $curriculumVersions->isEmpty()) {
            throw new \Exception(message: 'Required data not found. Please run InitialSetup seeders first.');
        }

        $faker = Faker::create();
        $createdCount = 0;

        // Create 100 students
        for ($i = 1; $i <= 100; $i++) {
            // $campus = $campuses->random();
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
            // generate unique last name with fallback
            $lastName = $faker->lastName;
            $fullName = $firstName . ' ' . $lastName;

            // Generate unique student ID based on campus and year
            $year = 2024;
            $campusCode = $this->getCampusCode($campus->name);
            $studentId = $this->generateStudentId($campusCode, $year, $i);
            while (Student::where('student_id', $studentId)->exists()) {
                $i++;
                $studentId = $this->generateStudentId($campusCode, $year, $i);
            }

            // Generate unique email
            $baseEmail = strtolower($firstName . '.' . $lastName . '@student.swinburne.edu.au');
            $email = $baseEmail;
            $counter = 1;
            while (User::where('email', $email)->exists() || Student::where('email', $email)->exists()) {
                $email = strtolower($firstName . '.' . $lastName . $counter . '@student.swinburne.edu.au');
                $counter++;
            }

            // Generate admission and graduation dates
            $admissionDate = Carbon::create(2024, 2, 15)->addDays(rand(0, 30)); // Feb-Mar 2024
            $expectedGraduationDate = $admissionDate->copy()->addYears(4); // 4-year program

            // Create student with active status
            $student = Student::create([
                'student_id' => $studentId,
                'full_name' => $fullName,
                'email' => $email,
                'phone' => $faker->phoneNumber,
                'date_of_birth' => $faker->dateTimeBetween('-25 years', '-18 years')->format('Y-m-d'),
                'gender' => $gender,
                'nationality' => $faker->randomElement(['Australian', 'Malaysian', 'Vietnamese', 'Chinese', 'Indian', 'Indonesian']),
                'national_id' => $this->generateUniqueNationalId($faker),
                'address' => $faker->address,
                'campus_id' => $campus->id,
                'program_id' => $program->id,
                'specialization_id' => $specialization->id,
                'curriculum_version_id' => $curriculumVersion->id,
                'admission_date' => $admissionDate,
                'expected_graduation_date' => $expectedGraduationDate,
                'emergency_contact_name' => $faker->name,
                'emergency_contact_phone' => $faker->phoneNumber,
                'emergency_contact_relationship' => $faker->randomElement(['Parent', 'Guardian', 'Spouse', 'Sibling']),
                'high_school_name' => $faker->company . ' High School',
                'high_school_graduation_year' => $faker->numberBetween(2020, 2024),
                'entrance_exam_score' => $faker->randomFloat(2, 60, 100),
                'admission_notes' => 'Admitted based on academic merit and entrance exam score.',
                'status' => 'active', // Active status by default
            ]);

            // Create user account for the student
            $this->createUserAccount($student);

            $createdCount++;

            if ($i % 20 === 0) {
                $this->command->info("  Created {$i} students with user accounts...");
            }
        }

        $this->command->info("✅ Successfully created {$createdCount} active students with user accounts!");
        $this->command->info("📧 All students have user accounts with default password: password123");
    }

    /**
     * Create user account for the student
     */
    private function createUserAccount(Student $student): User
    {
        return User::create([
            'name' => $student->full_name,
            'email' => $student->email,
            'phone' => $student->phone,
            'address' => $student->address,
            'password' => Hash::make('password123'), // Default password
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Get campus code for student ID generation
     */
    private function getCampusCode(string $campusName): string
    {
        $codes = [
            'Swinburne Hà Nội' => 'HN',
            'Swinburne Hồ Chí Minh' => 'HCM',
            'Swinburne Đà Nẵng' => 'DN',
            'Swinburne Cần Thơ' => 'CT',
        ];

        return $codes[$campusName] ?? 'HN';
    }

    /**
     * Generate student ID based on campus code, year, and sequence
     */
    private function generateStudentId(string $campusCode, int $year, int $sequence): string
    {
        return $campusCode . $year . str_pad((string)$sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Generate unique national ID
     */
    private function generateUniqueNationalId($faker): string
    {
        $nationalId = $faker->numerify('##########');
        while (Student::where('national_id', $nationalId)->exists()) {
            $nationalId = $faker->numerify('##########');
        }
        return $nationalId;
    }
}
