<?php

declare(strict_types=1);

use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Merchandise\Models\GoldTransaction;
use App\Services\GoldService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->gold = app(GoldService::class);
    $semester = Semester::factory()->create();
    $this->student = Student::factory()->create([
        'intake' => $semester->id,
        'intake_semester_id' => $semester->id,
    ]);
});

/** Sum of the ledger for a student, as the reconciliation invariant sees it. */
function ledgerSum(Student $student): int
{
    return (int) GoldTransaction::where('student_id', $student->id)->sum('amount');
}

it('adds gold and records a balance snapshot', function () {
    $tx = $this->gold->addGold($this->student, 100, GoldTransaction::SOURCE_EVENT, 1, 'reward');

    expect($this->gold->getBalance($this->student))->toBe(100)
        ->and($tx->amount)->toBe(100)
        ->and($tx->balance_before)->toBe(0)
        ->and($tx->balance_after)->toBe(100)
        ->and($tx->type)->toBe(GoldTransaction::TYPE_EARN);
});

it('deducts gold as a negative entry', function () {
    $this->gold->addGold($this->student, 100, GoldTransaction::SOURCE_EVENT, 1, 'reward');
    $tx = $this->gold->deductGold($this->student, 40, GoldTransaction::SOURCE_REDEMPTION_ORDER, 2, 'spend');

    expect($this->gold->getBalance($this->student))->toBe(60)
        ->and($tx->amount)->toBe(-40)
        ->and($tx->balance_before)->toBe(100)
        ->and($tx->balance_after)->toBe(60);
});

it('refuses to deduct more than the balance and leaves state untouched', function () {
    $this->gold->addGold($this->student, 30, GoldTransaction::SOURCE_EVENT, 1, 'reward');

    expect(fn () => $this->gold->deductGold($this->student, 50, GoldTransaction::SOURCE_REDEMPTION_ORDER, 2))
        ->toThrow(InvalidArgumentException::class);

    expect($this->gold->getBalance($this->student))->toBe(30)
        ->and(GoldTransaction::where('student_id', $this->student->id)->where('amount', '<', 0)->count())->toBe(0);
});

it('records the acting staff user on a manual adjustment', function () {
    $staff = User::factory()->create();
    $tx = $this->gold->adjustBalance($this->student, 25, 'manual grant', performedBy: $staff->id);

    expect($tx->performed_by)->toBe($staff->id)
        ->and($tx->source_id)->toBeNull()
        ->and($this->gold->getBalance($this->student))->toBe(25);
});

it('forbids a manual adjustment that would push the balance below zero', function () {
    $this->gold->addGold($this->student, 10, GoldTransaction::SOURCE_EVENT, 1);

    expect(fn () => $this->gold->adjustBalance($this->student, -50, 'over-burn'))
        ->toThrow(InvalidArgumentException::class);

    expect($this->gold->getBalance($this->student))->toBe(10);
});

it('writes off a shortfall without moving the balance', function () {
    $this->gold->addGold($this->student, 10, GoldTransaction::SOURCE_EVENT, 1);
    $tx = $this->gold->writeOff($this->student, 40, GoldTransaction::SOURCE_EVENT, 1, 'unreclaimable');

    expect($tx->type)->toBe(GoldTransaction::TYPE_WRITE_OFF)
        ->and($tx->amount)->toBe(0)
        ->and($this->gold->getBalance($this->student))->toBe(10)
        ->and($tx->notes)->toContain('40');
});

it('keeps wallet balance equal to the sum of the ledger (reconciliation invariant)', function () {
    $staff = User::factory()->create();
    $this->gold->addGold($this->student, 100, GoldTransaction::SOURCE_EVENT, 1);
    $this->gold->deductGold($this->student, 30, GoldTransaction::SOURCE_REDEMPTION_ORDER, 2);
    $this->gold->adjustBalance($this->student, 15, 'bonus', performedBy: $staff->id);
    $this->gold->deductGold($this->student, 5, GoldTransaction::SOURCE_REDEMPTION_ORDER, 3);
    $this->gold->writeOff($this->student, 999, GoldTransaction::SOURCE_EVENT, 1); // amount 0 — must not shift the sum

    expect($this->gold->getBalance($this->student))->toBe(ledgerSum($this->student))
        ->and($this->gold->getBalance($this->student))->toBe(80);
});

it('routes every balance-changing method through a row lock (concurrency guard)', function () {
    // RefreshDatabase cannot prove a lock contends (single connection), so the
    // guard is enforced structurally: each write method must lock the wallet
    // row before mutating it.
    $source = file_get_contents(app_path('Services/GoldService.php'));

    foreach (['addGold', 'deductGold', 'adjustBalance', 'writeOff'] as $method) {
        $body = substr($source, strpos($source, "function {$method}("));
        $body = substr($body, 0, strpos($body, "\n    public function ") ?: strlen($body));
        expect($body)->toContain('lockWalletForUpdate');
    }
});
