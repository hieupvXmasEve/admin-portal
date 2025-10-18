<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ClassSession;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UpdateClassSessionStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sessions:update-statuses
                            {--dry-run : Show what would be updated without making changes}
                            {--force : Force update even if status seems incorrect}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update class session statuses based on current time vs session schedule';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $isVerbose = $this->option('verbose') || $isDryRun;
        $isForce = $this->option('force');

        $this->info('Starting class session status update...');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Get sessions that might need status updates
        $sessions = $this->getSessionsForStatusUpdate();

        if ($sessions->isEmpty()) {
            $this->info('No sessions found that need status updates.');
            return Command::SUCCESS;
        }

        $this->info("Found {$sessions->count()} sessions to check...");

        $updatedCount = 0;
        $errors = [];

        foreach ($sessions as $session) {
            try {
                $currentStatus = $session->status;
                $expectedStatus = $session->getExpectedStatus();

                $statusNeedsUpdate = $currentStatus !== $expectedStatus;
                $attendanceNeedsCreation = $session->status === 'in_progress' 
                    && $session->attendances()->count() === 0;

                if (!$statusNeedsUpdate && !$attendanceNeedsCreation && !$isForce) {
                    if ($isVerbose) {
                        $this->line("Session {$session->id}: Status '{$currentStatus}' is correct, attendance exists");
                    }
                    continue;
                }

                if ($isVerbose || $isDryRun) {
                    $sessionInfo = $this->getSessionInfo($session);
                    if ($statusNeedsUpdate) {
                        $this->line("Session {$session->id} ({$sessionInfo}): {$currentStatus} → {$expectedStatus}");
                    }
                    if ($attendanceNeedsCreation) {
                        $this->line("Session {$session->id} ({$sessionInfo}): Creating missing attendance");
                    }
                }

                if (!$isDryRun) {
                    if ($statusNeedsUpdate) {
                        $updated = $session->updateStatusIfNeeded();
                        if ($updated) {
                            $updatedCount++;
                        }
                    }
                    
                    // Create attendance if missing for in_progress sessions
                    if ($attendanceNeedsCreation) {
                        $session->createDefaultAttendance();
                        $updatedCount++;
                    }
                }

            } catch (\Exception $e) {
                $errors[] = "Session {$session->id}: {$e->getMessage()}";
                if ($isVerbose) {
                    $this->error("Error updating session {$session->id}: {$e->getMessage()}");
                }
            }
        }

        // Report results
        if ($isDryRun) {
            $this->info("DRY RUN: Would have updated {$this->countSessionsNeedingUpdate($sessions)} sessions.");
        } else {
            $this->info("Successfully updated {$updatedCount} session statuses.");
        }

        if (!empty($errors)) {
            $this->error('Errors encountered:');
            foreach ($errors as $error) {
                $this->error("  - {$error}");
            }
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Get sessions that might need status updates
     */
    private function getSessionsForStatusUpdate(): Collection
    {
        return ClassSession::forStatusUpdate()
            ->with(['courseOffering.unit', 'room'])
            ->orderBy('session_date')
            ->orderBy('start_time')
            ->get();
    }

    /**
     * Count sessions that actually need updates
     */
    private function countSessionsNeedingUpdate(Collection $sessions): int
    {
        return $sessions->filter(function ($session) {
            return $session->needsStatusUpdate();
        })->count();
    }

    /**
     * Get readable session information
     */
    private function getSessionInfo(ClassSession $session): string
    {
        $unitCode = $session->courseOffering?->unit?->code ?? 'N/A';
        $date = $session->session_date->format('Y-m-d');
        $time = $session->start_time->format('H:i') . '-' . $session->end_time->format('H:i');

        return "{$unitCode} {$date} {$time}";
    }
}
