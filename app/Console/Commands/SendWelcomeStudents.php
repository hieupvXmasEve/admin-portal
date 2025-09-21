<?php

namespace App\Console\Commands;

use App\Enums\NotificationCategory;
use App\Models\Notification;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendWelcomeStudents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notify:welcome-students
                            {university : The university name to use in the welcome message}
                            {--dry-run : Show what would be processed without making changes}
                            {--since= : Send to students admitted since this date (YYYY-MM-DD format)}
                            {--days=30 : Send to students admitted within the last N days (ignored if --since is used)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send welcome notifications to new students';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $university = trim((string) $this->argument('university'));
        if ($university === '') {
            $this->error('The university argument is required and cannot be empty.');
            return 1;
        }

        $isDryRun = $this->option('dry-run');
        $since = $this->option('since');
        $days = (int) $this->option('days');

        // Determine the date criteria for "new students"
        $cutoffDate = $this->determineCutoffDate($since, $days);

        if (!$cutoffDate) {
            $this->error('Invalid date format. Please use YYYY-MM-DD format for --since option.');
            return 1;
        }

        $this->info("[$university] Looking for students admitted since: {$cutoffDate->format('Y-m-d H:i:s')}");

        // Count total students that match criteria
        $totalStudents = $this->getNewStudentsQuery($cutoffDate, $university)->count();

        if ($totalStudents === 0) {
            $this->info('No new students found matching the criteria.');
            return 0;
        }

        $this->info("Found {$totalStudents} new student(s) to notify.");

        if ($isDryRun) {
            $this->showDryRunPreview($cutoffDate, $university);
            return 0;
        }

        // Process students in chunks to avoid memory issues
        $processed = 0;
        $successful = 0;
        $failed = 0;

        $progressBar = $this->output->createProgressBar($totalStudents);
        $progressBar->setFormat('Processing students: %current%/%max% [%bar%] %percent:3s%% - Success: %message%');
        $progressBar->setMessage($successful);
        $progressBar->start();

        $this->getNewStudentsQuery($cutoffDate, $university)->chunkById(100, function ($students) use (&$processed, &$successful, &$failed, $progressBar, $university) {
            foreach ($students as $student) {
                try {
                    if ($this->sendWelcomeNotification($student, $university)) {
                        $successful++;
                    } else {
                        $failed++;
                    }
                } catch (\Exception $e) {
                    $this->error("Failed to send notification to student {$student->id}: " . $e->getMessage());
                    $failed++;
                }

                $processed++;
                $progressBar->setMessage($successful);
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine();

        $this->info("Processing complete!");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Students', $totalStudents],
                ['Processed', $processed],
                ['Successful', $successful],
                ['Failed', $failed],
            ]
        );

        return 0;
    }

    /**
     * Determine the cutoff date based on command options
     */
    private function determineCutoffDate(?string $since, int $days): ?Carbon
    {
        if ($since) {
            try {
                return Carbon::createFromFormat('Y-m-d', $since)->startOfDay();
            } catch (\Exception $e) {
                return null;
            }
        }

        return Carbon::now()->subDays($days)->startOfDay();
    }

    /**
     * Get query for new students based on criteria
     */
    private function getNewStudentsQuery(Carbon $cutoffDate, string $university)
    {
        $title = "Welcome to {$university}!";
        return Student::where('admission_date', '>=', $cutoffDate)
            ->whereIn('status', ['active', 'intake_pre_uni_gc', 'intake_course', 'pending']) // Include new student statuses
            ->whereDoesntHave('notifications', function ($query) use ($title) {
                $query->where('title', $title)
                    ->where('category', NotificationCategory::SYSTEM);
            }); // Idempotency: exclude students who already received welcome notification
    }

    /**
     * Show preview of what would be processed in dry-run mode
     */
    private function showDryRunPreview(Carbon $cutoffDate, string $university): void
    {
        $students = $this->getNewStudentsQuery($cutoffDate, $university)
            ->select('id', 'student_id', 'full_name', 'email', 'admission_date', 'status')
            ->limit(10)
            ->get();

        if ($students->isEmpty()) {
            $this->warn('No students match the criteria.');
            return;
        }

        $this->warn("DRY RUN: The following students would receive welcome notifications for {$university}:");
        $this->table(
            ['ID', 'Student ID', 'Full Name', 'Email', 'Admission Date', 'Status'],
            $students->map(function ($student) {
                return [
                    $student->id,
                    $student->student_id,
                    $student->full_name,
                    $student->email,
                    $student->admission_date->format('Y-m-d'),
                    $student->status,
                ];
            })
        );

        $totalCount = $this->getNewStudentsQuery($cutoffDate, $university)->count();
        if ($totalCount > 10) {
            $this->info("... and " . ($totalCount - 10) . " more student(s).");
        }

        $this->warn('No actual notifications will be sent in dry-run mode.');
    }

    /**
     * Send welcome notification to a student
     */
    private function sendWelcomeNotification(Student $student, string $university): bool
    {
        try {
            // Check if student already has a welcome notification (idempotency)
            $existingNotification = Notification::where('notifiable_type', Student::class)
                ->where('notifiable_id', $student->id)
                ->where('title', "Welcome to {$university}!")
                ->where('category', NotificationCategory::SYSTEM)
                ->first();

            if ($existingNotification) {
                return true; // Already sent, consider it successful
            }
            $name = mb_strtoupper($student->full_name);
            $university = ucwords($university);
            // Create welcome notification
            Notification::create([
                'type' => Student::class,
                'notifiable_type' => Student::class,
                'notifiable_id' => $student->id,
                'category' => NotificationCategory::SYSTEM,
                'title' => "Welcome to {$university}!",
                'message' => "Dear {$name}, welcome to {$university}! We're excited to have you join our academic community. Please explore your student portal and don't hesitate to reach out if you need any assistance.",
                'data' => [
                    'student_id' => $student->student_id,
                    'admission_date' => $student->admission_date->toDateString(),
                    'welcome_type' => 'new_student_welcome',
                ],
                'channels' => ['database', 'broadcast'],
                'is_important' => false,
                'expires_at' => null, // Welcome messages don't expire
            ]);

            return true;
        } catch (\Exception $e) {
            $this->error("Error creating notification for student {$student->id}: " . $e->getMessage());
            return false;
        }
    }
}
