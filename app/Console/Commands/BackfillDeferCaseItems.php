<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Finance\Actions\Operations\BackfillDeferCaseItemsAction;
use Illuminate\Console\Command;

/**
 * FIN-REV-020 — classify and (optionally) backfill item-level evidence for
 * full-scope defer cases.
 *
 * Read-only by default: it prints an operator report separating auto-safe
 * (itemizable) cases from needs-review cases. With --apply it creates the
 * missing defer_case_items and marks those registrations non-billable. It never
 * mutates money; the preserve/forfeit/partial settlement is a separate phase.
 */
class BackfillDeferCaseItems extends Command
{
    protected $signature = 'finance:defer-backfill
        {--apply : Create the missing defer_case_items (default is a read-only dry-run)}
        {--apply-money : Settle the defer finance policy for auto-safe full-scope cases (FIN-REV-020-03)}
        {--semester= : Restrict to a single semester id}
        {--cases : Print a per-case detail table}';

    protected $description = 'FIN-REV-020: classify/backfill item-level evidence for full-scope defer cases (read-only by default; --apply-money settles auto-safe cases)';

    public function handle(BackfillDeferCaseItemsAction $action): int
    {
        $semesterId = $this->option('semester') !== null ? (int) $this->option('semester') : null;
        $apply = (bool) $this->option('apply');
        $applyMoney = (bool) $this->option('apply-money');

        $result = $action->handle($apply, $semesterId, $applyMoney);
        $counts = $result['counts'];

        $this->line('');
        $this->info(sprintf(
            '📦 Defer backfill (%s)%s',
            $result['mode'],
            $semesterId ? " — semester #{$semesterId}" : ' — all semesters'
        ));

        $this->table(['Metric', 'Count'], [
            ['Full-scope defer cases', $counts['total']],
            ['Itemizable (auto-safe)', $counts['itemizable']],
            ['Already itemized', $counts['already_itemized']],
            ['No active registration (review)', $counts['no_registration']],
            ['Needs review (total)', $counts['needs_review']],
            ['Planned items', $counts['planned_items']],
        ]);

        if ($apply) {
            $this->info(sprintf(
                '✅ Applied: %d item(s) created across %d case(s); %d registration(s) marked defer.',
                $result['items_created'],
                $result['cases_itemized'],
                $result['registrations_marked'],
            ));
        } else {
            $this->comment('Dry-run only — no rows written. Re-run with --apply to create the planned items.');
        }

        if ($applyMoney) {
            $money = $result['money'];
            $this->info(sprintf(
                '💰 Money settled: %d case(s) settled, %d skipped (needs review), %d noop; released %s, consumed %s.',
                $money['settled'],
                $money['skipped'],
                $money['noop'],
                number_format($money['released']),
                number_format($money['consumed']),
            ));

            if ($money['by_reason'] !== []) {
                $this->table(
                    ['Outcome reason', 'Cases'],
                    array_map(static fn (string $reason, int $count): array => [$reason, $count], array_keys($money['by_reason']), $money['by_reason']),
                );
            }
        } else {
            $this->comment('No money mutated. Re-run with --apply-money to settle auto-safe full-scope cases.');
        }

        if ($this->option('cases')) {
            $this->printCaseTable($result['cases']);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $cases
     */
    private function printCaseTable(array $cases): void
    {
        if ($cases === []) {
            return;
        }

        $rows = array_map(static fn (array $case): array => [
            $case['defer_case_id'],
            $case['student_id'],
            $case['fee_policy'],
            $case['itemization'],
            $case['planned_item_count'],
            implode(',', $case['finance_flags']),
            $case['review_required'] ? 'yes' : '',
        ], $cases);

        $this->line('');
        $this->table(
            ['Case', 'Student', 'Policy', 'Itemization', 'Planned', 'Finance flags', 'Review'],
            $rows,
        );
    }
}
