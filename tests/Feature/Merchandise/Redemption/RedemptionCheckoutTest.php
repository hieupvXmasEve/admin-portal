<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\GoldTransaction;
use App\Models\Merchandise;
use App\Models\MerchandiseVariant;
use App\Models\RedemptionOrder;
use App\Models\RedemptionOrderItem;
use App\Models\Semester;
use App\Models\StockMovement;
use App\Models\Student;
use App\Modules\Merchandise\Support\RedemptionService;
use App\Modules\Merchandise\Support\StockService;
use App\Services\GoldService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->gold = app(GoldService::class);
    $this->service = app(RedemptionService::class);

    $this->campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $this->student = Student::factory()->create([
        'campus_id' => $this->campus->id,
        'intake' => $semester->id,
        'intake_semester_id' => $semester->id,
    ]);

    $this->merchandise = Merchandise::factory()->create(['gold_price' => 50]);
    $this->variant = MerchandiseVariant::factory()->create([
        'merchandise_id' => $this->merchandise->id,
        'campus_id' => $this->campus->id,
        'stock_quantity' => 10,
        'is_active' => true,
    ]);
});

function fundWallet(GoldService $gold, Student $student, int $amount): void
{
    $gold->addGold($student, $amount, GoldTransaction::SOURCE_EVENT, 1, 'test funding');
}

it('creates an order, deducts gold, decrements stock, and snapshots the line item', function () {
    fundWallet($this->gold, $this->student, 200);

    $order = $this->service->createOrder(
        $this->student,
        [['variant_id' => $this->variant->id, 'quantity' => 2]],
        RedemptionOrder::METHOD_PICKUP,
        null,
        null,
        null,
    );

    expect($order->status)->toBe(RedemptionOrder::STATUS_PENDING_REVIEW)
        ->and($order->total_gold)->toBe(100)
        ->and($order->campus_id)->toBe($this->campus->id)
        ->and($this->gold->getBalance($this->student))->toBe(100)
        ->and($this->variant->fresh()->stock_quantity)->toBe(8);

    $item = RedemptionOrderItem::where('redemption_order_id', $order->id)->firstOrFail();
    expect($item->merchandise_name)->toBe($this->merchandise->name)
        ->and($item->gold_price_each)->toBe(50)
        ->and($item->line_total)->toBe(100)
        ->and($item->quantity)->toBe(2);

    $this->assertDatabaseHas('gold_transactions', [
        'student_id' => $this->student->id,
        'type' => GoldTransaction::TYPE_REDEMPTION,
        'source_id' => $order->id,
        'amount' => -100,
    ]);

    $this->assertDatabaseHas('stock_movements', [
        'merchandise_variant_id' => $this->variant->id,
        'type' => StockMovement::TYPE_REDEMPTION_OUT,
        'redemption_order_id' => $order->id,
        'change' => -2,
    ]);
});

it('rejects checkout with insufficient Gold and creates nothing', function () {
    fundWallet($this->gold, $this->student, 10);

    expect(fn () => $this->service->createOrder(
        $this->student,
        [['variant_id' => $this->variant->id, 'quantity' => 1]],
        RedemptionOrder::METHOD_PICKUP,
        null,
        null,
        null,
    ))->toThrow(InvalidArgumentException::class);

    expect(RedemptionOrder::count())->toBe(0)
        ->and($this->gold->getBalance($this->student))->toBe(10)
        ->and($this->variant->fresh()->stock_quantity)->toBe(10);
});

it('rejects checkout for a variant at a different campus and creates nothing', function () {
    fundWallet($this->gold, $this->student, 200);
    $otherCampus = Campus::factory()->create();
    $variant = MerchandiseVariant::factory()->create([
        'merchandise_id' => $this->merchandise->id,
        'campus_id' => $otherCampus->id,
        'stock_quantity' => 10,
    ]);

    expect(fn () => $this->service->createOrder(
        $this->student,
        [['variant_id' => $variant->id, 'quantity' => 1]],
        RedemptionOrder::METHOD_PICKUP,
        null,
        null,
        null,
    ))->toThrow(InvalidArgumentException::class);

    expect(RedemptionOrder::count())->toBe(0)
        ->and($this->gold->getBalance($this->student))->toBe(200);
});

