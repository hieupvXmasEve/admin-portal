<?php

namespace App\Console\Commands\Academic;

use Illuminate\Console\Command;

class SyncCreditPointsToAcademicRecords extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'academic:sync-credit-points';

    protected $description = 'Sync credit_points from Unit model to AcademicRecords and calculate credit_points_earned based on credit_hours_earned';

    public function handle()
    {
        $this->info('Starting sync of credit points to academic records...');

        $records = \App\Models\AcademicRecord::with('unit')->get();
        $bar = $this->output->createProgressBar($records->count());

        $bar->start();

        foreach ($records as $record) {
            if (!$record->unit) {
                $bar->advance();
                continue;
            }

            $creditPoints = $record->unit->credit_points ?? 0;
            $record->credit_points = $creditPoints;
            
            // If credit_hours_earned > 0, then credit_points_earned = credit_points of the unit
            if ($record->credit_hours_earned > 0) {
                $record->credit_points_earned = $creditPoints;
            } else {
                $record->credit_points_earned = 0;
            }

            $record->save();
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Credit points sync completed successfully!');
    }
}
