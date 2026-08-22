<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\CanvasConnectionException;
use App\Models\CanvasCourseMapping;
use App\Modules\Academic\Delivery\Support\CanvasGradeSyncService;
use Illuminate\Console\Command;

class SyncAcademicRecordsCommand extends Command
{
    protected $signature = 'academic-records:sync
                            {--course-offering-id= : Sync specific course offering only}
                            {--chunk-size=10 : Number of courses to process per batch (default: 10)}
                            {--memory-limit=1024 : Memory limit in MB (default: 1024)}
                            {--dry-run : Run without making changes}';

    protected $description = 'Sync academic records from Canvas for all mapped courses';

    public function __construct(
        private CanvasGradeSyncService $gradeSyncService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting Canvas grade sync for mapped courses...');
        $this->newLine();

        if ($this->option('dry-run')) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();

            return self::SUCCESS;
        }

        // Set memory limit
        $memoryLimitMB = (int) $this->option('memory-limit');
        $memoryLimitBytes = $memoryLimitMB * 1024 * 1024;
        ini_set('memory_limit', "{$memoryLimitMB}M");
        $this->info("Memory limit set to: {$memoryLimitMB}MB");

        $startTime = microtime(true);
        $memoryStart = memory_get_usage(true);

        try {
            // Get all mapped Canvas courses with canvas sync enabled AND active integrations
            $query = CanvasCourseMapping::where('sync_status', 'mapped')
                ->whereNotNull('course_offering_id')
                ->whereHas('courseOffering', function ($q) {
                    $q->where('is_canvas_synced', true);
                })
                ->whereHas('canvasIntegration', function ($q) {
                    $q->where('is_active', true);
                })
                ->with(['courseOffering', 'canvasIntegration']);

            // Filter by specific course offering if provided
            if ($courseOfferingId = $this->option('course-offering-id')) {
                $query->where('course_offering_id', $courseOfferingId);
            }

            $totalMappings = $query->count();

            if ($totalMappings === 0) {
                $this->warn('No mapped Canvas courses found with active integrations.');

                return self::SUCCESS;
            }

            $this->info("Found {$totalMappings} mapped course(s) with active integrations to sync");
            $this->newLine();

            // Get chunk size from option
            $chunkSize = (int) $this->option('chunk-size');
            $this->info("Processing in chunks of {$chunkSize} courses");
            $this->newLine();

            // Initialize stats
            $totalStats = [
                'courses_processed' => 0,
                'courses_success' => 0,
                'courses_failed' => 0,
                'courses_skipped' => 0,
                'students_synced' => 0,
                'students_skipped' => 0,
                'total_errors' => 0,
                'integrations_deactivated' => 0,
                'finalized_semester_changes' => 0,
            ];

            // Origin recorded on every finalized-semester change this run emits.
            // This is the scheduled/system path; causer_id stays NULL (no synthetic
            // user), origin is answered by these properties instead.
            $origin = [
                'source' => 'scheduled-sync',
                'command' => $this->getName(),
                'run_at' => now()->toIso8601String(),
            ];

            // Per-semester roll-up of records changed inside finalized semesters,
            // so the summary can name which semesters now need re-finalizing.
            $finalizedSemesters = [];

            $progressBar = $this->output->createProgressBar($totalMappings);
            $progressBar->setFormat('verbose');

            // Track deactivated integrations to skip them
            $deactivatedIntegrationIds = [];

            // Process in chunks to manage memory
            $query->chunk($chunkSize, function ($mappings) use (&$totalStats, $progressBar, $memoryLimitBytes, &$deactivatedIntegrationIds, $origin, &$finalizedSemesters) {
                foreach ($mappings as $mapping) {
                    $courseOfferingName = $mapping->courseOffering->course_code ?? "ID:{$mapping->course_offering_id}";
                    $progressBar->setMessage("Syncing: {$courseOfferingName}");

                    // Skip if this integration was already deactivated in this run
                    if (in_array($mapping->canvas_integration_id, $deactivatedIntegrationIds)) {
                        $this->newLine();
                        $this->warn("⏭ Skipping {$courseOfferingName} - Canvas integration was deactivated");
                        $totalStats['courses_skipped']++;
                        $progressBar->advance();

                        continue;
                    }

                    // Double-check integration is still active (may have been deactivated by another process)
                    $mapping->canvasIntegration->refresh();
                    if (! $mapping->canvasIntegration->is_active) {
                        $this->newLine();
                        $this->warn("⏭ Skipping {$courseOfferingName} - Canvas integration is inactive");
                        $totalStats['courses_skipped']++;
                        $deactivatedIntegrationIds[] = $mapping->canvas_integration_id;
                        $progressBar->advance();

                        continue;
                    }

                    try {
                        $result = $this->gradeSyncService->syncCourseGrades($mapping, null, $origin);

                        if ($result['success']) {
                            $totalStats['courses_success']++;
                            $totalStats['students_synced'] += $result['students_synced'] ?? 0;
                            $totalStats['students_skipped'] += $result['students_skipped'] ?? 0;
                            $totalStats['total_errors'] += count($result['errors'] ?? []);

                            foreach ($result['finalized_semester_alerts'] ?? [] as $alert) {
                                $totalStats['finalized_semester_changes'] += $alert['changed_records'];
                                $rollup = $finalizedSemesters[$alert['semester_id']]
                                    ?? ['changed_records' => 0, 'students' => []];
                                $rollup['changed_records'] += $alert['changed_records'];
                                $rollup['students'] += $alert['student_ids'];
                                $finalizedSemesters[$alert['semester_id']] = $rollup;
                            }
                        } else {
                            $totalStats['courses_failed']++;
                        }

                        $totalStats['courses_processed']++;
                    } catch (CanvasConnectionException $e) {
                        $totalStats['courses_failed']++;
                        $totalStats['courses_processed']++;

                        $this->newLine();

                        if ($e->shouldDeactivate) {
                            $this->error("✗ Canvas integration deactivated for {$courseOfferingName}: {$e->getMessage()}");
                            $deactivatedIntegrationIds[] = $mapping->canvas_integration_id;
                            $totalStats['integrations_deactivated']++;
                        } else {
                            $this->warn("⚠ Canvas connection issue for {$courseOfferingName}: {$e->getMessage()}");
                        }
                    } catch (\Exception $e) {
                        $totalStats['courses_failed']++;
                        $totalStats['courses_processed']++;

                        $this->newLine();
                        $this->error("✗ Failed to sync course {$courseOfferingName}: {$e->getMessage()}");
                    }

                    $progressBar->advance();

                    // Check memory usage
                    $currentMemory = memory_get_usage(true);
                    $memoryPercentage = ($currentMemory / $memoryLimitBytes) * 100;

                    if ($memoryPercentage > 80) {
                        $this->newLine();
                        $this->warn(sprintf(
                            'High memory usage: %.1f%% (%dMB / %dMB)',
                            $memoryPercentage,
                            round($currentMemory / 1024 / 1024, 2),
                            round($memoryLimitBytes / 1024 / 1024, 2)
                        ));
                        $this->info('Running garbage collection...');
                        gc_collect_cycles();
                    }
                }

                // Force garbage collection after each chunk
                gc_collect_cycles();
            });

            $progressBar->finish();
            $this->newLine(2);

            // Display results
            $this->displayStats($totalStats);

            $executionTime = round(microtime(true) - $startTime, 2);
            $memoryUsed = round((memory_get_usage(true) - $memoryStart) / 1024 / 1024, 2);
            $memoryPeak = round(memory_get_peak_usage(true) / 1024 / 1024, 2);

            $this->newLine();
            $this->info("Sync completed in {$executionTime} seconds");
            $this->info("Memory used: {$memoryUsed}MB | Peak: {$memoryPeak}MB");

            // Show summary
            if ($totalStats['courses_success'] > 0) {
                $this->info("Successfully synced {$totalStats['courses_success']} course(s)");
            }
            if ($totalStats['students_synced'] > 0) {
                $this->info("Synced grades for {$totalStats['students_synced']} student(s)");
            }
            if ($totalStats['students_skipped'] > 0) {
                $this->warn("Skipped {$totalStats['students_skipped']} student(s) (not found in Canvas)");
            }
            if ($totalStats['courses_skipped'] > 0) {
                $this->warn("Skipped {$totalStats['courses_skipped']} course(s) (inactive integration)");
            }
            if ($totalStats['courses_failed'] > 0) {
                $this->error("Failed to sync {$totalStats['courses_failed']} course(s)");
            }
            if ($totalStats['integrations_deactivated'] > 0) {
                $this->error("Deactivated {$totalStats['integrations_deactivated']} Canvas integration(s) due to connection issues");
            }
            if ($totalStats['total_errors'] > 0) {
                $this->error("Encountered {$totalStats['total_errors']} error(s) during sync");
            }

            if ($totalStats['finalized_semester_changes'] > 0) {
                $this->newLine();
                $this->warn(
                    "Changed {$totalStats['finalized_semester_changes']} record(s) inside already-finalized semester(s). "
                    . 'These semesters need re-finalizing to refresh their GPA snapshots:'
                );
                foreach ($finalizedSemesters as $semesterId => $rollup) {
                    $studentCount = count($rollup['students']);
                    $this->warn("  - Semester {$semesterId}: {$rollup['changed_records']} record change(s) across {$studentCount} student(s)");
                }
            }

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Error: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        }
    }

    private function displayStats(array $stats): void
    {
        $this->table(
            ['Canvas Grade Sync', 'Count'],
            [
                ['Courses Processed', $stats['courses_processed']],
                ['Courses Success', $stats['courses_success']],
                ['Courses Failed', $stats['courses_failed']],
                ['Courses Skipped', $stats['courses_skipped']],
                ['Students Synced', $stats['students_synced']],
                ['Students Skipped', $stats['students_skipped']],
                ['Integrations Deactivated', $stats['integrations_deactivated']],
                ['Total Errors', $stats['total_errors']],
            ]
        );
    }
}
