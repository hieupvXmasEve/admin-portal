<?php

declare(strict_types=1);

use App\Modules\Finance\Support\Reporting\CollectionProgressCatalog as Catalog;

it('classifies non-overdue ages into the not-due bucket', function () {
    expect(Catalog::classifyAging(0))->toBe(Catalog::BUCKET_NOT_DUE)
        ->and(Catalog::classifyAging(-5))->toBe(Catalog::BUCKET_NOT_DUE);
});

it('classifies overdue ages into inclusive day-range buckets', function () {
    expect(Catalog::classifyAging(1))->toBe(Catalog::BUCKET_1_30)
        ->and(Catalog::classifyAging(30))->toBe(Catalog::BUCKET_1_30)
        ->and(Catalog::classifyAging(31))->toBe(Catalog::BUCKET_31_60)
        ->and(Catalog::classifyAging(60))->toBe(Catalog::BUCKET_31_60)
        ->and(Catalog::classifyAging(61))->toBe(Catalog::BUCKET_61_90)
        ->and(Catalog::classifyAging(90))->toBe(Catalog::BUCKET_61_90)
        ->and(Catalog::classifyAging(91))->toBe(Catalog::BUCKET_90_PLUS)
        ->and(Catalog::classifyAging(400))->toBe(Catalog::BUCKET_90_PLUS);
});

it('exposes the FIN-REV-012 balance-state vocabulary', function () {
    expect(Catalog::balanceStateKeys())->toBe([
        'unpaid',
        'partially_paid',
        'paid',
        'overdue',
        'overpaid',
        'unapplied',
        'invalid',
    ]);
});

it('exposes ordered aging bucket keys with labels', function () {
    expect(Catalog::agingBucketKeys())->toBe([
        'not_due',
        'd_1_30',
        'd_31_60',
        'd_61_90',
        'd_90_plus',
    ])
        ->and(Catalog::agingBucketLabel('d_1_30'))->toBe('Quá hạn 1-30 ngày')
        ->and(Catalog::balanceStateLabel('overpaid'))->toBe('Thanh toán dư');
});
