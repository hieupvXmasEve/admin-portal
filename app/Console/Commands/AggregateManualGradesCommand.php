<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CourseOffering;
use App\Services\CourseCompletionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AggregateManualGradesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'academic-records:aggregate-manual
                            {--course-offering-id= : Aggregate for a specific course offering only}
                            {--semester-id= : Aggregate for a specific semester}
                            {--dry-run : Run without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Aggregate manual grades from assessment components into academic records for in-progress courses';

    /**
     * Execute the console command.
     */
    public function handle(CourseCompletionService $courseCompletionService): int
    {
        $this->info('Starting manual grade aggregation...');

        $query = CourseOffering::where('is_canvas_synced', false)
            ->whereIn('course_status', ['not_started', 'in_progress']);

        if ($this->option('course-offering-id')) {
            $query->where('id', $this->option('course-offering-id'));
        }

        if ($this->option('semester-id')) {
            $query->where('semester_id', $this->option('semester-id'));
        }

        $courseOfferings = $query->get();

        if ($courseOfferings->isEmpty()) {
            $this->warn('No eligible manual course offerings found.');
            return 0;
        }

        $this->info("Processing {$courseOfferings->count()} course offerings...");
        $bar = $this->output->createProgressBar($courseOfferings->count());
        $bar->start();

        $successCount = 0;
        $failCount = 0;

        foreach ($courseOfferings as $courseOffering) {
            try {
                if (! $this->option('dry-run')) {
                    $courseCompletionService->aggregateManualGrades($courseOffering);
                }
                $successCount++;
            } catch (\Exception $e) {
                $failCount++;
                $this->error("\nFailed to aggregate grades for Course ID {$courseOffering->id}: " . $e->getMessage());
                Log::error('Manual grade aggregation failed', [
                    'course_offering_id' => $courseOffering->id,
                    'error' => $e->getMessage(),
                ]);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Aggregation completed.');
        $this->table(
            ['Total', 'Success', 'Failed'],
            [[$courseOfferings->count(), $successCount, $failCount]]
        );

        return $failCount > 0 ? 1 : 0;
    }
}
