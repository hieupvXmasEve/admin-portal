<?php

namespace App\Console\Commands;

use App\Events\AssessmentDeadlineApproaching;
use App\Models\AssessmentComponentDetail;
use App\Models\CourseOffering;
use App\Models\Student;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ScheduleAssessmentReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:schedule-assessment-reminders
                            {--days=7,3,1 : Days before deadline to send reminders (comma-separated)}
                            {--dry-run : Show what would be scheduled without actually scheduling}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Schedule assessment deadline reminder notifications';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $daysArray = array_map('intval', explode(',', $this->option('days')));
        $isDryRun = $this->option('dry-run');

        $this->info('Scheduling assessment deadline reminders...');
        $this->info('Reminder days: ' . implode(', ', $daysArray));

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No reminders will actually be scheduled');
        }

        $scheduledCount = 0;
        $skippedCount = 0;

        // Get all upcoming assessments
        $upcomingAssessments = AssessmentComponentDetail::whereNotNull('due_date')
            ->where('due_date', '>', now())
            ->where('due_date', '<=', now()->addDays(max($daysArray)))
            ->with(['assessmentComponent.syllabus.courseOffering.unit', 'assessmentComponent.syllabus.courseOffering.semester'])
            ->get();

        $this->info("Found {$upcomingAssessments->count()} upcoming assessments");

        foreach ($upcomingAssessments as $assessment) {
            try {
                $courseOffering = $assessment->assessmentComponent->syllabus->courseOffering;

                if (!$courseOffering) {
                    $this->warn("Skipping assessment {$assessment->id}: No course offering found");
                    $skippedCount++;
                    continue;
                }

                // Calculate days until deadline
                $daysUntilDeadline = now()->diffInDays($assessment->due_date, false);

                // Check if we should send a reminder for this assessment
                if (in_array($daysUntilDeadline, $daysArray)) {
                    $this->scheduleReminderForAssessment($assessment, $courseOffering, $daysUntilDeadline, $isDryRun);
                    $scheduledCount++;
                } else {
                    $this->line("Skipping assessment {$assessment->name}: {$daysUntilDeadline} days until deadline (not in reminder schedule)");
                    $skippedCount++;
                }

            } catch (\Exception $e) {
                $this->error("Error processing assessment {$assessment->id}: {$e->getMessage()}");
                $skippedCount++;

                Log::error('Failed to schedule assessment reminder', [
                    'assessment_id' => $assessment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Completed: {$scheduledCount} reminders scheduled, {$skippedCount} skipped");

        return Command::SUCCESS;
    }

    /**
     * Schedule reminder for a specific assessment
     */
    protected function scheduleReminderForAssessment(
        AssessmentComponentDetail $assessment,
        CourseOffering $courseOffering,
        int $daysUntilDeadline,
        bool $isDryRun
    ): void {
        // Get enrolled students
        $students = Student::whereHas('enrollments', function ($query) use ($courseOffering) {
            $query->where('course_offering_id', $courseOffering->id);
        })->with('user')->get();

        // Get assigned lecturers
        $lecturers = User::whereHas('teachingAssignments', function ($query) use ($courseOffering) {
            $query->where('course_offering_id', $courseOffering->id);
        })->get();

        $this->line("Assessment: {$assessment->name} ({$courseOffering->unit->name})");
        $this->line("  Due: {$assessment->due_date->format('Y-m-d H:i')} ({$daysUntilDeadline} days)");
        $this->line("  Students: {$students->count()}, Lecturers: {$lecturers->count()}");

        if (!$isDryRun) {
            // Schedule reminder for students
            if ($students->isNotEmpty()) {
                $studentUsers = $students->map(fn($student) => $student->user)->filter();

                AssessmentDeadlineApproaching::dispatch(
                    $assessment,
                    $courseOffering,
                    $studentUsers,
                    $daysUntilDeadline,
                    [
                        'recipient_type' => 'students',
                        'submission_link' => config('app.url') . "/courses/{$courseOffering->id}/assessments/{$assessment->id}",
                    ]
                );
            }

            // Schedule reminder for lecturers (different template/content)
            if ($lecturers->isNotEmpty()) {
                AssessmentDeadlineApproaching::dispatch(
                    $assessment,
                    $courseOffering,
                    $lecturers,
                    $daysUntilDeadline,
                    [
                        'recipient_type' => 'lecturers',
                        'management_link' => config('app.url') . "/lecturer/courses/{$courseOffering->id}/assessments/{$assessment->id}",
                    ]
                );
            }

            Log::info('Assessment reminder scheduled', [
                'assessment_id' => $assessment->id,
                'course_offering_id' => $courseOffering->id,
                'days_until_deadline' => $daysUntilDeadline,
                'students_count' => $students->count(),
                'lecturers_count' => $lecturers->count(),
            ]);
        }
    }
}
