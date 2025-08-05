<?php

declare(strict_types=1);

namespace Database\Seeders\Timeline;

use App\Models\Student;
use App\Models\Unit;
use App\Models\AcademicRecord;
use App\Models\Semester;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class CourseExemptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates course exemptions for eligible students
     */
    public function run(): void
    {
        $this->command->info('📜 Processing course exemptions...');

        // Get students (active or suspended but not dropped/graduated)
        $students = Student::whereNotIn('status', ['dropped', 'graduated'])->get();

        if ($students->isEmpty()) {
            throw new \Exception('No students found.');
        }

        // Get SPRING2025 semester for dating exemptions
        $semester = Semester::where('code', 'SPRING2025')->first();

        if (!$semester) {
            throw new \Exception('SPRING2025 semester not found.');
        }

        $exemptionCount = 0;

        foreach ($students as $student) {
            $exemptions = $this->processStudentExemptions($student, $semester);
            $exemptionCount += count($exemptions);
        }

        $this->command->info("✅ Processed {$exemptionCount} course exemptions!");
    }

    private function processStudentExemptions(Student $student, Semester $semester): array
    {
        $exemptions = [];

        // Check for various exemption types
        $exemptions = array_merge($exemptions, $this->checkTransferCreditExemptions($student, $semester));
        $exemptions = array_merge($exemptions, $this->checkProfessionalExperienceExemptions($student, $semester));
        $exemptions = array_merge($exemptions, $this->checkLanguageExemptions($student, $semester));
        $exemptions = array_merge($exemptions, $this->checkAdvancedPlacementExemptions($student, $semester));

        return $exemptions;
    }

    private function checkTransferCreditExemptions(Student $student, Semester $semester): array
    {
        $exemptions = [];

        // Simulate 10% of students having transfer credits
        if (rand(1, 100) <= 10) {
            $transferableUnits = $this->getTransferableUnits();
            $numExemptions = rand(1, 3); // 1-3 exemptions per student

            $selectedUnits = $transferableUnits->random(min($numExemptions, $transferableUnits->count()));

            foreach ($selectedUnits as $unit) {
                $exemption = $this->createExemptionRecord($student, $unit, $semester, [
                    'type' => 'transfer_credit',
                    'reason' => 'Transfer credit from previous institution',
                    'source' => $this->getRandomPreviousInstitution(),
                    'grade_equivalent' => $this->getTransferGrade(),
                ]);

                $exemptions[] = $exemption;
            }
        }

        return $exemptions;
    }

    private function checkProfessionalExperienceExemptions(Student $student, Semester $semester): array
    {
        $exemptions = [];

        // Simulate 5% of students having relevant professional experience
        if (rand(1, 100) <= 5) {
            $experienceUnits = $this->getProfessionalExperienceUnits();
            $selectedUnit = $experienceUnits->random();

            $exemption = $this->createExemptionRecord($student, $selectedUnit, $semester, [
                'type' => 'professional_experience',
                'reason' => 'Relevant professional experience and industry certification',
                'source' => $this->getRandomEmployer(),
                'grade_equivalent' => 'C', // Credit grade for experience
            ]);

            $exemptions[] = $exemption;
        }

        return $exemptions;
    }

    private function checkLanguageExemptions(Student $student, Semester $semester): array
    {
        $exemptions = [];

        // Check if student is international and might be exempt from English units
        if (in_array($student->nationality, ['Chinese', 'Indian', 'Malaysian']) && rand(1, 100) <= 15) {
            $englishUnits = Unit::where('code', 'like', 'ENG%')->get();

            if ($englishUnits->isNotEmpty()) {
                $selectedUnit = $englishUnits->random();

                $exemption = $this->createExemptionRecord($student, $selectedUnit, $semester, [
                    'type' => 'language_proficiency',
                    'reason' => 'IELTS/TOEFL score meets exemption criteria',
                    'source' => 'IELTS Academic - Band 7.5',
                    'grade_equivalent' => 'P', // Pass grade
                ]);

                $exemptions[] = $exemption;
            }
        }

        return $exemptions;
    }

    private function checkAdvancedPlacementExemptions(Student $student, Semester $semester): array
    {
        $exemptions = [];

        // Simulate 3% of students having advanced placement
        if (rand(1, 100) <= 3) {
            $advancedUnits = $this->getAdvancedPlacementUnits();
            $selectedUnit = $advancedUnits->random();

            $exemption = $this->createExemptionRecord($student, $selectedUnit, $semester, [
                'type' => 'advanced_placement',
                'reason' => 'Advanced Placement (AP) or International Baccalaureate (IB) credit',
                'source' => rand(1, 2) === 1 ? 'AP Computer Science A - Score 5' : 'IB Mathematics HL - Score 7',
                'grade_equivalent' => 'D', // Distinction grade for advanced placement
            ]);

            $exemptions[] = $exemption;
        }

        return $exemptions;
    }

    private function createExemptionRecord(Student $student, Unit $unit, Semester $semester, array $exemptionData): AcademicRecord
    {
        $gradePoints = $this->calculateGradePoints($exemptionData['grade_equivalent']);

        // Find any course offering for this unit in this semester, or use the first available course offering
        $courseOffering = \App\Models\CourseOffering::where('unit_id', $unit->id)
            ->where('semester_id', $semester->id)
            ->first() ?? \App\Models\CourseOffering::where('semester_id', $semester->id)->first();

        if (!$courseOffering) {
            throw new \Exception("No course offering found for exemption records in semester {$semester->code}");
        }

        // Check if student already has an academic record for this course offering
        $existingRecord = AcademicRecord::where('student_code', $student->id)
            ->where('course_offering_id', $courseOffering->id)
            ->first();

        if ($existingRecord) {
            $this->command->info("  ⚠️ {$student->student_code}: Already has record for {$unit->code}, skipping exemption");
            return $existingRecord;
        }

        $exemption = AcademicRecord::create([
            'student_code' => $student->id,
            'course_offering_id' => $courseOffering->id, // Use existing course offering
            'semester_id' => $semester->id,
            'unit_id' => $unit->id,
            'program_id' => $student->program_id,
            'campus_id' => $student->campus_id,

            // Grade information
            'final_percentage' => $this->getPercentageFromGrade($exemptionData['grade_equivalent']),
            'final_letter_grade' => $exemptionData['grade_equivalent'],
            'grade_points' => $gradePoints,
            'quality_points' => $gradePoints * (float) $unit->credit_points,
            'credit_hours' => (float) $unit->credit_points,
            'grade_status' => 'final',

            // Completion information
            'completion_status' => 'completed',
            'enrollment_date' => $semester->start_date,
            'completion_date' => $semester->start_date, // Immediate completion for exemptions
            'grade_submission_date' => now(),
            'grade_finalized_date' => now(),

            // Exemption-specific fields
            'is_transfer_credit' => $exemptionData['type'] === 'transfer_credit',
            'transfer_institution' => $exemptionData['source'] ?? null,
            'excluded_from_gpa' => false, // Include in GPA unless specified otherwise

            // Additional information
            'instructor_id' => null,
            'administrative_notes' => "Exemption: {$exemptionData['reason']}. Source: {$exemptionData['source']}",
        ]);

        $this->command->info("  📜 {$student->student_code}: Exempted from {$unit->code} ({$exemptionData['type']})");

        return $exemption;
    }

    private function getTransferableUnits()
    {
        // Units commonly accepted as transfer credits
        return Unit::whereIn('code', [
            'MAT10001', // Mathematics for Computing
            'ENG10001', // English for Academic Purposes
            'ACC10007', // Accounting for Decision Making
            'MKT10001', // Introduction to Marketing
        ])->get();
    }

    private function getProfessionalExperienceUnits()
    {
        // Units that can be exempted based on professional experience
        return Unit::whereIn('code', [
            'HRM10001', // Introduction to Human Resource Management
            'MKT10001', // Introduction to Marketing
            'ACC10007', // Accounting for Decision Making
        ])->get();
    }

    private function getAdvancedPlacementUnits()
    {
        // Units commonly exempted through AP/IB
        return Unit::whereIn('code', [
            'MAT10001', // Mathematics for Computing
            'COS10009', // Introduction to Programming
            'ENG10001', // English for Academic Purposes
        ])->get();
    }

    private function getRandomPreviousInstitution(): string
    {
        $institutions = [
            'University of Melbourne',
            'Monash University',
            'RMIT University',
            'Deakin University',
            'University of Technology Sydney',
            'Griffith University',
            'Queensland University of Technology',
        ];

        return $institutions[array_rand($institutions)];
    }

    private function getRandomEmployer(): string
    {
        $employers = [
            'Microsoft Australia',
            'IBM Australia',
            'Telstra Corporation',
            'Commonwealth Bank',
            'Westpac Banking Corporation',
            'Woolworths Group',
            'BHP Billiton',
        ];

        return $employers[array_rand($employers)];
    }

    private function getTransferGrade(): string
    {
        // Transfer credits typically receive Pass or Credit grades
        $grades = ['P', 'C', 'C', 'D']; // Weighted toward Credit
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
}
