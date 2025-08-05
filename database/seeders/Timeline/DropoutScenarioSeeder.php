<?php

declare(strict_types=1);

namespace Database\Seeders\Timeline;

use App\Models\Student;
use App\Models\CourseRegistration;
use App\Models\AcademicHold;
use App\Models\Semester;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class DropoutScenarioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Simulates various dropout scenarios for students
     */
    public function run(): void
    {
        $this->command->info('📉 Creating dropout scenarios...');

        // Get active students who might drop out
        $activeStudents = Student::where('status', 'active')
            ->whereHas('academicRecords')
            ->get();

        if ($activeStudents->isEmpty()) {
            throw new \Exception('No active students found for dropout scenarios.');
        }

        // Simulate 5-8% dropout rate
        $dropoutPercentage = rand(5, 8);
        $dropoutCount = (int) ceil($activeStudents->count() * $dropoutPercentage / 100);

        // Select students for dropout scenarios
        $dropoutStudents = $activeStudents->random(min($dropoutCount, $activeStudents->count()));

        $dropoutStats = [
            'academic_failure' => 0,
            'financial_hardship' => 0,
            'personal_reasons' => 0,
            'transfer_out' => 0,
            'medical_withdrawal' => 0,
        ];

        foreach ($dropoutStudents as $student) {
            $dropoutType = $this->determineDropoutType($student);
            $this->processDropout($student, $dropoutType);
            $dropoutStats[$dropoutType['category']]++;
        }

        $this->displayDropoutStatistics($dropoutStats, $dropoutCount);
    }

    private function determineDropoutType(Student $student): array
    {
        // Get student's academic performance to influence dropout type
        $latestGPA = $student->gpaCalculations()
            ->where('calculation_type', 'cumulative')
            ->latest('calculated_at')
            ->first();

        $gpa = $latestGPA ? $latestGPA->gpa : 2.5;

        // Determine dropout type based on GPA and other factors
        $dropoutTypes = [
            [
                'category' => 'academic_failure',
                'reason' => 'Academic dismissal due to poor performance',
                'probability' => $gpa < 1.5 ? 40 : 10,
                'voluntary' => false,
                'refund_eligible' => false,
            ],
            [
                'category' => 'financial_hardship',
                'reason' => 'Financial difficulties - unable to continue',
                'probability' => 25,
                'voluntary' => true,
                'refund_eligible' => true,
            ],
            [
                'category' => 'personal_reasons',
                'reason' => 'Personal/family circumstances',
                'probability' => 20,
                'voluntary' => true,
                'refund_eligible' => true,
            ],
            [
                'category' => 'transfer_out',
                'reason' => 'Transfer to another institution',
                'probability' => $gpa > 3.0 ? 20 : 10,
                'voluntary' => true,
                'refund_eligible' => false,
            ],
            [
                'category' => 'medical_withdrawal',
                'reason' => 'Medical withdrawal',
                'probability' => 10,
                'voluntary' => false,
                'refund_eligible' => true,
            ],
        ];

        // Select dropout type based on probabilities
        $totalProbability = array_sum(array_column($dropoutTypes, 'probability'));
        $random = rand(1, $totalProbability);
        $cumulative = 0;

        foreach ($dropoutTypes as $type) {
            $cumulative += $type['probability'];
            if ($random <= $cumulative) {
                return $type;
            }
        }

        // Fallback
        return $dropoutTypes[1]; // Financial hardship
    }

    private function processDropout(Student $student, array $dropoutType): void
    {
        $dropoutDate = $this->getDropoutDate();

        // Update student status to inactive (withdrawn is not a valid enum value)
        $student->update([
            'status' => 'inactive',
            'admission_notes' => ($student->admission_notes ?? '') .
                " | Withdrawn: {$dropoutType['reason']} (Date: {$dropoutDate->format('Y-m-d')})"
        ]);

        // Withdraw from current registrations
        $this->withdrawFromCurrentCourses($student, $dropoutDate, $dropoutType);

        // Create administrative hold
        $this->createDropoutHold($student, $dropoutType, $dropoutDate);

        // Process any refunds if applicable
        if ($dropoutType['refund_eligible']) {
            $this->processRefund($student, $dropoutDate, $dropoutType);
        }

        $this->logDropout($student, $dropoutType);
    }

    private function getDropoutDate(): Carbon
    {
        // Dropouts can happen throughout the academic year
        $baseDate = now()->subMonths(rand(1, 12));
        return $baseDate->addDays(rand(0, 30));
    }

    private function withdrawFromCurrentCourses(Student $student, Carbon $dropoutDate, array $dropoutType): void
    {
        // Get current semester registrations
        $currentRegistrations = CourseRegistration::where('student_code', $student->id)
            ->where('registration_status', 'registered')
            ->whereHas('semester', function ($query) use ($dropoutDate) {
                $query->where('start_date', '<=', $dropoutDate)
                    ->where('end_date', '>=', $dropoutDate);
            })
            ->get();

        foreach ($currentRegistrations as $registration) {
            $registration->update([
                'registration_status' => 'withdrawn',
                'withdrawal_date' => $dropoutDate->toDateString(),
                'notes' => "Withdrawn due to: {$dropoutType['reason']}",
            ]);

            // Update course offering enrollment
            $registration->courseOffering->decrement('current_enrollment');
        }
    }

    private function createDropoutHold(Student $student, array $dropoutType, Carbon $dropoutDate): void
    {
        AcademicHold::create([
            'student_code' => $student->id,
            'hold_type' => 'administrative',
            'hold_category' => 'all', // Use 'all' for withdrawal processing holds
            'title' => 'Student Withdrawal Processing',
            'description' => "Student withdrawn: {$dropoutType['reason']}. " .
                ($dropoutType['voluntary'] ? 'Voluntary withdrawal.' : 'Involuntary withdrawal.') .
                ' Exit interview and documentation required.',
            'amount' => null,
            'priority' => 'medium',
            'status' => 'active',
            'placed_date' => $dropoutDate,
            'due_date' => $dropoutDate->copy()->addDays(30),
            'resolved_date' => null,
            'placed_by_user_id' => 1,
            'resolved_by_user_id' => null,
            'resolution_notes' => null,
        ]);
    }

    private function processRefund(Student $student, Carbon $dropoutDate, array $dropoutType): void
    {
        // Calculate refund based on withdrawal timing
        $refundPercentage = $this->calculateRefundPercentage($dropoutDate);

        if ($refundPercentage > 0) {
            // Create refund hold/record
            AcademicHold::create([
                'student_code' => $student->id,
                'hold_type' => 'financial',
                'hold_category' => 'all', // Use 'all' for refund processing holds
                'title' => 'Tuition Refund Processing',
                'description' => "Refund processing: {$refundPercentage}% refund eligible due to " .
                    "withdrawal within refund period. Reason: {$dropoutType['reason']}",
                'amount' => - ($refundPercentage * 10), // Negative amount indicates refund
                'priority' => 'low',
                'status' => 'active',
                'placed_date' => $dropoutDate,
                'due_date' => $dropoutDate->copy()->addDays(45),
                'resolved_date' => null,
                'placed_by_user_id' => 1,
                'resolved_by_user_id' => null,
                'resolution_notes' => null,
            ]);
        }
    }

    private function calculateRefundPercentage(Carbon $dropoutDate): int
    {
        // Get current semester
        $currentSemester = Semester::where('start_date', '<=', $dropoutDate)
            ->where('end_date', '>=', $dropoutDate)
            ->first();

        if (!$currentSemester) {
            return 0;
        }

        $semesterStart = $currentSemester->start_date;
        $weeksIntoSemester = $semesterStart->diffInWeeks($dropoutDate);

        // Refund schedule
        if ($weeksIntoSemester <= 1) {
            return 100; // Full refund
        } elseif ($weeksIntoSemester <= 2) {
            return 75;  // 75% refund
        } elseif ($weeksIntoSemester <= 4) {
            return 50;  // 50% refund
        } elseif ($weeksIntoSemester <= 6) {
            return 25;  // 25% refund
        } else {
            return 0;   // No refund
        }
    }

    private function logDropout(Student $student, array $dropoutType): void
    {
        $voluntaryStatus = $dropoutType['voluntary'] ? 'Voluntary' : 'Involuntary';
        $this->command->info("  📉 {$student->student_code}: {$dropoutType['category']} ({$voluntaryStatus})");
    }

    private function displayDropoutStatistics(array $stats, int $totalDropouts): void
    {
        $this->command->info("✅ Dropout scenarios created!");
        $this->command->info("  📊 Total dropouts: {$totalDropouts} students");
        $this->command->info("  📉 Academic failure: {$stats['academic_failure']} students");
        $this->command->info("  💰 Financial hardship: {$stats['financial_hardship']} students");
        $this->command->info("  👨‍👩‍👧‍👦 Personal reasons: {$stats['personal_reasons']} students");
        $this->command->info("  🔄 Transfer out: {$stats['transfer_out']} students");
        $this->command->info("  🏥 Medical withdrawal: {$stats['medical_withdrawal']} students");
    }
}
