<?php

declare(strict_types=1);

namespace Database\Seeders\Timeline;

use App\Models\AcademicHold;
use App\Models\Student;
use Illuminate\Database\Seeder;

class GraduationEligibilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Assesses graduation eligibility for students near completion
     */
    public function run(): void
    {
        $this->command->info('🎓 Assessing graduation eligibility...');

        // Get students who might be eligible for graduation
        $potentialGraduates = Student::where('status', 'active')
            ->whereHas('academicRecords', function ($query) {
                $query->where('completion_status', 'completed')
                    ->where('grade_status', 'final');
            })
            ->with([
                'academicRecords' => function ($query) {
                    $query->where('grade_status', 'final');
                },
                'curriculumVersion.curriculumUnits.unit',
                'gpaCalculations' => function ($query) {
                    $query->where('calculation_type', 'cumulative')
                        ->latest('calculated_at');
                },
            ])
            ->get();

        if ($potentialGraduates->isEmpty()) {
            throw new \Exception('No potential graduates found.');
        }

        $eligibilityResults = [
            'eligible' => 0,
            'pending_requirements' => 0,
            'gpa_insufficient' => 0,
            'holds_blocking' => 0,
            'credits_insufficient' => 0,
        ];

        foreach ($potentialGraduates as $student) {
            $eligibilityResult = $this->assessGraduationEligibility($student);
            $eligibilityResults[$eligibilityResult['status']]++;
        }

        $this->displayEligibilityResults($eligibilityResults);
    }

    private function assessGraduationEligibility(Student $student): array
    {
        $eligibilityChecks = [
            'credit_requirements' => false,
            'gpa_requirements' => false,
            'curriculum_requirements' => false,
            'no_active_holds' => false,
            'residency_requirements' => false,
        ];

        $issues = [];

        // Check credit hour requirements
        $creditCheck = $this->checkCreditRequirements($student);
        $eligibilityChecks['credit_requirements'] = $creditCheck['eligible'];
        if (! $creditCheck['eligible']) {
            $issues[] = $creditCheck['issue'];
        }

        // Check GPA requirements
        $gpaCheck = $this->checkGPARequirements($student);
        $eligibilityChecks['gpa_requirements'] = $gpaCheck['eligible'];
        if (! $gpaCheck['eligible']) {
            $issues[] = $gpaCheck['issue'];
        }

        // Check curriculum completion
        $curriculumCheck = $this->checkCurriculumRequirements($student);
        $eligibilityChecks['curriculum_requirements'] = $curriculumCheck['eligible'];
        if (! $curriculumCheck['eligible']) {
            $issues[] = $curriculumCheck['issue'];
        }

        // Check for active holds
        $holdsCheck = $this->checkActiveHolds($student);
        $eligibilityChecks['no_active_holds'] = $holdsCheck['eligible'];
        if (! $holdsCheck['eligible']) {
            $issues[] = $holdsCheck['issue'];
        }

        // Check residency requirements (simplified)
        $residencyCheck = $this->checkResidencyRequirements($student);
        $eligibilityChecks['residency_requirements'] = $residencyCheck['eligible'];
        if (! $residencyCheck['eligible']) {
            $issues[] = $residencyCheck['issue'];
        }

        // Determine overall eligibility
        $overallEligible = array_reduce($eligibilityChecks, function ($carry, $check) {
            return $carry && $check;
        }, true);

        $status = $this->determineEligibilityStatus($eligibilityChecks, $issues);

        // Update student record
        $this->updateStudentEligibilityStatus($student, $overallEligible, $issues, $eligibilityChecks);

        return [
            'eligible' => $overallEligible,
            'status' => $status,
            'checks' => $eligibilityChecks,
            'issues' => $issues,
        ];
    }

    private function checkCreditRequirements(Student $student): array
    {
        $requiredCredits = $student->curriculumVersion->curriculumUnits->sum('unit.credit_points');
        $completedCredits = $student->academicRecords
            ->where('completion_status', 'completed')
            ->sum('credit_hours');

        $eligible = $completedCredits >= $requiredCredits;

        return [
            'eligible' => $eligible,
            'issue' => $eligible ? null : "Insufficient credits: {$completedCredits}/{$requiredCredits}",
            'completed_credits' => $completedCredits,
            'required_credits' => $requiredCredits,
        ];
    }

    private function checkGPARequirements(Student $student): array
    {
        $latestGPA = $student->gpaCalculations->first();
        $minimumGPA = 2.0; // Standard graduation GPA requirement

        if (! $latestGPA) {
            return [
                'eligible' => false,
                'issue' => 'No GPA calculation available',
            ];
        }

        $eligible = $latestGPA->gpa >= $minimumGPA;

        return [
            'eligible' => $eligible,
            'issue' => $eligible ? null : "GPA below minimum: {$latestGPA->gpa}/{$minimumGPA}",
            'current_gpa' => $latestGPA->gpa,
            'required_gpa' => $minimumGPA,
        ];
    }

    private function checkCurriculumRequirements(Student $student): array
    {
        $curriculumUnits = $student->curriculumVersion->curriculumUnits;
        $completedUnitIds = $student->academicRecords
            ->where('completion_status', 'completed')
            ->pluck('unit_id')
            ->toArray();

        // Check completion by category
        $categoryRequirements = [];
        $allCategoriesMet = true;

        foreach (['core', 'major', 'elective'] as $category) {
            $categoryUnits = $curriculumUnits->where('unit_type', $category);
            $completedCategoryUnits = $categoryUnits->whereIn('unit_id', $completedUnitIds);

            $required = $categoryUnits->count();
            $completed = $completedCategoryUnits->count();
            $met = $completed >= $required;

            $categoryRequirements[$category] = [
                'required' => $required,
                'completed' => $completed,
                'met' => $met,
            ];

            if (! $met) {
                $allCategoriesMet = false;
            }
        }

        $issues = [];
        foreach ($categoryRequirements as $category => $req) {
            if (! $req['met']) {
                $issues[] = "{$category}: {$req['completed']}/{$req['required']}";
            }
        }

        return [
            'eligible' => $allCategoriesMet,
            'issue' => $allCategoriesMet ? null : 'Incomplete requirements: ' . implode(', ', $issues),
            'category_requirements' => $categoryRequirements,
        ];
    }

    private function checkActiveHolds(Student $student): array
    {
        $activeHolds = AcademicHold::where('student_id', $student->id)
            ->where('status', 'active')
            ->get();

        $blockingHolds = $activeHolds->whereIn('hold_category', [
            'tuition_fees',
            'academic_suspension',
            'disciplinary_action',
            'transcript_hold',
        ]);

        $eligible = $blockingHolds->isEmpty();

        return [
            'eligible' => $eligible,
            'issue' => $eligible ? null : 'Active holds blocking graduation: ' .
                $blockingHolds->pluck('title')->implode(', '),
            'blocking_holds' => $blockingHolds->count(),
        ];
    }

    private function checkResidencyRequirements(Student $student): array
    {
        // Simplified residency check - require at least 50% of credits from this institution
        $totalCredits = $student->academicRecords->sum('credit_hours');
        $transferCredits = $student->academicRecords
            ->where('is_transfer_credit', true)
            ->sum('credit_hours');

        $institutionCredits = $totalCredits - $transferCredits;
        $requiredInstitutionCredits = $totalCredits * 0.5; // 50% residency requirement

        $eligible = $institutionCredits >= $requiredInstitutionCredits;

        return [
            'eligible' => $eligible,
            'issue' => $eligible ? null :
                "Insufficient residency credits: {$institutionCredits}/{$requiredInstitutionCredits}",
            'institution_credits' => $institutionCredits,
            'required_credits' => $requiredInstitutionCredits,
        ];
    }

    private function determineEligibilityStatus(array $checks, array $issues): string
    {
        if (array_reduce($checks, function ($carry, $check) {
            return $carry && $check;
        }, true)) {
            return 'eligible';
        }

        // Determine primary blocking issue
        if (! $checks['gpa_requirements']) {
            return 'gpa_insufficient';
        }

        if (! $checks['no_active_holds']) {
            return 'holds_blocking';
        }

        if (! $checks['credit_requirements']) {
            return 'credits_insufficient';
        }

        return 'pending_requirements';
    }

    private function updateStudentEligibilityStatus(Student $student, bool $eligible, array $issues, array $checks): void
    {
        $eligibilityNote = $eligible ?
            'ELIGIBLE FOR GRADUATION' :
            'Graduation pending: ' . implode('; ', $issues);

        $currentNotes = $student->admission_notes ?? '';
        $student->update([
            'admission_notes' => trim($currentNotes . ' | ' . $eligibilityNote),
        ]);

        if ($eligible) {
            $this->command->info("  🎓 {$student->student_id}: ELIGIBLE FOR GRADUATION!");
        } else {
            $this->command->info("  📋 {$student->student_id}: Pending - " . implode(', ', array_slice($issues, 0, 2)));
        }
    }

    private function displayEligibilityResults(array $results): void
    {
        $total = array_sum($results);

        $this->command->info('✅ Graduation eligibility assessment complete!');
        $this->command->info("  🎓 Eligible for graduation: {$results['eligible']} students");
        $this->command->info("  📋 Pending requirements: {$results['pending_requirements']} students");
        $this->command->info("  📉 GPA insufficient: {$results['gpa_insufficient']} students");
        $this->command->info("  ⚠️ Holds blocking: {$results['holds_blocking']} students");
        $this->command->info("  📚 Credits insufficient: {$results['credits_insufficient']} students");
        $this->command->info("  📊 Total assessed: {$total} students");
    }
}
