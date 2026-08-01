<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\GoldTransaction;
use App\Models\Merchandise;
use App\Models\MerchandiseVariant;
use App\Models\RedemptionOrder;
use App\Models\RedemptionOrderItem;
use App\Models\Role;
use App\Models\Semester;
use App\Models\StockMovement;
use App\Models\Student;
use App\Models\User;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Database\Seeders\InitialSetup\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

const MERCHANDISE_REPORT_CSRF = 'merchandise-report-test-csrf';

uses(RefreshDatabase::class);

function grantMerchandiseReportRoleAtCampus(User $user, Campus $campus, string $roleCode): void
{
    $role = Role::where('code', $roleCode)->firstOrFail();

    DB::table('campus_user_roles')->insert([
        'user_id' => $user->id,
        'campus_id' => $campus->id,
        'role_id' => $role->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $user->id);
}

/**
 * Creates a redemption order with one item, plus the gold_transactions rows
 * a real checkout/refund would leave behind — the ledger is the reconciliation
 * target for the Gold used/refunded report, so the test seeds the ledger
 * exactly the way RedemptionService does (redemption debit always; a
 * redemption_refund credit only when the order was reversed).
 */
function seedReportOrder(Student $student, Campus $campus, MerchandiseVariant $variant, string $status, int $totalGold, int $quantity = 1): RedemptionOrder
{
    $order = RedemptionOrder::factory()->create([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'status' => $status,
        'total_gold' => $totalGold,
    ]);

    RedemptionOrderItem::factory()->create([
        'redemption_order_id' => $order->id,
        'merchandise_variant_id' => $variant->id,
        'merchandise_name' => $variant->merchandise->name,
        'quantity' => $quantity,
        'gold_price_each' => intdiv($totalGold, $quantity),
        'line_total' => $totalGold,
    ]);

    GoldTransaction::create([
        'student_id' => $student->id,
        'amount' => -$totalGold,
        'balance_before' => 1000,
        'balance_after' => 1000 - $totalGold,
        'type' => GoldTransaction::TYPE_REDEMPTION,
        'source_type' => GoldTransaction::SOURCE_REDEMPTION_ORDER,
        'source_id' => $order->id,
    ]);

    if (! in_array($status, RedemptionOrder::NON_REVERSED_STATUSES, true)) {
        GoldTransaction::create([
            'student_id' => $student->id,
            'amount' => $totalGold,
            'balance_before' => 1000 - $totalGold,
            'balance_after' => 1000,
            'type' => GoldTransaction::TYPE_REDEMPTION_REFUND,
            'source_type' => GoldTransaction::SOURCE_REDEMPTION_ORDER,
            'source_id' => $order->id,
        ]);
    }

    return $order;
}

beforeEach(function () {
    Cache::flush();
    $this->seed(RoleAndPermissionSeeder::class);

    $this->campus = Campus::factory()->create();

    $merchandiseA = Merchandise::factory()->create(['name' => 'Merch A']);
    $this->variantA = MerchandiseVariant::factory()->create([
        'merchandise_id' => $merchandiseA->id,
        'campus_id' => $this->campus->id,
        'stock_quantity' => 8,
    ]);

    $merchandiseB = Merchandise::factory()->create(['name' => 'Merch B']);
    $this->variantB = MerchandiseVariant::factory()->create([
        'merchandise_id' => $merchandiseB->id,
        'campus_id' => $this->campus->id,
        'stock_quantity' => 5,
    ]);

    $semester = Semester::factory()->create();
    $student = Student::factory()->create([
        'campus_id' => $this->campus->id,
        'intake' => $semester->id,
        'intake_semester_id' => $semester->id,
    ]);

    $this->collected = seedReportOrder($student, $this->campus, $this->variantA, RedemptionOrder::STATUS_COLLECTED, 30, 2);
    $this->shipped = seedReportOrder($student, $this->campus, $this->variantA, RedemptionOrder::STATUS_SHIPPED, 70, 1);
    RedemptionOrderItem::factory()->create([
        'redemption_order_id' => $this->shipped->id,
        'merchandise_variant_id' => $this->variantB->id,
        'merchandise_name' => 'Merch B',
        'quantity' => 1,
        'gold_price_each' => 20,
        'line_total' => 20,
    ]);
    $this->pendingReview = seedReportOrder($student, $this->campus, $this->variantB, RedemptionOrder::STATUS_PENDING_REVIEW, 20, 1);
    $this->readyForCollection = seedReportOrder($student, $this->campus, $this->variantA, RedemptionOrder::STATUS_READY_FOR_COLLECTION, 15, 1);
    $this->pickupOverdue = seedReportOrder($student, $this->campus, $this->variantA, RedemptionOrder::STATUS_PICKUP_OVERDUE, 25, 1);
    $this->rejected = seedReportOrder($student, $this->campus, $this->variantA, RedemptionOrder::STATUS_REJECTED, 40, 1);
    $this->cancelled = seedReportOrder($student, $this->campus, $this->variantB, RedemptionOrder::STATUS_CANCELLED, 10, 1);

    StockMovement::create([
        'merchandise_variant_id' => $this->variantA->id,
        'change' => -2,
        'quantity_before' => 10,
        'quantity_after' => 8,
        'type' => StockMovement::TYPE_MANUAL_DECREASE,
        'note' => 'shrinkage audit',
    ]);

    $this->staff = User::factory()->create();
    grantMerchandiseReportRoleAtCampus($this->staff, $this->campus, 'super_admin');

    session(['_token' => MERCHANDISE_REPORT_CSRF, 'current_campus_id' => $this->campus->id]);
});

it('reports order counts per status matching the seeded orders', function () {
    $response = test()->actingAs($this->staff)->getJson(route('merchandise.reports.data'));

    $response->assertOk();
    $counts = $response->json('data.orders_by_status.counts');

    expect($counts[RedemptionOrder::STATUS_COLLECTED])->toBe(1)
        ->and($counts[RedemptionOrder::STATUS_SHIPPED])->toBe(1)
        ->and($counts[RedemptionOrder::STATUS_PENDING_REVIEW])->toBe(1)
        ->and($counts[RedemptionOrder::STATUS_READY_FOR_COLLECTION])->toBe(1)
        ->and($counts[RedemptionOrder::STATUS_PICKUP_OVERDUE])->toBe(1)
        ->and($counts[RedemptionOrder::STATUS_REJECTED])->toBe(1)
        ->and($counts[RedemptionOrder::STATUS_CANCELLED])->toBe(1)
        ->and($counts[RedemptionOrder::STATUS_APPROVED])->toBe(0)
        ->and(array_sum($counts))->toBe(7);
});

it('lists the operational queues for pending review, ready for collection, pickup overdue, and shipped', function () {
    $response = test()->actingAs($this->staff)->getJson(route('merchandise.reports.data'));

    $queues = $response->json('data.orders_by_status.queues');

    expect(collect($queues[RedemptionOrder::STATUS_PENDING_REVIEW])->pluck('id')->all())->toBe([$this->pendingReview->id])
        ->and(collect($queues[RedemptionOrder::STATUS_READY_FOR_COLLECTION])->pluck('id')->all())->toBe([$this->readyForCollection->id])
        ->and(collect($queues[RedemptionOrder::STATUS_PICKUP_OVERDUE])->pluck('id')->all())->toBe([$this->pickupOverdue->id])
        ->and(collect($queues[RedemptionOrder::STATUS_SHIPPED])->pluck('id')->all())->toBe([$this->shipped->id]);
});

it('sums Gold used from non-reversed orders and Gold refunded from rejected/cancelled orders, reconciled against the ledger', function () {
    $response = test()->actingAs($this->staff)->getJson(route('merchandise.reports.data'));

    $summary = $response->json('data.gold_summary');

    // Business definition per report scope.
    expect($summary['gold_used'])->toBe(30 + 70 + 20 + 15 + 25)
        ->and($summary['gold_refunded'])->toBe(40 + 10);

    // Ledger reconciliation: sum of the 'redemption' debit for exactly the
    // non-reversed orders must equal gold_used, and the 'redemption_refund'
    // credit for exactly the rejected/cancelled orders must equal
    // gold_refunded. Every order also carries a 'redemption' debit even when
    // later reversed, so the source_id scoping below is load-bearing.
    $nonReversedIds = [$this->collected->id, $this->shipped->id, $this->pendingReview->id, $this->readyForCollection->id, $this->pickupOverdue->id];
    $refundedIds = [$this->rejected->id, $this->cancelled->id];

    $ledgerUsed = (int) GoldTransaction::query()
        ->where('type', GoldTransaction::TYPE_REDEMPTION)
        ->whereIn('source_id', $nonReversedIds)
        ->sum(DB::raw('ABS(amount)'));

    $ledgerRefunded = (int) GoldTransaction::query()
        ->where('type', GoldTransaction::TYPE_REDEMPTION_REFUND)
        ->whereIn('source_id', $refundedIds)
        ->sum('amount');

    expect($ledgerUsed)->toBe($summary['gold_used'])
        ->and($ledgerRefunded)->toBe($summary['gold_refunded']);
});

it('ranks most-redeemed merchandise by quantity from non-reversed order items only', function () {
    $response = test()->actingAs($this->staff)->getJson(route('merchandise.reports.data'));

    $ranking = $response->json('data.most_redeemed');

    // Merch A: 2 (collected) + 1 (shipped) + 1 (ready_for_collection) + 1 (pickup_overdue) = 5,
    // across 4 non-reversed orders. The rejected order's Merch A item (qty 1) is excluded.
    // Merch B: 1 (shipped) + 1 (pending_review) = 2, across 2 non-reversed orders. The
    // cancelled order's Merch B item (qty 1) is excluded.
    expect($ranking[0]['merchandise_name'])->toBe('Merch A')
        ->and($ranking[0]['total_quantity'])->toBe(5)
        ->and($ranking[0]['order_count'])->toBe(4)
        ->and($ranking[1]['merchandise_name'])->toBe('Merch B')
        ->and($ranking[1]['total_quantity'])->toBe(2)
        ->and($ranking[1]['order_count'])->toBe(2);
});

it('reports current stock per variant and recent stock movement history', function () {
    $response = test()->actingAs($this->staff)->getJson(route('merchandise.reports.data'));

    $variants = collect($response->json('data.stock.variants'));
    $movements = $response->json('data.stock.movements');

    expect($variants->firstWhere('id', $this->variantA->id)['stock_quantity'])->toBe(8)
        ->and($variants->firstWhere('id', $this->variantB->id)['stock_quantity'])->toBe(5)
        ->and($movements)->toHaveCount(1)
        ->and($movements[0]['change'])->toBe(-2)
        ->and($movements[0]['quantity_before'])->toBe(10)
        ->and($movements[0]['quantity_after'])->toBe(8);
});

it('filters queues to a single status when the status filter is set', function () {
    $response = test()->actingAs($this->staff)->getJson(route('merchandise.reports.data', ['status' => RedemptionOrder::STATUS_SHIPPED]));

    $queues = $response->json('data.orders_by_status.queues');

    expect(array_keys($queues))->toBe([RedemptionOrder::STATUS_SHIPPED]);
});
