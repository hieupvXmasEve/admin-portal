<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\GoldTransaction;
use App\Models\Merchandise;
use App\Models\MerchandiseVariant;
use App\Models\RedemptionOrder;
use App\Models\Semester;
use App\Models\StockMovement;
use App\Models\Student;
use App\Modules\Merchandise\Exceptions\RedemptionStateConflictException;
use App\Modules\Merchandise\Support\RedemptionService;
use App\Services\GoldService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->gold = app(GoldService::class);
    $this->service = app(RedemptionService::class);

    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $this->student = Student::factory()->create([
        'campus_id' => $campus->id,
        'intake' => $semester->id,
        'intake_semester_id' => $semester->id,
    ]);

    $merchandise = Merchandise::factory()->create(['gold_price' => 50]);
    $this->variant = MerchandiseVariant::factory()->create([
        'merchandise_id' => $merchandise->id,
        'campus_id' => $campus->id,
        'stock_quantity' => 10,
    ]);

    $this->gold->addGold($this->student, 200, GoldTransaction::SOURCE_EVENT, 1, 'test funding');

    $this->order = $this->service->createOrder(
        $this->student,
        [['variant_id' => $this->variant->id, 'quantity' => 2]],
        RedemptionOrder::METHOD_PICKUP,
        null,
        null,
        null,
    );
});

it('refunds Gold and stock symmetrically on reject', function () {
    $balanceAfterCheckout = $this->gold->getBalance($this->student);
    $stockAfterCheckout = $this->variant->fresh()->stock_quantity;

    $order = $this->service->reject($this->order->id, 'Out of stock in warehouse', null);

    expect($order->status)->toBe(RedemptionOrder::STATUS_REJECTED)
        ->and($this->gold->getBalance($this->student))->toBe($balanceAfterCheckout + $this->order->total_gold)
        ->and($this->variant->fresh()->stock_quantity)->toBe($stockAfterCheckout + 2);

    $this->assertDatabaseHas('gold_transactions', [
        'student_id' => $this->student->id,
        'type' => GoldTransaction::TYPE_REDEMPTION_REFUND,
        'source_id' => $this->order->id,
        'amount' => $this->order->total_gold,
    ]);
});

it('blocks a second reject/refund on the same order via the state guard', function () {
    $this->service->reject($this->order->id, 'first reject', null);

    expect(fn () => $this->service->reject($this->order->id, 'second reject', null))
        ->toThrow(RedemptionStateConflictException::class);

    expect(
        GoldTransaction::where('source_id', $this->order->id)
            ->where('type', GoldTransaction::TYPE_REDEMPTION_REFUND)
            ->count()
    )->toBe(1)
        ->and(
            StockMovement::where('redemption_order_id', $this->order->id)
                ->where('type', StockMovement::TYPE_REDEMPTION_REFUND)
                ->count()
        )->toBe(1);
});

it('rejects a second redemption_refund ledger row for the same order at the DB level', function () {
    // Bypasses the RedemptionService state guard entirely to prove the
    // generated-column partial-unique index on gold_transactions is a real,
    // independent backstop — not just documentation.
    $this->gold->addGold(
        $this->student,
        $this->order->total_gold,
        GoldTransaction::SOURCE_REDEMPTION_ORDER,
        $this->order->id,
        'first refund',
        null,
        GoldTransaction::TYPE_REDEMPTION_REFUND,
    );

    expect(fn () => $this->gold->addGold(
        $this->student,
        $this->order->total_gold,
        GoldTransaction::SOURCE_REDEMPTION_ORDER,
        $this->order->id,
        'second refund attempt',
        null,
        GoldTransaction::TYPE_REDEMPTION_REFUND,
    ))->toThrow(QueryException::class);

    expect(
        GoldTransaction::where('source_id', $this->order->id)
            ->where('type', GoldTransaction::TYPE_REDEMPTION_REFUND)
            ->count()
    )->toBe(1);
});

it('walks the pickup happy path: approve -> ready for collection -> collected', function () {
    $order = $this->service->approve($this->order->id, null);
    expect($order->status)->toBe(RedemptionOrder::STATUS_APPROVED);

    $order = $this->service->setReadyForCollection($this->order->id, 'Front desk', Carbon::now()->addDays(7), null);
    expect($order->status)->toBe(RedemptionOrder::STATUS_READY_FOR_COLLECTION)
        ->and($order->collection_location)->toBe('Front desk');

    $order = $this->service->confirmCollected($this->order->id, null);
    expect($order->status)->toBe(RedemptionOrder::STATUS_COLLECTED)
        ->and($order->collected_at)->not->toBeNull();
});

it('rejects an illegal transition: cannot confirm collected from pending_review', function () {
    expect(fn () => $this->service->confirmCollected($this->order->id, null))
        ->toThrow(RedemptionStateConflictException::class);
});

it('rejects an illegal transition: cannot approve an already-approved order', function () {
    $this->service->approve($this->order->id, null);

    expect(fn () => $this->service->approve($this->order->id, null))
        ->toThrow(RedemptionStateConflictException::class);
});

it('handles a cancellation request: accept refunds and cancels', function () {
    $this->service->approve($this->order->id, null);
    $balanceBeforeCancel = $this->gold->getBalance($this->student);

    $order = $this->service->requestCancellation($this->order->id, 'Changed my mind', $this->student->id);
    expect($order->status)->toBe(RedemptionOrder::STATUS_CANCELLATION_REQUESTED);

    $order = $this->service->handleCancellation($this->order->id, true, 'Approved by staff', null);
    expect($order->status)->toBe(RedemptionOrder::STATUS_CANCELLED)
        ->and($this->gold->getBalance($this->student))->toBe($balanceBeforeCancel + $this->order->total_gold);
});

it('handles a cancellation request: reject reverts to the prior status', function () {
    $this->service->approve($this->order->id, null);
    $this->service->requestCancellation($this->order->id, 'Changed my mind', $this->student->id);

    $order = $this->service->handleCancellation($this->order->id, false, 'Already shipped, cannot cancel', null);

    expect($order->status)->toBe(RedemptionOrder::STATUS_APPROVED)
        ->and($order->cancellation_result)->toBe(RedemptionOrder::CANCELLATION_RESULT_REJECTED);
});

it('every transition locks the order row for update before mutating it (structural concurrency guard)', function () {
    // RefreshDatabase cannot prove cross-connection contention (RT-10) — the
    // guard is enforced structurally instead: every public transition method
    // must acquire the row lock via the shared transition() helper.
    $source = file_get_contents(app_path('Modules/Merchandise/Support/RedemptionService.php'));

    expect($source)->toContain('lockForUpdate()')
        ->and(substr_count($source, '$this->transition('))->toBeGreaterThanOrEqual(9);
});
