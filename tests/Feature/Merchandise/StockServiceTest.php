<?php

declare(strict_types=1);

use App\Modules\Merchandise\Models\MerchandiseVariant;
use App\Modules\Merchandise\Models\StockMovement;
use App\Modules\Merchandise\Support\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('increments stock and writes a movement with correct before/after', function () {
    $variant = MerchandiseVariant::factory()->create(['stock_quantity' => 5]);

    $movement = app(StockService::class)->adjustStock($variant, 10, StockMovement::TYPE_STOCK_IN);

    expect($movement->quantity_before)->toBe(5)
        ->and($movement->quantity_after)->toBe(15)
        ->and($movement->change)->toBe(10)
        ->and($variant->fresh()->stock_quantity)->toBe(15);
});

it('decrements stock and writes a movement with correct before/after', function () {
    $variant = MerchandiseVariant::factory()->create(['stock_quantity' => 10]);

    $movement = app(StockService::class)->adjustStock($variant, -4, StockMovement::TYPE_MANUAL_DECREASE);

    expect($movement->quantity_before)->toBe(10)
        ->and($movement->quantity_after)->toBe(6)
        ->and($variant->fresh()->stock_quantity)->toBe(6);
});

it('throws and leaves stock/movements unchanged when decrementing below available', function () {
    $variant = MerchandiseVariant::factory()->create(['stock_quantity' => 2]);

    expect(fn () => app(StockService::class)->adjustStock($variant, -5, StockMovement::TYPE_MANUAL_DECREASE))
        ->toThrow(InvalidArgumentException::class, 'Insufficient stock');

    expect($variant->fresh()->stock_quantity)->toBe(2);
    expect(StockMovement::where('merchandise_variant_id', $variant->id)->count())->toBe(0);

    // ponytail: RefreshDatabase is single-connection/sequential, so this test
    // cannot exercise real concurrent-request races. The lockForUpdate +
    // conditional `WHERE stock_quantity >= ?` update inside StockService is
    // the actual race guard (mirrors Phase 1's GoldService pattern) — this
    // test only proves the sequential guard logic is correct.
});

it('rejects an unknown movement type', function () {
    $variant = MerchandiseVariant::factory()->create(['stock_quantity' => 2]);

    expect(fn () => app(StockService::class)->adjustStock($variant, 1, 'not_a_real_type'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a zero change', function () {
    $variant = MerchandiseVariant::factory()->create(['stock_quantity' => 2]);

    expect(fn () => app(StockService::class)->adjustStock($variant, 0, StockMovement::TYPE_STOCK_IN))
        ->toThrow(InvalidArgumentException::class);
});
