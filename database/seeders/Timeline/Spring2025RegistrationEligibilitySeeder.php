<?php

declare(strict_types=1);

namespace Database\Seeders\Timeline;

use App\Models\AcademicHold;
use App\Models\CurriculumUnit;
use App\Models\GpaCalculation;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Database\Seeder;

class Spring2025RegistrationEligibilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Determines student eligibility for SPRING2025 registration
     */
    public function run(): void
    {
        $this->command->info('🔍 Checking SPRING2025 registration eligibility...');

        // Get SPRING2025 semester
        $spring2025 = Semester::where('code', 'SPRING2025')->first();

        if (! $spring2025) {
            throw new \Exception('SPRING2025 semester not found. Please create semester first.');
        }

        // Get all students (active or suspended)
        $students = Student::whereIn('status', ['active', 'suspended'])->get();

        if ($students->isEmpty()) {
            throw new \Exception('No students found.');
        }

        $eligibleCount = 0;
        $ineligibleCount = 0;
        $holdCount = 0;

        foreach ($students as $student) {
            $eligibilityResult = $this->checkRegistrationEligibility($student, $spring2025);

            if ($eligibilityResult['eligible']) {
                $eligibleCount++;
            } else {
                $ineligibleCount++;
                if ($eligibilityResult['new_holds'] > 0) {
                    $holdCount += $eligibilityResult['new_holds'];
                }
            }
        }

        $this->command->info('✅ Registration eligibility check complete!');
        $this->command->info("  ✅ Eligible students: {$eligibleCount}");
        $this->command->info("  ❌ Ineligible students: {$ineligibleCount}");
        $this->command->info("  ⚠️ New holds created: {$holdCount}");
    }

    private function checkRegistrationEligibility(Student $student, Semester $semester): array
    {
        $eligible = true;
        $reasons = [];
        $newHolds = 0;

        // Check for active holds
        $activeHolds = AcademicHold::where('student_code', $student->id)
            ->where('status', 'active')
            ->get();

        if ($activeHolds->isNotEmpty()) {
            $eligible = false;
            $reasons[] = 'Active academic/financial holds';
        }

        // Check GPA requirements
        $latestGPA = GpaCalculation::where('student_code', $student->id)
            ->where('calculation_type', 'cumulative')
            ->latest('calculated_at')
            ->first();

        if ($latestGPA && $latestGPA->gpa < 0.5) {
            $eligible = false;
            $reasons[] = 'GPA below minimum threshold (0.5)';

            // Create academic suspension hold
            $this->createAcademicSuspensionHold($student, $semester);
            $newHolds++;
        }

        // Check prerequisite completion for second semester units
        $prerequisiteCheck = $this->checkPrerequisiteCompletion($student);
        if (! $prerequisiteCheck['eligible']) {
            $eligible = false;
            $reasons = array_merge($reasons, $prerequisiteCheck['reasons']);
        }

        // Check enrollment capacity (simulate some students being blocked due to capacity)
        if (rand(1, 100) <= 5) { // 5% chance of capacity issues
            $eligible = false;
            $reasons[] = 'Course capacity limitations';
        }

        // Update student notes if ineligible
        if (! $eligible) {
            $student->update([
                'admission_notes' => 'Registration eligibility issues: '.implode(', ', $reasons),
            ]);
        }

        return [
            'eligible' => $eligible,
            'reasons' => $reasons,
            'new_holds' => $newHolds,
        ];
    }

    private function checkPrerequisiteCompletion(Student $student): array
    {
        // Get second semester units for this student's curriculum
        $secondSemesterUnits = CurriculumUnit::with(['unit.prerequisiteGroups.conditions.requiredUnit'])
            ->where('curriculum_version_id', $student->curriculum_version_id)
            ->where('year_level', 1)
            ->where('semester_number', 2)
            ->get();

        $eligible = true;
        $reasons = [];

        foreach ($secondSemesterUnits as $curriculumUnit) {
            $unit = $curriculumUnit->unit;

            // Check if student has completed prerequisites
            foreach ($unit->prerequisiteGroups as $group) {
                $groupSatisfied = $this->checkPrerequisiteGroup($student, $group);

                if (! $groupSatisfied) {
                    $eligible = false;
                    $reasons[] = "Prerequisites not met for {$unit->code}";
                }
            }
        }

        return [
            'eligible' => $eligible,
            'reasons' => $reasons,
        ];
    }

    private function checkPrerequisiteGroup(Student $student, $prerequisiteGroup): bool
    {
        $satisfiedConditions = 0;
        $requiredConditions = $prerequisiteGroup->required_conditions ?? $prerequisiteGroup->conditions->count();

        foreach ($prerequisiteGroup->conditions as $condition) {
            if ($this->hasCompletedUnit($student, $condition->required_unit_id)) {
                $satisfiedConditions++;
            }
        }

        return $satisfiedConditions >= $requiredConditions;
    }

    private function hasCompletedUnit(Student $student, int $unitId): bool
    {
        return $student->academicRecords()
            ->where('unit_id', $unitId)
            ->where('completion_status', 'completed')
            ->where('grade_status', 'final')
            ->exists();
    }

    private function createAcademicSuspensionHold(Student $student, Semester $semester): void
    {
        AcademicHold::create([
            'student_code' => $student->id,
            'hold_type' => 'academic',
            'hold_category' => 'all',
            'title' => 'Academic Suspension',
            'description' => 'Student suspended from enrollment due to extremely poor academic performance (GPA < 0.5). Academic appeal process required.',
            'amount' => null,
            'priority' => 'high',
            'status' => 'active',
            'placed_date' => now(),
            'due_date' => null, // Indefinite until appeal
            'resolved_date' => null,
            'placed_by_user_id' => 1,
            'resolved_by_user_id' => null,
            'resolution_notes' => null,
        ]);

        // Update student status
        $student->update([
            'status' => 'suspended',
            'admission_notes' => 'Academic suspension due to GPA below 0.5',
        ]);
    }
}
