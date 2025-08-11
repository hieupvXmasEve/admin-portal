<?php

declare(strict_types=1);

namespace Database\Seeders\Timeline;

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class TransferStudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates transfer students with credit transfers
     */
    public function run(): void
    {
        $this->command->info('🔄 Creating transfer students...');

        // Get available programs and campuses
        $programs = Program::all();
        $campuses = Campus::all();

        if ($programs->isEmpty() || $campuses->isEmpty()) {
            throw new \Exception('Programs or campuses not found. Please run InitialSetup seeders first.');
        }

        // Create 15-20 transfer students
        $transferCount = rand(15, 20);
        $createdStudents = 0;

        for ($i = 1; $i <= $transferCount; $i++) {
            $this->createTransferStudent($programs, $campuses);
            $createdStudents++;
        }

        $this->command->info("✅ Created {$createdStudents} transfer students with credit transfers!");
    }

    private function createTransferStudent($programs, $campuses): void
    {
        $campus = $campuses->random();
        $program = $programs->random();

        // Get curriculum version for the program
        $curriculumVersion = CurriculumVersion::where('program_id', $program->id)
            ->first();

        if (! $curriculumVersion) {
            return; // Skip if no curriculum version
        }

        // Create student record directly (no separate user)
        $student = $this->createTransferStudentRecord($campus, $program, $curriculumVersion);

        // Create transfer credit records
        $this->createTransferCredits($student);

        $this->command->info("  🔄 {$student->student_id}: Transfer from {$this->getRandomPreviousInstitution()}");
    }

    private function createTransferStudentRecord(Campus $campus, Program $program, CurriculumVersion $curriculumVersion): Student
    {
        $transferDate = $this->getTransferDate();
        $firstName = $this->getRandomFirstName();
        $lastName = $this->getRandomLastName();
        $fullName = $firstName . ' ' . $lastName;
        $email = strtolower($firstName . '.' . $lastName . rand(100, 999) . '@student.swinburne.edu.au');

        return Student::create([
            'student_id' => $this->generateTransferStudentId($campus),
            'full_name' => $fullName,
            'email' => $email,
            'campus_id' => $campus->id,
            'program_id' => $program->id,
            'specialization_id' => null,
            'curriculum_version_id' => $curriculumVersion->id,
            'status' => 'active',
            'admission_date' => $transferDate,
            'expected_graduation_date' => $transferDate->copy()->addYears(2),
            'nationality' => $this->getRandomNationality(),
            'date_of_birth' => $this->getRandomDateOfBirth(),
            'gender' => $this->getRandomGender(),
            'admission_notes' => $this->generateTransferAdmissionNotes(),
        ]);
    }

    private function createTransferCredits(Student $student): void
    {
        // Get transferable units
        $transferableUnits = Unit::whereIn('code', [
            'MAT10001', // Mathematics for Computing
            'ENG10001', // English for Academic Purposes
            'COS10009', // Introduction to Programming
            'ACC10007', // Accounting for Decision Making
            'MKT10001', // Introduction to Marketing
            'HRM10001', // Introduction to Human Resource Management
        ])->get();

        // Transfer 2-5 units
        $transferCount = rand(2, 5);
        $selectedUnits = $transferableUnits->random(min($transferCount, $transferableUnits->count()));

        // Get a semester for dating the transfer credits
        $semester = Semester::where('code', 'FALL2024')->first();

        foreach ($selectedUnits as $unit) {
            $this->createTransferCreditRecord($student, $unit, $semester);
        }
    }

    private function createTransferCreditRecord(Student $student, Unit $unit, Semester $semester): void
    {
        $transferGrade = $this->getTransferGrade();
        $gradePoints = $this->calculateGradePoints($transferGrade);
        $previousInstitution = $this->getRandomPreviousInstitution();

        // Find a course offering for this unit in the semester
        $courseOffering = \App\Models\CourseOffering::where('unit_id', $unit->id)
            ->where('semester_id', $semester->id)
            ->first();

        // If no course offering exists for this specific unit/semester, find any course offering for this unit
        if (! $courseOffering) {
            $courseOffering = \App\Models\CourseOffering::where('unit_id', $unit->id)->first();
        }

        // If still no course offering, skip this transfer credit
        if (! $courseOffering) {
            return;
        }

        AcademicRecord::create([
            'student_id' => $student->id,
            'course_offering_id' => $courseOffering->id,
            'semester_id' => $semester->id,
            'unit_id' => $unit->id,
            'program_id' => $student->program_id,
            'campus_id' => $student->campus_id,

            // Grade information
            'final_percentage' => $this->getPercentageFromGrade($transferGrade),
            'final_letter_grade' => $transferGrade,
            'grade_points' => $gradePoints,
            'quality_points' => $gradePoints * $unit->credit_points,
            'credit_hours' => $unit->credit_points,
            'credit_hours_earned' => $unit->credit_points,
            'grade_status' => 'final',

            // Completion information
            'completion_status' => 'completed',
            'enrollment_date' => $semester->start_date->subMonths(rand(6, 24)),
            'completion_date' => $semester->start_date->subMonths(rand(1, 6)),
            'grade_submission_date' => now(),
            'grade_finalized_date' => now(),

            // Transfer credit specific fields
            'is_transfer_credit' => true,
            'transfer_institution' => $previousInstitution,
            'excluded_from_gpa' => false,

            // Additional information
            'instructor_id' => null,
            'administrative_notes' => "Transfer credit from {$previousInstitution}. Original course equivalent to {$unit->code}.",
        ]);
    }

    private function generateTransferStudentId(Campus $campus): string
    {
        $campusCode = strtoupper(substr($campus->code, 0, 3));
        $year = date('y');
        $sequence = str_pad((string) rand(5000, 9999), 4, '0', STR_PAD_LEFT);

        return "T{$campusCode}{$year}{$sequence}";
    }

    private function getTransferDate(): Carbon
    {
        $transferDates = [
            Carbon::create(2024, 9, 1),
            Carbon::create(2025, 2, 1),
            Carbon::create(2024, 6, 1),
        ];

        return $transferDates[array_rand($transferDates)];
    }

    private function generateTransferAdmissionNotes(): string
    {
        $previousInstitution = $this->getRandomPreviousInstitution();
        $transferReason = $this->getRandomTransferReason();

        return "Transfer student from {$previousInstitution}. Reason: {$transferReason}. Credit evaluation completed.";
    }

    private function getRandomPreviousInstitution(): string
    {
        $institutions = [
            'University of Melbourne',
            'Monash University',
            'RMIT University',
            'Deakin University',
            'La Trobe University',
            'Griffith University',
            'Queensland University of Technology',
            'University of Technology Sydney',
            'Macquarie University',
            'Curtin University',
            'University of South Australia',
            'Victoria University',
        ];

        return $institutions[array_rand($institutions)];
    }

    private function getRandomTransferReason(): string
    {
        $reasons = [
            'Program not available at previous institution',
            'Relocation for family reasons',
            'Better program reputation',
            'Financial considerations',
            'Career change requiring different specialization',
            'Closer to home',
            'Better industry connections',
            'Preferred campus location',
        ];

        return $reasons[array_rand($reasons)];
    }

    private function getTransferGrade(): string
    {
        $grades = ['C', 'C', 'D', 'D', 'HD'];

        return $grades[array_rand($grades)];
    }

    private function calculateGradePoints(string $letterGrade): float
    {
        $gradePoints = [
            'HD' => 4.0,
            'D' => 3.0,
            'C' => 2.0,
            'P' => 1.0,
            'N' => 0.0,
        ];

        return $gradePoints[$letterGrade] ?? 0.0;
    }

    private function getPercentageFromGrade(string $letterGrade): float
    {
        $percentages = [
            'HD' => 85.0,
            'D' => 75.0,
            'C' => 65.0,
            'P' => 55.0,
            'N' => 40.0,
        ];

        return $percentages[$letterGrade] ?? 0.0;
    }

    private function getRandomFirstName(): string
    {
        $names = [
            'James',
            'Sarah',
            'Michael',
            'Emma',
            'William',
            'Emily',
            'David',
            'Jessica',
            'Christopher',
            'Ashley',
            'Daniel',
            'Amanda',
            'Matthew',
            'Stephanie',
            'Anthony',
            'Melissa',
            'Mark',
            'Nicole',
            'Steven',
            'Elizabeth',
            'Andrew',
            'Megan',
            'Joshua',
            'Rachel',
            'Kenneth',
            'Lauren',
            'Paul',
            'Kimberly',
            'Alexander',
            'Amy',
            'Patrick',
            'Christina',
            'Jack',
            'Samantha',
            'Dennis',
            'Brittany',
            'Jerry',
            'Deborah',
            'Tyler',
            'Rebecca',
            'Aaron',
            'Kayla',
            'Jose',
            'Alexis',
        ];

        return $names[array_rand($names)];
    }

    private function getRandomLastName(): string
    {
        $names = [
            'Smith',
            'Johnson',
            'Williams',
            'Brown',
            'Jones',
            'Garcia',
            'Miller',
            'Davis',
            'Rodriguez',
            'Martinez',
            'Hernandez',
            'Lopez',
            'Gonzalez',
            'Wilson',
            'Anderson',
            'Thomas',
            'Taylor',
            'Moore',
            'Jackson',
            'Martin',
            'Lee',
            'Perez',
            'Thompson',
            'White',
            'Harris',
            'Sanchez',
            'Clark',
            'Ramirez',
            'Lewis',
            'Robinson',
            'Walker',
            'Young',
            'Allen',
            'King',
            'Wright',
            'Scott',
            'Torres',
            'Nguyen',
            'Hill',
            'Flores',
        ];

        return $names[array_rand($names)];
    }

    private function getRandomNationality(): string
    {
        $nationalities = ['Australian', 'Vietnamese', 'Chinese', 'Indian', 'Malaysian', 'Indonesian'];

        return $nationalities[array_rand($nationalities)];
    }

    private function getRandomDateOfBirth(): Carbon
    {
        return Carbon::now()->subYears(rand(20, 25))->subDays(rand(0, 365));
    }

    private function getRandomGender(): string
    {
        return ['male', 'female', 'other'][array_rand(['male', 'female', 'other'])];
    }
}
