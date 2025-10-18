<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ClassSession;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CompleteOldClassSessionsCommand extends Command
{
    protected $signature = 'sessions:complete-old
                            {--before= : Complete sessions before this date (Y-m-d format, default: today)}
                            {--dry-run : Show what would be updated without making changes}';

    protected $description = 'Complete old class sessions that are still scheduled or in_progress';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $beforeDate = $this->option('before') 
            ? Carbon::parse($this->option('before'))
            : Carbon::today();

        $this->info('Completing old class sessions...');
        $this->info("Target: Sessions before {$beforeDate->format('Y-m-d')}");
        $this->newLine();

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        try {
            // Find old sessions that need to be completed
            $query = ClassSession::whereIn('status', ['scheduled', 'in_progress'])
                ->where(function ($q) use ($beforeDate) {
                    // Sessions where the date has passed
                    $q->where('session_date', '<', $beforeDate)
                      // OR sessions on the same date but end time has passed
                      ->orWhere(function ($subq) use ($beforeDate) {
                          $subq->where('session_date', '=', $beforeDate->format('Y-m-d'))
                               ->whereRaw('TIME(end_time) < ?', [$beforeDate->format('H:i:s')]);
                      });
                })
                ->with(['courseOffering.unit']);

            $totalCount = $query->count();

            if ($totalCount === 0) {
                $this->info('No old sessions found that need to be completed.');
                return Command::SUCCESS;
            }

            $this->info("Found {$totalCount} sessions to complete:");
            $this->newLine();

            // Group by status for summary
            $statusCounts = $query->clone()
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $this->table(
                ['Current Status', 'Count'],
                collect($statusCounts)->map(fn($count, $status) => [$status, $count])->toArray()
            );
            $this->newLine();

            if ($isDryRun) {
                // Show sample sessions
                $this->info('Sample sessions (first 10):');
                $samples = $query->clone()->limit(10)->get();
                
                $sampleData = $samples->map(function ($session) {
                    return [
                        'ID' => $session->id,
                        'Unit' => $session->courseOffering?->unit?->code ?? 'N/A',
                        'Date' => $session->session_date->format('Y-m-d'),
                        'Time' => $session->start_time->format('H:i') . '-' . $session->end_time->format('H:i'),
                        'Status' => $session->status,
                    ];
                })->toArray();

                $this->table(
                    ['ID', 'Unit', 'Date', 'Time', 'Status'],
                    $sampleData
                );

                $this->newLine();
                $this->warn("DRY RUN: Would complete {$totalCount} sessions.");
                return Command::SUCCESS;
            }

            // Ask for confirmation
            if (!$this->confirm("Do you want to complete {$totalCount} sessions?")) {
                $this->warn('Operation cancelled.');
                return Command::SUCCESS;
            }

            // Perform bulk update
            $this->info('Updating sessions...');
            $progressBar = $this->output->createProgressBar($totalCount);
            $progressBar->start();

            $updated = 0;
            $errors = 0;

            // Process in chunks to avoid memory issues
            $query->chunk(100, function ($sessions) use (&$updated, &$errors, $progressBar) {
                foreach ($sessions as $session) {
                    try {
                        DB::transaction(function () use ($session) {
                            // Calculate ended_at from session_date + end_time
                            $endedAt = $session->ended_at;
                            if (!$endedAt) {
                                $endedAt = $session->session_date
                                    ->copy()
                                    ->setTimeFromTimeString($session->end_time->format('H:i:s'));
                            }

                            $session->update([
                                'status' => 'completed',
                                'ended_at' => $endedAt,
                            ]);
                        });
                        $updated++;
                    } catch (\Exception $e) {
                        $errors++;
                        \Log::error("Failed to complete session {$session->id}: {$e->getMessage()}");
                    }
                    $progressBar->advance();
                }
            });

            $progressBar->finish();
            $this->newLine(2);

            // Show results
            $this->table(
                ['Result', 'Count'],
                [
                    ['Successfully completed', $updated],
                    ['Errors', $errors],
                    ['Total processed', $totalCount],
                ]
            );

            if ($updated > 0) {
                $this->info("✓ Successfully completed {$updated} sessions");
            }

            if ($errors > 0) {
                $this->error("✗ Failed to complete {$errors} sessions (check logs)");
                return Command::FAILURE;
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
