<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CourseOffering;
use App\Models\FormSurvey;
use App\Services\CourseSurveyService;
use Illuminate\Console\Command;

class CreateMissingCourseSurveysCommand extends Command
{
    protected $signature = 'course-surveys:create-missing
                            {--course-offering-id= : Process specific course offering ID}
                            {--status=completed : Filter by course status (completed, in_progress, etc.)}
                            {--all : Process all course offerings regardless of status}
                            {--dry-run : Run without making changes}';

    protected $description = 'Create surveys for course offerings that do not have surveys yet';

    public function __construct(
        private CourseSurveyService $courseSurveyService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting course survey creation for missing surveys...');
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        $startTime = microtime(true);
        $memoryStart = memory_get_usage(true);

        try {
            $courseOfferings = $this->getCourseOfferings();

            if ($courseOfferings->isEmpty()) {
                $this->info('No course offerings found matching the criteria.');
                return self::SUCCESS;
            }

            $this->info("Found {$courseOfferings->count()} course offering(s) without surveys");
            $this->newLine();

            if ($this->option('dry-run')) {
                $this->displayPreview($courseOfferings);
                return self::SUCCESS;
            }

            $stats = $this->processCourseOfferings($courseOfferings);

            $executionTime = round(microtime(true) - $startTime, 2);
            $memoryUsed = round((memory_get_usage(true) - $memoryStart) / 1024 / 1024, 2);
            $memoryPeak = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

            $this->newLine();
            $this->displayStats($stats);
            $this->info("✓ Completed in {$executionTime} seconds");
            $this->info("📊 Memory used: {$memoryUsed}MB | Peak: {$memoryPeak}MB");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        }
    }

    private function getCourseOfferings()
    {
        $query = CourseOffering::query()
            ->whereDoesntHave('formSurveys')
            ->with(['unit', 'semester']);

        // Filter by specific course offering ID
        if ($this->option('course-offering-id')) {
            $query->where('id', $this->option('course-offering-id'));
        }

        // Filter by course status (unless --all is specified)
        if (! $this->option('all')) {
            $status = $this->option('status') ?? 'completed';
            $query->where('course_status', $status);
        }

        return $query->get();
    }

    private function displayPreview($courseOfferings): void
    {
        $this->table(
            ['ID', 'Course Code', 'Section', 'Semester', 'Status', 'Unit Type'],
            $courseOfferings->map(function ($offering) {
                return [
                    $offering->id,
                    $offering->course_code ?? 'N/A',
                    $offering->section_code ?? 'N/A',
                    $offering->semester?->code ?? 'N/A',
                    $offering->course_status ?? 'N/A',
                    $offering->unit?->unit_type ?? 'N/A',
                ];
            })->toArray()
        );

        $this->newLine();
        $this->warn('This would create surveys for the above course offerings.');
    }

    private function processCourseOfferings($courseOfferings): array
    {
        $stats = [
            'total' => $courseOfferings->count(),
            'created' => 0,
            'skipped' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        $progressBar = $this->output->createProgressBar($courseOfferings->count());
        $progressBar->setFormat('verbose');
        $progressBar->start();

        foreach ($courseOfferings as $courseOffering) {
            try {
                $result = $this->courseSurveyService->attachSurveyToCompletedCourse($courseOffering);

                if ($result) {
                    $stats['created']++;
                } else {
                    $stats['skipped']++;
                }
            } catch (\Exception $e) {
                $stats['failed']++;
                $stats['errors'][] = [
                    'course_offering_id' => $courseOffering->id,
                    'error' => $e->getMessage(),
                ];

                $this->newLine();
                $this->error("Failed to create survey for course offering #{$courseOffering->id}: {$e->getMessage()}");
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        return $stats;
    }

    private function displayStats(array $stats): void
    {
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Processed', $stats['total']],
                ['Surveys Created', $stats['created']],
                ['Skipped', $stats['skipped']],
                ['Failed', $stats['failed']],
            ]
        );

        if (! empty($stats['errors'])) {
            $this->newLine();
            $this->warn('Errors encountered:');
            foreach ($stats['errors'] as $error) {
                $this->line("  - Course Offering #{$error['course_offering_id']}: {$error['error']}");
            }
        }
    }
}
