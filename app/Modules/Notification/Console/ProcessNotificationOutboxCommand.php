<?php

declare(strict_types=1);

namespace App\Modules\Notification\Console;

use App\Modules\Notification\Actions\DispatchOutboxBatchAction;
use Illuminate\Console\Command;

class ProcessNotificationOutboxCommand extends Command
{
    protected $signature = 'notifications:process-outbox {--limit=100 : Batch size for each run}';

    protected $description = 'Process pending notification outbox events';

    public function handle(DispatchOutboxBatchAction $dispatchOutboxBatchAction): int
    {
        $result = $dispatchOutboxBatchAction->run((int) $this->option('limit'));

        $this->info(sprintf(
            'processed=%d queued_deliveries=%d failed=%d',
            $result['processed'],
            $result['queued_deliveries'],
            $result['failed']
        ));

        return self::SUCCESS;
    }
}
