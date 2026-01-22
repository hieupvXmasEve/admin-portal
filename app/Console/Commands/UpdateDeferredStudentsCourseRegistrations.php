<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Student;
use Illuminate\Console\Command;

class UpdateDeferredStudentsCourseRegistrations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'course-registrations:update-deferred
                            {--semester=2 : Semester ID to update}
                            {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update course registrations to "defer" status for deferred students in a specific semester';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $semesterId = (int) $this->option('semester');
        $isDryRun = $this->option('dry-run');

        $this->info('🔄 Updating course registrations for deferred students...');
        $this->newLine();

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Find all students with status = 'deferred'
        $students = Student::where('status', 'deferred')
            ->whereHas('courseRegistrations', function ($query) use ($semesterId) {
                $query->where('semester_id', $semesterId);
            })
            ->with(['courseRegistrations' => function ($query) use ($semesterId) {
                $query->where('semester_id', $semesterId)
                    ->with(['student', 'courseOffering']);
            }])
            ->get();

        if ($students->isEmpty()) {
            $this->warn("No deferred students found with course registrations in semester {$semesterId}.");

            return self::SUCCESS;
        }

        $this->info("Found {$students->count()} deferred student(s) with course registrations in semester {$semesterId}");
        $this->newLine();

        // Collect all course registrations to update
        $registrationsToUpdate = collect();
        foreach ($students as $student) {
            $registrationsToUpdate = $registrationsToUpdate->merge($student->courseRegistrations);
        }

        $totalRegistrations = $registrationsToUpdate->count();

        if ($totalRegistrations === 0) {
            $this->warn('No course registrations found to update.');

            return self::SUCCESS;
        }

        $this->info("Total course registrations to update: {$totalRegistrations}");
        $this->newLine();

        if ($isDryRun) {
            // Show sample data
            $this->info('Sample registrations that would be updated (first 50):');
            $samples = $registrationsToUpdate->take(50);

            $sampleData = $samples->map(function ($registration) {
                return [
                    'ID' => $registration->id,
                    'Student' => $registration->student->student_id ?? 'N/A',
                    'Student Name' => $registration->student->full_name ?? 'N/A',
                    'Course' => $registration->courseOffering?->course_code ?? 'N/A',
                    'Current Status' => $registration->registration_status,
                    'New Status' => 'defer',
                ];
            })->toArray();

            $this->table(
                ['ID', 'Student', 'Student Name', 'Course', 'Current Status', 'New Status'],
                $sampleData
            );

            $this->newLine();
            $this->warn("DRY RUN: Would update {$totalRegistrations} course registrations to 'defer' status.");

            return self::SUCCESS;
        }

        // Ask for confirmation
        if (! $this->confirm("Do you want to update {$totalRegistrations} course registrations to 'defer' status?")) {
            $this->warn('Operation cancelled.');

            return self::SUCCESS;
        }

        // Perform update
        $this->info('Updating course registrations...');
        $progressBar = $this->output->createProgressBar($totalRegistrations);
        $progressBar->setFormat('verbose');
        $progressBar->start();

        $updated = 0;
        $errors = 0;

        // Process in chunks to avoid memory issues
        $registrationsToUpdate->chunk(100)->each(function ($chunk) use (&$updated, &$errors, $progressBar) {
            foreach ($chunk as $registration) {
                try {
                    $registration->update(['registration_status' => 'defer']);
                    $updated++;
                    $progressBar->setMessage("Updated: {$registration->student->student_id} - {$registration->courseOffering?->course_code}");
                } catch (\Exception $e) {
                    $errors++;
                    $this->newLine();
                    $this->error("Failed to update registration ID {$registration->id}: {$e->getMessage()}");
                }
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine(2);

        // Display summary
        $this->info("✓ Successfully updated {$updated} course registrations");
        if ($errors > 0) {
            $this->error("✗ Failed to update {$errors} course registrations");
        }

        $this->newLine();
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Found', $totalRegistrations],
                ['Updated', $updated],
                ['Errors', $errors],
            ]
        );

        return self::SUCCESS;
    }
}
