<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Audit;

/**
 * Pure transform: flattens a money graph's raw ledger entries into a chronological
 * list of signed timeline events for the UI. Signed amounts are preserved as-is so
 * reversals/releases stay visible as negative events instead of being hidden inside
 * a net balance. No DB access — input is the graph from GetFinanceAuditGraphQuery.
 */
class FinanceLedgerTimelineBuilder
{
    /**
     * @param  array{ledger_entries?:list<array{at:?string,type:string,amount:float|int,entry_type:string,refs:array<string,int>}>}  $graph
     * @return list<array{at:?string,type:string,signed_amount:float,label:string,refs:array<string,int>}>
     */
    public function build(array $graph): array
    {
        $entries = $graph['ledger_entries'] ?? [];

        $events = array_map(function (array $entry): array {
            return [
                'at' => $entry['at'] ?? null,
                'type' => (string) $entry['type'],
                'signed_amount' => (float) $entry['amount'],
                'label' => $this->label((string) $entry['type'], (string) $entry['entry_type']),
                'refs' => $entry['refs'] ?? [],
            ];
        }, $entries);

        // Chronological; undated events sort last. PHP 8 usort is stable.
        usort($events, function (array $a, array $b): int {
            if ($a['at'] === null && $b['at'] === null) {
                return 0;
            }
            if ($a['at'] === null) {
                return 1;
            }
            if ($b['at'] === null) {
                return -1;
            }

            return $a['at'] <=> $b['at'];
        });

        return $events;
    }

    private function label(string $type, string $entryType): string
    {
        return match ($type) {
            'payment_application' => 'Payment '.$entryType,
            'discount_allocation' => 'Discount '.$entryType,
            default => ucfirst(str_replace('_', ' ', $type)).' '.$entryType,
        };
    }
}
