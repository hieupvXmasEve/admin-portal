<?php

declare(strict_types=1);

namespace Database\Seeders\Timeline;

use App\Models\GpaCalculation;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\UnitPrerequisiteGroup;
use Illuminate\Database\Seeder;

class AdvancedUnitEligibilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Determines student eligibility for advanced units in future semesters
     */
    public function run(): void
    {
        $this->command->info('🎓 Checking advanced unit eligibility...');

        // Get students who have completed at least one semester
        $students = Student::whereHas('academicRecords', function ($query) {
            $query->where('grade_status', 'final');
        })->with(['academicRecords' => function ($query) {
            $query->where('grade_status', 'final');
        }])->get();

        if ($students->isEmpty()) {
            throw new \Exception('No students with completed academic records found.');
        }

        $eligibilityResults = [
            'year2_eligible' => 0,
            'year3_eligible' => 0,
            'honors_eligible' => 0,
            'advanced_standing' => 0,
            'prerequisite_blocked' => 0,
        ];

        foreach ($students as $student) {
            $result = $this->assessStudentEligibility($student);

            foreach ($result as $key => $value) {
                if (isset($eligibilityResults[$key]) && $value) {
                    $eligibilityResults[$key]++;
                }
            }
        }

        $this->displayEligibilityResults($eligibilityResults);
    }

    private function assessStudentEligibility(Student $student): array
    {
        $results = [
            'year2_eligible' => false,
            'year3_eligible' => false,
            'honors_eligible' => false,
            'advanced_standing' => false,
            'prerequisite_blocked' => false,
        ];

        // Get student's latest GPA
        $latestGPA = GpaCalculation::where('student_id', $student->id)
            ->where('calculation_type', 'cumulative')
            ->latest('calculated_at')
            ->first();

        if (! $latestGPA) {
            return $results;
        }

        // Check completed credit hours
        $completedCredits = $student->academicRecords
            ->where('completion_status', 'completed')
            ->sum('credit_hours');

        // Check year 2 eligibility (completed year 1 requirements)
        $results['year2_eligible'] = $this->checkYear2Eligibility($student, $completedCredits, $latestGPA);

        // Check year 3 eligibility (completed year 2 requirements)
        $results['year3_eligible'] = $this->checkYear3Eligibility($student, $completedCredits, $latestGPA);

        // Check honors eligibility
        $results['honors_eligible'] = $this->checkHonorsEligibility($student, $completedCredits, $latestGPA);

        // Check advanced standing (accelerated progression)
        $results['advanced_standing'] = $this->checkAdvancedStanding($student, $completedCredits, $latestGPA);

        // Check for prerequisite blocks
        $results['prerequisite_blocked'] = $this->checkPrerequisiteBlocks($student);

        // Update student record with eligibility information
        $this->updateStudentEligibility($student, $results, $latestGPA);

        return $results;
    }

    private function checkYear2Eligibility(Student $student, float $completedCredits, GpaCalculation $gpa): bool
    {
        // Requirements for year 2:
        // - Completed at least 24 credit hours (typical year 1 load)
        // - GPA >= 1.5
        // - Completed core foundation units

        if ($completedCredits < 24 || $gpa->gpa < 1.5) {
            return false;
        }

        // Check if core foundation units are completed
        $foundationUnits = $this->getFoundationUnits();
        $completedFoundationUnits = $student->academicRecords
            ->whereIn('unit_id', $foundationUnits->pluck('id'))
            ->where('completion_status', 'completed')
            ->count();

        // Must complete at least 75% of foundation units
        return $completedFoundationUnits >= ($foundationUnits->count() * 0.75);
    }

    private function checkYear3Eligibility(Student $student, float $completedCredits, GpaCalculation $gpa): bool
    {
        // Requirements for year 3:
        // - Completed at least 48 credit hours (typical years 1-2 load)
        // - GPA >= 2.0
        // - Completed year 2 core units

        if ($completedCredits < 48 || $gpa->gpa < 2.0) {
            return false;
        }

        // For this simulation, assume students need more time to reach year 3
        // Most students in this seeder are still in year 1
        return false;
    }

    private function checkHonorsEligibility(Student $student, float $completedCredits, GpaCalculation $gpa): bool
    {
        // Requirements for honors:
        // - Completed at least 72 credit hours (3 years)
        // - GPA >= 3.5
        // - No failed units in final year

        if ($completedCredits < 72 || $gpa->gpa < 3.5) {
            return false;
        }

        // Check for recent failures
        $recentFailures = $student->academicRecords
            ->where('completion_status', 'failed')
            ->where('grade_finalized_date', '>=', now()->subMonths(12))
            ->count();

        return $recentFailures === 0;
    }

    private function checkAdvancedStanding(Student $student, float $completedCredits, GpaCalculation $gpa): bool
    {
        // Advanced standing criteria:
        // - Exceptional GPA (>= 3.8)
        // - Completed more credits than typical for time enrolled
        // - No failed units

        if ($gpa->gpa < 3.8) {
            return false;
        }

        $failedUnits = $student->academicRecords
            ->where('completion_status', 'failed')
            ->count();

        if ($failedUnits > 0) {
            return false;
        }

        // Check if student has completed more than expected for their enrollment time
        // For first year students, advanced standing if completed > 30 credits with high GPA
        return $completedCredits > 30;
    }

    private function checkPrerequisiteBlocks(Student $student): bool
    {
        // Check if student has failed prerequisite units that block progression
        $failedUnits = $student->academicRecords
            ->where('completion_status', 'failed')
            ->pluck('unit_id');

        if ($failedUnits->isEmpty()) {
            return false;
        }

        // Check if any failed units are prerequisites for other units
        $blockedUnits = UnitPrerequisiteGroup::whereHas('conditions', function ($query) use ($failedUnits) {
            $query->whereIn('required_unit_id', $failedUnits);
        })->count();

        return $blockedUnits > 0;
    }

    private function getFoundationUnits()
    {
        // Core foundation units that must be completed for progression
        return Unit::whereIn('code', [
            'COS10009', // Introduction to Programming
            'COS10011', // Creating Web Applications
            'MAT10001', // Mathematics for Computing
            'ENG10001', // English for Academic Purposes
        ])->get();
    }

    private function updateStudentEligibility(Student $student, array $results, GpaCalculation $gpa): void
    {
        $eligibilityNotes = [];

        if ($results['year2_eligible']) {
            $eligibilityNotes[] = 'Eligible for Year 2 units';
        }

        if ($results['year3_eligible']) {
            $eligibilityNotes[] = 'Eligible for Year 3 units';
        }

        if ($results['honors_eligible']) {
            $eligibilityNotes[] = 'Eligible for Honors program';
        }

        if ($results['advanced_standing']) {
            $eligibilityNotes[] = 'Eligible for Advanced Standing';
        }

        if ($results['prerequisite_blocked']) {
            $eligibilityNotes[] = 'Progression blocked by failed prerequisites';
        }

        if (empty($eligibilityNotes)) {
            $eligibilityNotes[] = 'Standard progression - Year 1 units only';
        }

        // Update student admission notes with eligibility information
        $currentNotes = $student->admission_notes ?? '';
        $newNotes = trim($currentNotes . ' | Eligibility: ' . implode(', ', $eligibilityNotes));

        $student->update([
            'admission_notes' => $newNotes,
        ]);

        // Log high-performing students
        if ($results['advanced_standing'] || $results['honors_eligible']) {
            $this->command->info("  🌟 {$student->student_id}: " . implode(', ', $eligibilityNotes) . " (GPA: {$gpa->gpa})");
        }
    }

    private function displayEligibilityResults(array $results): void
    {
        $this->command->info('✅ Advanced unit eligibility assessment complete!');
        $this->command->info("  📚 Year 2 eligible: {$results['year2_eligible']} students");
        $this->command->info("  📖 Year 3 eligible: {$results['year3_eligible']} students");
        $this->command->info("  🏆 Honors eligible: {$results['honors_eligible']} students");
        $this->command->info("  ⭐ Advanced standing: {$results['advanced_standing']} students");
        $this->command->info("  ⚠️ Prerequisite blocked: {$results['prerequisite_blocked']} students");
    }
}
