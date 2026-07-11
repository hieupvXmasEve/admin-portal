<?php

declare(strict_types=1);

use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Support\SettlementPosition\SettlementPositionScope;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/AggregateSettlementPositionReaderTest.php';

it('keeps the recorded representative batch baseline bounded', function (): void {
    $lines = collect(range(1, 4))
        ->map(fn (): InvoiceLine => createAggregatePayableLine($this->invoice, $this->billingAccount))
        ->all();
    $scopes = array_map(
        fn (InvoiceLine $line): SettlementPositionScope => SettlementPositionScope::payableLine((int) $line->id),
        $lines,
    );
    $reader = app(SettlementPositionReader::class);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $reader->batch([SettlementPositionScope::payableLine((int) $lines[0]->id)]);
    $singleTargetQueryCount = count(DB::getQueryLog());
    DB::flushQueryLog();
    $reader->batch($scopes);
    $batchQueryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    $durations = [];
    foreach (range(1, 15) as $unused) {
        $startedAt = hrtime(true);
        $reader->batch($scopes);
        $durations[] = (hrtime(true) - $startedAt) / 1_000_000;
    }
    sort($durations);
    $p95 = $durations[(int) ceil(count($durations) * 0.95) - 1];

    expect($singleTargetQueryCount)->toBeLessThanOrEqual(6)
        ->and($batchQueryCount)->toBe($singleTargetQueryCount)
        ->and($p95)->toBeLessThanOrEqual(100.0);
});
