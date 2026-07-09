<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Finance\Actions\BackfillBillingAccountsAction;
use Illuminate\Console\Command;

class BackfillBillingAccountsCommand extends Command
{
    protected $signature = 'finance:backfill-billing-accounts
        {--dry-run : Report changes without writing billing accounts or obligation links}';

    protected $description = 'Provision billing accounts for all Students and backfill finance_obligations.billing_account_id';

    public function handle(BackfillBillingAccountsAction $action): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $action->run($dryRun);

        $prefix = $dryRun ? '[dry-run] ' : '';

        $this->info(sprintf(
            '%sStudents: checked %d, created %d, already had account %d.',
            $prefix,
            $result['students_checked'],
            $result['students_created'],
            $result['students_already'],
        ));

        $this->info(sprintf(
            '%sObligations: checked %d, updated %d, already linked %d, skipped %d.',
            $prefix,
            $result['obligations_checked'],
            $result['obligations_updated'],
            $result['obligations_already'],
            $result['obligations_skipped'],
        ));

        if ($result['obligations_skipped'] > 0) {
            $this->warn(sprintf(
                'Skipped %d obligation(s) with no charge/source student evidence.',
                $result['obligations_skipped'],
            ));
        }

        return self::SUCCESS;
    }
}
