<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Academic\Actions\RecoverAcademicFinanceCancellationHandoffsAction;
use App\Modules\Finance\Actions\RecoverFinanceCancellationWorkAction;
use Illuminate\Console\Command;

class RecoverFinanceCancellationWorkCommand extends Command
{
    protected $signature = 'finance:recover-cancellation-work {--limit=100 : Maximum rows per recovery category}';

    protected $description = 'Re-dispatch committed Finance cancellation handoffs, operations, and completion outbox rows';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $handoffIds = RecoverAcademicFinanceCancellationHandoffsAction::run(['limit' => $limit]);
        $finance = RecoverFinanceCancellationWorkAction::run(['limit' => $limit]);

        $this->info(sprintf(
            'Queued %d Academic handoff(s), %d Finance operation(s), and %d completion event(s).',
            count($handoffIds),
            count($finance['operation_ids']),
            count($finance['completion_outbox_ids']),
        ));

        return self::SUCCESS;
    }
}
