<?php

declare(strict_types=1);

namespace Database\Seeders\Timeline;

use App\Models\Student;
use App\Models\AcademicHold;
use App\Models\Semester;
use App\Models\CourseRegistration;
use App\Models\CourseOffering;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ReEnrollmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Processes re-enrollment for students returning from leave or suspension
     */
    public function run(): void
    {
        $this->command->info('🔄 Processing student re-enrollments...');

        // Get students who are on leave or suspended and might return
        $returningStudents = Student::whereIn('status', ['on_leave', 'suspended'])
            ->whereHas('academicHolds', function ($query) {
                $query->where('status', 'active')
                    ->whereIn('hold_category', ['academic_leave', 'academic_suspension']);
            })
            ->get();

        if ($returningStudents->isEmpty()) {
            $this->command->info('⚠️ No students on leave or suspension found. Creating sample scenarios...');
            $returningStudents = $this->createSampleReturningStudents();
        }

        $reEnrollmentStats = [
            'successful_returns' => 0,
            'conditional_returns' => 0,
            'denied_returns' => 0,
            'pending_review' => 0,
        ];

        foreach ($returningStudents as $student) {
            $result = $this->processReEnrollment($student);
            $reEnrollmentStats[$result]++;
        }

        $this->displayReEnrollmentStatistics($reEnrollmentStats);
    }

    private function processReEnrollment(Student $student): string
    {
        $reEnrollmentType = $this->determineReEnrollmentOutcome($student);

        switch ($reEnrollmentType['outcome']) {
            case 'approved':
                return $this->processSuccessfulReturn($student, $reEnrollmentType);

            case 'conditional':
                return $this->processConditionalReturn($student, $reEnrollmentType);

            case 'denied':
                return $this->processDeniedReturn($student, $reEnrollmentType);

            case 'pending':
            default:
                return $this->processPendingReturn($student, $reEnrollmentType);
        }
    }

    private function determineReEnrollmentOutcome(Student $student): array
    {
        // Get student's academic history
        $latestGPA = $student->gpaCalculations()
            ->where('calculation_type', 'cumulative')
            ->latest('calculated_at')
            ->first();

        $gpa = $latestGPA ? $latestGPA->gpa : 0.0;

        // Get reason for original leave/suspension
        $currentStatus = $student->status;
        $leaveReason = $this->extractLeaveReason($student);

        // Determine outcome based on various factors
        if ($currentStatus === 'suspended' && $gpa < 1.0) {
            // Academic suspension cases
            $outcomes = [
                ['outcome' => 'denied', 'probability' => 40],
                ['outcome' => 'conditional', 'probability' => 45],
                ['outcome' => 'pending', 'probability' => 15],
            ];
        } elseif ($currentStatus === 'on_leave') {
            // Leave cases (medical, personal, etc.)
            $outcomes = [
                ['outcome' => 'approved', 'probability' => 60],
                ['outcome' => 'conditional', 'probability' => 25],
                ['outcome' => 'pending', 'probability' => 15],
            ];
        } else {
            // Default case
            $outcomes = [
                ['outcome' => 'approved', 'probability' => 50],
                ['outcome' => 'conditional', 'probability' => 30],
                ['outcome' => 'pending', 'probability' => 20],
            ];
        }

        // Select outcome based on probability
        $totalProbability = array_sum(array_column($outcomes, 'probability'));
        $random = rand(1, $totalProbability);
        $cumulative = 0;

        foreach ($outcomes as $outcome) {
            $cumulative += $outcome['probability'];
            if ($random <= $cumulative) {
                return [
                    'outcome' => $outcome['outcome'],
                    'leave_reason' => $leaveReason,
                    'previous_gpa' => $gpa,
                ];
            }
        }

        return ['outcome' => 'pending', 'leave_reason' => $leaveReason, 'previous_gpa' => $gpa];
    }

    private function processSuccessfulReturn(Student $student, array $reEnrollmentType): string
    {
        // Update student status
        $student->update([
            'status' => 'active',
            'admission_notes' => ($student->admission_notes ?? '') .
                " | Re-enrolled: " . now()->format('Y-m-d') .
                " (Approved return from {$reEnrollmentType['leave_reason']})"
        ]);

        // Resolve academic holds
        $this->resolveAcademicHolds($student, 'Approved for re-enrollment');

        // Register for current semester if possible
        $this->registerForCurrentSemester($student);

        $this->command->info("  ✅ {$student->student_code}: Successful return (was {$reEnrollmentType['leave_reason']})");

        return 'successful_returns';
    }

    private function processConditionalReturn(Student $student, array $reEnrollmentType): string
    {
        // Update student status with conditions
        $conditions = $this->generateReturnConditions($reEnrollmentType);

        $student->update([
            'status' => 'active',
            'admission_notes' => ($student->admission_notes ?? '') .
                " | Conditional re-enrollment: " . now()->format('Y-m-d') .
                " (Conditions: {$conditions})"
        ]);

        // Create conditional enrollment hold
        $this->createConditionalHold($student, $conditions);

        // Resolve original holds
        $this->resolveAcademicHolds($student, 'Conditional re-enrollment approved');

        $this->command->info("  ⚠️ {$student->student_code}: Conditional return ({$conditions})");

        return 'conditional_returns';
    }

    private function processDeniedReturn(Student $student, array $reEnrollmentType): string
    {
        // Keep student in current status, add denial note
        $student->update([
            'admission_notes' => ($student->admission_notes ?? '') .
                " | Re-enrollment denied: " . now()->format('Y-m-d') .
                " (Reason: Insufficient academic progress)"
        ]);

        // Create denial hold
        $this->createDenialHold($student);

        $this->command->info("  ❌ {$student->student_code}: Re-enrollment denied");

        return 'denied_returns';
    }

    private function processPendingReturn(Student $student, array $reEnrollmentType): string
    {
        // Add pending review note
        $student->update([
            'admission_notes' => ($student->admission_notes ?? '') .
                " | Re-enrollment under review: " . now()->format('Y-m-d') .
                " (Pending committee decision)"
        ]);

        // Create review hold
        $this->createReviewHold($student);

        $this->command->info("  📋 {$student->student_code}: Under review");

        return 'pending_review';
    }

    private function extractLeaveReason(Student $student): string
    {
        $notes = $student->admission_notes ?? '';

        if (str_contains($notes, 'Medical leave')) {
            return 'medical leave';
        } elseif (str_contains($notes, 'Personal')) {
            return 'personal leave';
        } elseif (str_contains($notes, 'Financial')) {
            return 'financial hardship';
        } elseif (str_contains($notes, 'Academic suspension')) {
            return 'academic suspension';
        } else {
            return 'leave of absence';
        }
    }

    private function generateReturnConditions(array $reEnrollmentType): string
    {
        $conditions = [];

        if ($reEnrollmentType['previous_gpa'] < 2.0) {
            $conditions[] = 'Academic probation';
            $conditions[] = 'Mandatory tutoring';
        }

        if ($reEnrollmentType['leave_reason'] === 'medical leave') {
            $conditions[] = 'Medical clearance required';
        }

        if ($reEnrollmentType['leave_reason'] === 'academic suspension') {
            $conditions[] = 'Reduced course load';
            $conditions[] = 'Academic counseling';
        }

        $conditions[] = 'Regular progress monitoring';

        return implode(', ', $conditions);
    }

    private function resolveAcademicHolds(Student $student, string $resolution): void
    {
        AcademicHold::where('student_code', $student->id)
            ->where('status', 'active')
            ->whereIn('hold_category', ['academic_leave', 'academic_suspension'])
            ->update([
                'status' => 'resolved',
                'resolved_date' => now(),
                'resolved_by_user_id' => 1,
                'resolution_notes' => $resolution,
            ]);
    }

    private function createConditionalHold(Student $student, string $conditions): void
    {
        AcademicHold::create([
            'student_code' => $student->id,
            'hold_type' => 'academic',
            'hold_category' => 'registration',
            'title' => 'Conditional Re-enrollment',
            'description' => "Student re-enrolled under conditions: {$conditions}",
            'amount' => null,
            'priority' => 'medium',
            'status' => 'active',
            'placed_date' => now(),
            'due_date' => now()->addMonths(6), // Review in 6 months
            'resolved_date' => null,
            'placed_by_user_id' => 1,
            'resolved_by_user_id' => null,
            'resolution_notes' => null,
        ]);
    }

    private function createDenialHold(Student $student): void
    {
        AcademicHold::create([
            'student_code' => $student->id,
            'hold_type' => 'administrative',
            'hold_category' => 'registration',
            'title' => 'Re-enrollment Denied',
            'description' => 'Re-enrollment application denied. Appeal process available.',
            'amount' => null,
            'priority' => 'high',
            'status' => 'active',
            'placed_date' => now(),
            'due_date' => null,
            'resolved_date' => null,
            'placed_by_user_id' => 1,
            'resolved_by_user_id' => null,
            'resolution_notes' => null,
        ]);
    }

    private function createReviewHold(Student $student): void
    {
        AcademicHold::create([
            'student_code' => $student->id,
            'hold_type' => 'administrative',
            'hold_category' => 'registration',
            'title' => 'Re-enrollment Under Review',
            'description' => 'Re-enrollment application under committee review.',
            'amount' => null,
            'priority' => 'medium',
            'status' => 'active',
            'placed_date' => now(),
            'due_date' => now()->addDays(30),
            'resolved_date' => null,
            'placed_by_user_id' => 1,
            'resolved_by_user_id' => null,
            'resolution_notes' => null,
        ]);
    }

    private function registerForCurrentSemester(Student $student): void
    {
        // Get current semester
        $currentSemester = Semester::where('is_active', true)->first();

        if (!$currentSemester) {
            return;
        }

        // Register for 1-2 courses (reduced load for returning students)
        $availableOfferings = CourseOffering::where('semester_id', $currentSemester->id)
            ->where('is_active', true)
            ->where('enrollment_status', 'open')
            ->take(2)
            ->get();

        foreach ($availableOfferings as $offering) {
            CourseRegistration::create([
                'student_code' => $student->id,
                'course_offering_id' => $offering->id,
                'semester_id' => $currentSemester->id,
                'registration_status' => 'registered',
                'registration_date' => now(),
                'registration_method' => 'admin_override',
                'credit_hours' => $offering->unit->credit_points,
                'attempt_number' => 1,
                'is_retake' => false,
                'notes' => 'Re-enrollment registration',
            ]);

            $offering->increment('current_enrollment');
        }
    }

    private function createSampleReturningStudents()
    {
        // For demonstration, mark some active students as on leave
        $students = Student::where('status', 'active')->take(5)->get();

        foreach ($students as $student) {
            $student->update(['status' => 'inactive']);

            AcademicHold::create([
                'student_code' => $student->id,
                'hold_type' => 'administrative',
                'hold_category' => 'registration',
                'title' => 'Academic Leave',
                'description' => 'Student on academic leave',
                'status' => 'active',
                'placed_date' => now()->subMonths(6),
                'placed_by_user_id' => 1,
            ]);
        }

        return $students;
    }

    private function displayReEnrollmentStatistics(array $stats): void
    {
        $total = array_sum($stats);

        $this->command->info("✅ Re-enrollment processing complete!");
        $this->command->info("  ✅ Successful returns: {$stats['successful_returns']} students");
        $this->command->info("  ⚠️ Conditional returns: {$stats['conditional_returns']} students");
        $this->command->info("  ❌ Denied returns: {$stats['denied_returns']} students");
        $this->command->info("  📋 Pending review: {$stats['pending_review']} students");
        $this->command->info("  📊 Total processed: {$total} students");
    }
}
