<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Support\ClassSessionService;
use Illuminate\Console\Command;

class GenerateAttendanceForCourseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:generate-for-course 
                            {course_offering_id : The ID of the course offering}
                            {--dry-run : Preview what would be generated without making changes}
                            {--force : Generate for all sessions even if some already have attendance}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate attendance records for all class sessions in a course offering';

    /**
     * Execute the console command.
     */
    public function handle(ClassSessionService $classSessionService): int
    {
        $courseOfferingId = (int) $this->argument('course_offering_id');
        $isDryRun = $this->option('dry-run');
        $isForce = $this->option('force');

        // Validate and load course offering
        $courseOffering = CourseOffering::with([
            'unit:id,code,name',
            'semester:id,name,code',
            'classSessions' => function ($query) {
                $query->orderBy('sequence_number')->orderBy('session_date');
            },
            'courseRegistrations' => function ($query) {
                $query->where('registration_status', 'confirmed');
            },
        ])->find($courseOfferingId);

        if (! $courseOffering) {
            $this->error("❌ Course offering with ID {$courseOfferingId} not found.");

            return Command::FAILURE;
        }

        // Display course information
        $this->displayCourseInfo($courseOffering, $isDryRun);

        // Validate enrolled students
        $enrolledCount = $courseOffering->courseRegistrations->count();
        if ($enrolledCount === 0) {
            $this->warn('⚠️  No enrolled students found for this course offering.');
            $this->info('Please ensure students are registered before generating attendance.');

            return Command::FAILURE;
        }

        // Validate class sessions
        $sessions = $courseOffering->classSessions;
        if ($sessions->isEmpty()) {
            $this->warn('⚠️  No class sessions found for this course offering.');
            $this->info('Please generate class sessions first before creating attendance records.');

            return Command::FAILURE;
        }

        $this->newLine();
        $this->line('═══════════════════════════════════════════════════════════════════');
        $this->newLine();

        // Process each session
        $totalNew = 0;
        $totalExisting = 0;
        $totalSessions = $sessions->count();
        $errors = [];

        foreach ($sessions as $index => $session) {
            $sessionNumber = $index + 1;

            try {
                $this->displaySessionHeader($session, $sessionNumber, $totalSessions);

                if ($isDryRun) {
                    $this->displayDryRunInfo($session);

                    continue;
                }

                // Generate attendance using service
                $result = $classSessionService->generateAttendanceForSession($session);

                if ($result['success']) {
                    $newRecords = $result['new_records_created'] ?? 0;
                    $existingRecords = $result['existing_records'] ?? 0;

                    $totalNew += $newRecords;
                    $totalExisting += $existingRecords;

                    if ($newRecords > 0) {
                        $this->info("  ✓ Created {$newRecords} new attendance record(s)");
                    }

                    if ($existingRecords > 0) {
                        $this->line("  ℹ  Skipped {$existingRecords} existing record(s)");
                    }

                    if ($newRecords === 0 && $existingRecords > 0) {
                        $this->comment("  → All {$existingRecords} student(s) already have attendance records");
                    }
                } else {
                    $this->warn("  ⚠️  {$result['message']}");
                    if (isset($result['existing_count'])) {
                        $this->line("  ℹ  Existing records: {$result['existing_count']}");
                    }
                }

                $this->newLine();

            } catch (\Exception $e) {
                $errors[] = "Session {$sessionNumber}: {$e->getMessage()}";
                $this->error("  ✗ Error: {$e->getMessage()}");
                $this->newLine();
            }
        }

        // Display summary
        $this->displaySummary($totalSessions, $totalNew, $totalExisting, $enrolledCount, $errors, $isDryRun);

        return empty($errors) ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Display course information header
     */
    private function displayCourseInfo(CourseOffering $courseOffering, bool $isDryRun): void
    {
        $this->info('═══════════════════════════════════════════════════════════════════');
        $this->info('  GENERATE ATTENDANCE FOR COURSE');
        $this->info('═══════════════════════════════════════════════════════════════════');
        $this->newLine();

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $courseCode = $courseOffering->unit->code ?? 'N/A';
        $courseName = $courseOffering->unit->name ?? 'N/A';
        $semester = $courseOffering->semester->name ?? 'N/A';
        $enrolledCount = $courseOffering->courseRegistrations->count();
        $sessionCount = $courseOffering->classSessions->count();

        $this->table(
            ['Property', 'Value'],
            [
                ['Course Code', $courseCode],
                ['Course Name', $courseName],
                ['Section', $courseOffering->section_code ?? 'Default'],
                ['Semester', $semester],
                ['Enrolled Students', $enrolledCount],
                ['Class Sessions', $sessionCount],
                ['Delivery Mode', ucfirst($courseOffering->delivery_mode ?? 'N/A')],
            ]
        );
    }

    /**
     * Display session processing header
     */
    private function displaySessionHeader($session, int $sessionNumber, int $totalSessions): void
    {
        $date = $session->session_date->format('M d, Y');
        $time = $session->start_time->format('H:i').' - '.$session->end_time->format('H:i');
        $status = ucfirst($session->status);
        $title = $session->session_title ?? "Session {$sessionNumber}";

        $this->line("<fg=cyan>Processing Session {$sessionNumber}/{$totalSessions}:</> {$title}");
        $this->line("  📅 {$date} ⏰ {$time} 📊 Status: {$status}");
    }

    /**
     * Display dry run information
     */
    private function displayDryRunInfo($session): void
    {
        $existingCount = $session->attendances()->count();
        $enrolledCount = $session->courseOffering->courseRegistrations()
            ->where('registration_status', 'confirmed')
            ->count();
        $wouldCreate = max(0, $enrolledCount - $existingCount);

        if ($wouldCreate > 0) {
            $this->info("  → Would create {$wouldCreate} new attendance record(s)");
        }

        if ($existingCount > 0) {
            $this->line("  → Would skip {$existingCount} existing record(s)");
        }

        if ($wouldCreate === 0 && $existingCount === 0) {
            $this->comment('  → No students enrolled');
        }

        $this->newLine();
    }

    /**
     * Display summary statistics
     */
    private function displaySummary(
        int $totalSessions,
        int $totalNew,
        int $totalExisting,
        int $enrolledCount,
        array $errors,
        bool $isDryRun
    ): void {
        $this->newLine();
        $this->line('═══════════════════════════════════════════════════════════════════');
        $this->info('  SUMMARY');
        $this->line('═══════════════════════════════════════════════════════════════════');
        $this->newLine();

        if ($isDryRun) {
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Sessions that would be processed', $totalSessions],
                    ['Enrolled students', $enrolledCount],
                ]
            );
            $this->info('✓ Dry run completed. Use the command without --dry-run to actually generate attendance.');
        } else {
            $successRate = $totalSessions > 0
                ? round((($totalSessions - count($errors)) / $totalSessions) * 100, 1)
                : 0;

            $this->table(
                ['Metric', 'Count'],
                [
                    ['Sessions processed', $totalSessions],
                    ['New attendance records created', $totalNew],
                    ['Existing records skipped', $totalExisting],
                    ['Total records', $totalNew + $totalExisting],
                    ['Enrolled students', $enrolledCount],
                    ['Success rate', $successRate.'%'],
                ]
            );

            if (empty($errors)) {
                $this->info('✓ All sessions processed successfully!');
            } else {
                $this->newLine();
                $this->error('⚠️  Errors encountered:');
                foreach ($errors as $error) {
                    $this->error("  - {$error}");
                }
            }
        }

        $this->newLine();
    }
}
