<?php

namespace App\Console\Commands;

use App\Models\Semester;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DeactivateExpiredSemesters extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'semester:deactivate-expired {--dry-run : Show what would be deactivated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically deactivate semesters that have passed their end date';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $now = Carbon::now();

        $this->info("Checking for expired semesters as of: {$now->format('Y-m-d H:i:s')}");

        // Find active semesters that have expired
        $expiredSemesters = Semester::where('is_active', true)
            ->where('end_date', '<', $now)
            ->get();

        if ($expiredSemesters->isEmpty()) {
            $this->info('No expired active semesters found.');

            return 0;
        }

        $this->table(
            ['ID', 'Code', 'Name', 'End Date', 'Days Expired'],
            $expiredSemesters->map(function ($semester) use ($now) {
                return [
                    $semester->id,
                    $semester->code,
                    $semester->name,
                    $semester->end_date->format('Y-m-d H:i:s'),
                    $now->diffInDays($semester->end_date).' days',
                ];
            })
        );

        if ($isDryRun) {
            $this->warn('DRY RUN: The above semesters would be deactivated.');

            return 0;
        }

        if (! $this->confirm('Do you want to deactivate these expired semesters?')) {
            $this->info('Operation cancelled.');

            return 0;
        }

        // Deactivate expired semesters
        $deactivatedCount = Semester::deactivateExpiredSemesters();

        $this->info("Successfully deactivated {$deactivatedCount} expired semester(s).");

        return 0;
    }
}
