<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Finance\Actions\CloseKnownLegacyDataExceptionsAction;
use Illuminate\Console\Command;

class CloseKnownFinanceDataExceptionsCommand extends Command
{
    protected $signature = 'finance:close-known-legacy-data-exceptions';

    protected $description = 'Apply the two approved Finance legacy-retirement data repairs transactionally';

    public function handle(): int
    {
        CloseKnownLegacyDataExceptionsAction::run([]);
        $this->info('Closed the approved Finance data exceptions.');

        return self::SUCCESS;
    }
}
