<?php

declare(strict_types=1);

namespace Database\Seeders\Timeline;

use App\Models\AcademicHold;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AcademicLeaveSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Simulates students taking academic leave during SPRING2025
     */
    public function run(): void
    {
        $this->command->info('🏥 Processing academic leave requests for SPRING2025...');

        // Get SPRING2025 semester
        $semester = Semester::where('code', 'SPRING2025')->first();

        if (! $semester) {
            throw new \Exception('SPRING2025 semester not found.');
        }

        // Get students registered for SPRING2025
        $registeredStudents = Student::whereHas('courseRegistrations', function ($query) use ($semester) {
            $query->where('semester_id', $semester->id)
                ->where('registration_status', 'registered');
        })->get();

        if ($registeredStudents->isEmpty()) {
            throw new \Exception('No registered students found for SPRING2025.');
        }

        // Simulate 3-5% of students taking academic leave
        $leavePercentage = rand(3, 5);
        $studentsOnLeave = $registeredStudents->random(
            (int) ceil($registeredStudents->count() * $leavePercentage / 100)
        );

        $leaveCount = 0;

        foreach ($studentsOnLeave as $student) {
            $this->processAcademicLeave($student, $semester);
            $leaveCount++;
        }

        $this->command->info("✅ Processed {$leaveCount} academic leave requests!");
    }

    private function processAcademicLeave(Student $student, Semester $semester): void
    {
        $leaveType = $this->determineLeaveType();
        $leaveDate = $this->getLeaveDate($semester);

        // Update student status to inactive for academic leave
        $student->update([
            'status' => 'inactive',
            'admission_notes' => "Academic leave: {$leaveType['reason']} (effective {$leaveDate->format('Y-m-d')})",
        ]);

        // Withdraw from all current registrations
        $this->withdrawFromCourses($student, $semester, $leaveDate, $leaveType);

        // Create academic hold for leave documentation
        $this->createLeaveHold($student, $leaveType, $leaveDate);

        $this->command->info("  📋 {$student->student_id}: {$leaveType['reason']}");
    }

    private function determineLeaveType(): array
    {
        $leaveTypes = [
            [
                'reason' => 'Medical leave',
                'category' => 'medical',
                'documentation_required' => true,
                'max_duration_months' => 12,
                'refund_eligible' => true,
            ],
            [
                'reason' => 'Personal/family emergency',
                'category' => 'personal',
                'documentation_required' => true,
                'max_duration_months' => 6,
                'refund_eligible' => true,
            ],
            [
                'reason' => 'Financial hardship',
                'category' => 'financial',
                'documentation_required' => false,
                'max_duration_months' => 12,
                'refund_eligible' => false,
            ],
            [
                'reason' => 'Military service',
                'category' => 'military',
                'documentation_required' => true,
                'max_duration_months' => 24,
                'refund_eligible' => true,
            ],
            [
                'reason' => 'Academic stress/mental health',
                'category' => 'mental_health',
                'documentation_required' => true,
                'max_duration_months' => 12,
                'refund_eligible' => true,
            ],
        ];

        return $leaveTypes[array_rand($leaveTypes)];
    }

    private function getLeaveDate(Semester $semester): Carbon
    {
        // Leave can happen at various points during the semester
        $semesterStart = $semester->start_date;
        $semesterEnd = $semester->end_date;

        // Most leaves happen in first half of semester
        $midSemester = $semesterStart->copy()->addDays(
            $semesterStart->diffInDays($semesterEnd) / 2
        );

        // 70% chance of leave in first half, 30% in second half
        if (rand(1, 100) <= 70) {
            $daysDiff = (int) $semesterStart->diffInDays($midSemester);

            return $semesterStart->copy()->addDays(rand(7, $daysDiff)); // At least 1 week in
        } else {
            $daysDiff = (int) $midSemester->diffInDays($semesterEnd);

            return $midSemester->copy()->addDays(rand(0, $daysDiff));
        }
    }

    private function withdrawFromCourses(Student $student, Semester $semester, Carbon $leaveDate, array $leaveType): void
    {
        $registrations = CourseRegistration::where('student_id', $student->id)
            ->where('semester_id', $semester->id)
            ->where('registration_status', 'registered')
            ->get();

        foreach ($registrations as $registration) {
            // Determine if withdrawal is with or without penalty based on timing
            $withdrawalType = $this->determineWithdrawalType($leaveDate, $semester);

            $registration->update([
                'registration_status' => 'withdrawn',
                'withdrawal_date' => $leaveDate->toDateString(),
                'notes' => "Withdrawn due to academic leave: {$leaveType['reason']}. {$withdrawalType['note']}",
            ]);

            // Update course offering enrollment
            $registration->courseOffering->decrement('current_enrollment');
        }
    }

    private function determineWithdrawalType(Carbon $leaveDate, Semester $semester): array
    {
        $semesterStart = $semester->start_date;
        $weeksIntoSemester = $semesterStart->diffInWeeks($leaveDate);

        if ($weeksIntoSemester <= 2) {
            return [
                'type' => 'full_refund',
                'note' => 'Full refund eligible (within 2 weeks)',
                'refund_percentage' => 100,
            ];
        } elseif ($weeksIntoSemester <= 6) {
            return [
                'type' => 'partial_refund',
                'note' => 'Partial refund eligible (within 6 weeks)',
                'refund_percentage' => 50,
            ];
        } else {
            return [
                'type' => 'no_refund',
                'note' => 'No refund (after 6 weeks)',
                'refund_percentage' => 0,
            ];
        }
    }

    private function createLeaveHold(Student $student, array $leaveType, Carbon $leaveDate): void
    {
        $expectedReturnDate = $leaveDate->copy()->addMonths($leaveType['max_duration_months']);

        AcademicHold::create([
            'student_id' => $student->id,
            'hold_type' => 'administrative',
            'hold_category' => 'registration',
            'title' => 'Academic Leave Documentation',
            'description' => "Student on {$leaveType['reason']} leave. " .
                ($leaveType['documentation_required'] ? 'Documentation required for return. ' : '') .
                "Expected return: {$expectedReturnDate->format('Y-m-d')}",
            'amount' => null,
            'priority' => 'medium',
            'status' => 'active',
            'placed_date' => $leaveDate,
            'due_date' => $expectedReturnDate,
            'resolved_date' => null,
            'placed_by_user_id' => 1,
            'resolved_by_user_id' => null,
            'resolution_notes' => null,
        ]);
    }
}