it('rolls back the order, the Gold deduction, and every stock decrement together when a later line fails', function () {
    fundWallet($this->gold, $this->student, 500);

    $secondVariant = MerchandiseVariant::factory()->create([
        'merchandise_id' => $this->merchandise->id,
        'campus_id' => $this->campus->id,
        // Explicit, distinct from $this->variant's random color/size so this
        // never collides with merchandise_variants_unique_combo.
        'color' => 'rollback-test-color',
        'size' => 'rollback-test-size',
        'stock_quantity' => 5,
    ]);

    // Real StockService succeeds for the first (lower id) variant, then a
    // stand-in throws for the second — simulating a failure that happens
    // AFTER the order row, items, and Gold deduction already ran inside the
    // same transaction. Everything must still unwind together.
    $real = app(StockService::class);
    $calls = 0;
    $flaky = Mockery::mock(StockService::class);
    $flaky->shouldReceive('adjustStock')->andReturnUsing(function (...$args) use ($real, &$calls) {
        $calls++;
        if ($calls === 2) {
            throw new InvalidArgumentException('Simulated failure on the second stock decrement');
        }

        return $real->adjustStock(...$args);
    });
    app()->forgetInstance(StockService::class);
    app()->singleton(StockService::class, fn () => $flaky);
    $service = app(RedemptionService::class);

    expect(fn () => $service->createOrder(
        $this->student,
        [
            ['variant_id' => $this->variant->id, 'quantity' => 1],
            ['variant_id' => $secondVariant->id, 'quantity' => 1],
        ],
        RedemptionOrder::METHOD_PICKUP,
        null,
        null,
        null,
    ))->toThrow(InvalidArgumentException::class);

    expect(RedemptionOrder::count())->toBe(0)
        ->and(RedemptionOrderItem::count())->toBe(0)
        ->and($this->gold->getBalance($this->student))->toBe(500)
        ->and($this->variant->fresh()->stock_quantity)->toBe(10)
        ->and($secondVariant->fresh()->stock_quantity)->toBe(5);
});

it('replays the same order on a repeated Idempotency-Key instead of double-spending', function () {
    fundWallet($this->gold, $this->student, 200);

    $first = $this->service->createOrder(
        $this->student,
        [['variant_id' => $this->variant->id, 'quantity' => 1]],
        RedemptionOrder::METHOD_PICKUP,
        null,
        'idem-key-1',
        null,
    );

    $second = $this->service->createOrder(
        $this->student,
        [['variant_id' => $this->variant->id, 'quantity' => 1]],
        RedemptionOrder::METHOD_PICKUP,
        null,
        'idem-key-1',
        null,
    );

    expect($second->id)->toBe($first->id)
        ->and(RedemptionOrder::count())->toBe(1)
        ->and($this->gold->getBalance($this->student))->toBe(150)
        ->and($this->variant->fresh()->stock_quantity)->toBe(9);
});

it('keeps the order item snapshot unchanged after the merchandise is renamed, repriced, and archived', function () {
    fundWallet($this->gold, $this->student, 200);

    $order = $this->service->createOrder(
        $this->student,
        [['variant_id' => $this->variant->id, 'quantity' => 1]],
        RedemptionOrder::METHOD_PICKUP,
        null,
        null,
        null,
    );

    $item = RedemptionOrderItem::where('redemption_order_id', $order->id)->firstOrFail();
    expect($item->merchandise_name)->toBe($this->merchandise->name)
        ->and($item->gold_price_each)->toBe(50);

    $this->merchandise->update([
        'name' => 'Renamed Product',
        'gold_price' => 9999,
        'status' => Merchandise::STATUS_ARCHIVED,
    ]);

    $item->refresh();
    expect($item->merchandise_name)->not->toBe('Renamed Product')
        ->and($item->gold_price_each)->toBe(50)
        ->and($item->line_total)->toBe(50);
});
