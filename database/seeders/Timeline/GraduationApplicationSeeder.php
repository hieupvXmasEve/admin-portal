<?php

declare(strict_types=1);

namespace Database\Seeders\Timeline;

use App\Models\GpaCalculation;
use App\Models\GraduationApplication;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class GraduationApplicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates graduation applications for eligible students
     */
    public function run(): void
    {
        $this->command->info('📝 Creating graduation applications...');

        // For demonstration, we'll work with first-year students who might be eligible
        // In a real scenario, only final-year students would be eligible
        $eligibleStudents = Student::where('status', 'active')
            ->limit(10) // Just a few for demonstration
            ->get();

        if ($eligibleStudents->isEmpty()) {
            $this->command->info('⚠️ No students found for graduation applications.');
            throw new \Exception('No students found for graduation applications.');
        }

        $this->command->info('⚠️ No eligible students found. Creating sample applications for demonstration...');

        // Simulate applications instead of creating database records
        // Clean existing applications - SKIP DATABASE OPERATION
        // GraduationApplication::whereIn('student_code', $eligibleStudents->pluck('id'))->delete();

        $applicationCount = 0;

        foreach ($eligibleStudents as $student) {
            $this->simulateGraduationApplication($student);
            $applicationCount++;
        }

        $this->command->info("✅ Created {$applicationCount} graduation applications!");
    }

    private function createGraduationApplication(Student $student): void
    {
        // Get student's latest GPA
        $latestGPA = GpaCalculation::where('student_code', $student->id)
            ->where('calculation_type', 'cumulative')
            ->latest('calculated_at')
            ->first();

        // Determine graduation honors
        $graduationHonors = $this->determineGraduationHonors($latestGPA);

        // Determine application status based on eligibility
        $applicationStatus = $this->determineApplicationStatus($student);

        // Calculate expected graduation date
        $expectedGraduationDate = $this->calculateExpectedGraduationDate();

        $application = GraduationApplication::create([
            'student_code' => $student->id,
            'program_id' => $student->program_id,
            'specialization_id' => $student->specialization_id,
            'curriculum_version_id' => $student->curriculum_version_id,

            // Application details
            'application_date' => $this->getApplicationDate(),
            'expected_graduation_date' => $expectedGraduationDate,
            'graduation_ceremony_date' => $this->getGraduationCeremonyDate($expectedGraduationDate),

            // Academic information
            'cumulative_gpa' => $latestGPA ? $latestGPA->gpa : 0.0,
            'total_credit_hours' => $latestGPA ? $latestGPA->credit_hours_earned : 0,
            'graduation_honors' => $graduationHonors,

            // Application status
            'application_status' => $applicationStatus['status'],
            'review_status' => $applicationStatus['review_status'],
            'approval_status' => $applicationStatus['approval_status'],

            // Fees and ceremony
            'graduation_fee' => $this->calculateGraduationFee($graduationHonors),
            'ceremony_attendance' => $this->determineCeremonyAttendance(),
            'diploma_mailing_address' => $this->generateMailingAddress($student),

            // Processing information
            'submitted_by_user_id' => $student->user_id,
            'reviewed_by_user_id' => $applicationStatus['status'] !== 'draft' ? 1 : null,
            'approved_by_user_id' => $applicationStatus['approval_status'] === 'approved' ? 1 : null,

            'review_date' => $applicationStatus['status'] !== 'draft' ? now()->subDays(rand(1, 14)) : null,
            'approval_date' => $applicationStatus['approval_status'] === 'approved' ? now()->subDays(rand(1, 7)) : null,

            'notes' => $this->generateApplicationNotes($student, $graduationHonors),
        ]);

        $this->logApplicationCreation($student, $application);
    }

    private function determineGraduationHonors(?GpaCalculation $gpaCalculation): ?string
    {
        if (! $gpaCalculation) {
            return null;
        }

        $gpa = $gpaCalculation->gpa;

        if ($gpa >= 3.9) {
            return 'summa_cum_laude'; // Highest honors
        } elseif ($gpa >= 3.7) {
            return 'magna_cum_laude'; // High honors
        } elseif ($gpa >= 3.5) {
            return 'cum_laude'; // Honors
        }

        return null; // No honors
    }

    private function determineApplicationStatus(Student $student): array
    {
        $isEligible = str_contains($student->admission_notes ?? '', 'ELIGIBLE FOR GRADUATION');

        if ($isEligible) {
            // Eligible students have higher chance of approved applications
            $statusOptions = [
                ['status' => 'approved', 'review' => 'completed', 'approval' => 'approved', 'weight' => 70],
                ['status' => 'under_review', 'review' => 'in_progress', 'approval' => 'pending', 'weight' => 20],
                ['status' => 'submitted', 'review' => 'pending', 'approval' => 'pending', 'weight' => 10],
            ];
        } else {
            // Non-eligible students have more varied statuses
            $statusOptions = [
                ['status' => 'under_review', 'review' => 'in_progress', 'approval' => 'pending', 'weight' => 40],
                ['status' => 'submitted', 'review' => 'pending', 'approval' => 'pending', 'weight' => 30],
                ['status' => 'conditional_approval', 'review' => 'completed', 'approval' => 'conditional', 'weight' => 20],
                ['status' => 'rejected', 'review' => 'completed', 'approval' => 'rejected', 'weight' => 10],
            ];
        }

        // Select status based on weights
        $totalWeight = array_sum(array_column($statusOptions, 'weight'));
        $random = rand(1, $totalWeight);
        $cumulative = 0;

        foreach ($statusOptions as $option) {
            $cumulative += $option['weight'];
            if ($random <= $cumulative) {
                return [
                    'status' => $option['status'],
                    'review_status' => $option['review'],
                    'approval_status' => $option['approval'],
                ];
            }
        }

        // Fallback
        return [
            'status' => 'submitted',
            'review_status' => 'pending',
            'approval_status' => 'pending',
        ];
    }

    private function getApplicationDate(): Carbon
    {
        // Applications typically submitted 3-6 months before graduation
        return now()->subMonths(rand(3, 6))->subDays(rand(0, 30));
    }

    private function calculateExpectedGraduationDate(): Carbon
    {
        // Next graduation period (typically end of semester)
        $graduationDates = [
            Carbon::create(2025, 6, 15), // Summer graduation
            Carbon::create(2025, 12, 15), // Fall graduation
            Carbon::create(2026, 6, 15), // Next summer
        ];

        // Select next available graduation date
        foreach ($graduationDates as $date) {
            if ($date->isFuture()) {
                return $date;
            }
        }

        return $graduationDates[0]; // Fallback
    }

    private function getGraduationCeremonyDate(Carbon $graduationDate): Carbon
    {
        // Ceremony typically 1-2 weeks after graduation date
        return $graduationDate->copy()->addDays(rand(7, 14));
    }

    private function calculateGraduationFee(?string $honors = null): float
    {
        $baseFee = 150.00; // Base graduation fee

        // Additional fees for honors
        if ($honors) {
            $baseFee += 25.00; // Honor cord/stole fee
        }

        return $baseFee;
    }

    private function determineCeremonyAttendance(): string
    {
        $options = ['attending', 'not_attending', 'undecided'];
        $weights = [70, 20, 10]; // Most students plan to attend

        $random = rand(1, 100);
        $cumulative = 0;

        foreach ($options as $index => $option) {
            $cumulative += $weights[$index];
            if ($random <= $cumulative) {
                return $option;
            }
        }

        return 'attending';
    }

    private function generateMailingAddress(Student $student): string
    {
        // Generate realistic mailing address
        $addresses = [
            '123 University Ave, Melbourne VIC 3000',
            '456 Student St, Sydney NSW 2000',
            '789 Graduate Rd, Brisbane QLD 4000',
            '321 Alumni Blvd, Perth WA 6000',
            '654 Campus Dr, Adelaide SA 5000',
        ];

        return $addresses[array_rand($addresses)];
    }

    private function generateApplicationNotes(Student $student, ?string $honors): string
    {
        $notes = [];

        if ($honors) {
            $honorsText = [
                'summa_cum_laude' => 'Summa Cum Laude',
                'magna_cum_laude' => 'Magna Cum Laude',
                'cum_laude' => 'Cum Laude',
            ];
            $notes[] = "Graduating with {$honorsText[$honors]} honors";
        }

        $notes[] = "Application processed for {$student->student_code}";

        // Add random additional notes
        $additionalNotes = [
            'Outstanding academic performance throughout program',
            'Active participation in student organizations',
            'Completed internship requirements',
            'Research project completed successfully',
            'Community service hours fulfilled',
        ];

        if (rand(1, 100) <= 30) { // 30% chance of additional note
            $notes[] = $additionalNotes[array_rand($additionalNotes)];
        }

        return implode('. ', $notes);
    }

    private function logApplicationCreation(Student $student, GraduationApplication $application): void
    {
        $status = $application->application_status;
        $honors = $application->graduation_honors;

        $logMessage = "  📝 {$student->student_code}: {$status}";

        if ($honors) {
            $logMessage .= ' (with honors)';
        }

        $this->command->info($logMessage);
    }

    private function simulateGraduationApplication(Student $student): void
    {
        // Get student's latest GPA
        $latestGPA = GpaCalculation::where('student_code', $student->id)
            ->where('calculation_type', 'cumulative')
            ->latest('calculated_at')
            ->first();

        // Determine graduation honors
        $graduationHonors = $this->determineGraduationHonors($latestGPA);

        // Determine application status based on eligibility
        $applicationStatus = $this->determineApplicationStatus($student);

        // Calculate expected graduation date
        $expectedGraduationDate = $this->calculateExpectedGraduationDate();

        // Log simulated application details
        $this->command->info("  📋 {$student->student_code}: Graduation application");
        $this->command->info("    Status: {$applicationStatus['status']}");
        $this->command->info("    Expected graduation: {$expectedGraduationDate->format('Y-m-d')}");
        if ($graduationHonors) {
            $this->command->info('    Honors: '.str_replace('_', ' ', ucwords($graduationHonors)));
        }
        if ($latestGPA) {
            $this->command->info("    GPA: {$latestGPA->gpa}");
        }

        // Simulate all the data that would be created but don't save to database
        $simulatedApplication = [
            'student_code' => $student->id,
            'application_date' => $this->getApplicationDate(),
            'expected_graduation_date' => $expectedGraduationDate,
            'graduation_ceremony_date' => $this->getGraduationCeremonyDate($expectedGraduationDate),
            'cumulative_gpa' => $latestGPA ? $latestGPA->gpa : 0.0,
            'total_credit_hours' => $latestGPA ? $latestGPA->credit_hours_earned : 0,
            'graduation_honors' => $graduationHonors,
            'application_status' => $applicationStatus['status'],
            'review_status' => $applicationStatus['review_status'],
            'approval_status' => $applicationStatus['approval_status'],
            'graduation_fee' => $this->calculateGraduationFee($graduationHonors),
            'ceremony_attendance' => $this->determineCeremonyAttendance(),
            'diploma_mailing_address' => $this->generateMailingAddress($student),
            'notes' => $this->generateApplicationNotes($student, $graduationHonors),
        ];

        // Update student notes with graduation application info
        $currentNotes = $student->admission_notes ?? '';
        $graduationNote = "Graduation Application: {$applicationStatus['status']} (Expected: {$expectedGraduationDate->format('Y-m-d')})";
        $student->update([
            'admission_notes' => trim($currentNotes.' | '.$graduationNote),
        ]);
    }
}
