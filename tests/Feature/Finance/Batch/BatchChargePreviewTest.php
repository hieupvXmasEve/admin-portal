<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Finance\Models\FinanceCharge;

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create(['is_active' => true]);
    $this->user = User::factory()->create();
});

it('returns a preview token and lines for an authorized major preview', function () {
    grantFinance($this->user, ['create_finance_charges', 'view_finance_all_campus'], $this->campus);

    $this->actingAs($this->user)
        ->withHeaders(['X-CSRF-TOKEN' => BATCH_STUDIO_CSRF])
        ->postJson(route('finance.batch-studio.charges.preview'), [
            'fee_category' => 'major',
            'semester_id' => $this->semester->id,
            'scope' => ['filters' => []],
        ])
        ->assertOk()
        ->assertJsonStructure(['data' => ['preview_token', 'lines', 'summary']]);
});

it('includes intake-major students in major charge preview', function () {
    grantFinance($this->user, ['create_finance_charges', 'view_finance_all_campus'], $this->campus);

    $student = makeBatchHpStudent($this->campus, $this->semester, 'BS'.random_int(100000000, 999999999), 'intake_major');

    $response = $this->actingAs($this->user)
        ->withHeaders(['X-CSRF-TOKEN' => BATCH_STUDIO_CSRF])
        ->postJson(route('finance.batch-studio.charges.preview'), [
            'fee_category' => 'major',
            'semester_id' => $this->semester->id,
            'scope' => ['filters' => ['search' => $student->student_id]],
        ])
        ->assertOk();

    expect(collect($response->json('data.lines'))->pluck('display.student_id')->all())
        ->toContain($student->student_id);
});

it('includes every eligible student in a major charge preview', function () {
    grantFinance($this->user, ['create_finance_charges', 'view_finance_all_campus'], $this->campus);
    $studentCodePrefix = 'BULK'.random_int(100000, 999999);

    $template = makeBatchHpStudent($this->campus, $this->semester, $studentCodePrefix.'01', 'intake_major');

    foreach (range(2, 21) as $index) {
        $studentCode = $studentCodePrefix.str_pad((string) $index, 2, '0', STR_PAD_LEFT);
        $student = $template->replicate();
        $student->student_id = $studentCode;
        $student->email = strtolower($studentCode).'@example.test';
        $student->national_id = null;
        $student->save();
    }

    $response = $this->actingAs($this->user)
        ->withHeaders(['X-CSRF-TOKEN' => BATCH_STUDIO_CSRF])
        ->postJson(route('finance.batch-studio.charges.preview'), [
            'fee_category' => 'major',
            'semester_id' => $this->semester->id,
            'scope' => ['filters' => ['search' => $studentCodePrefix]],
        ])
        ->assertOk();

    expect($response->json('data.lines'))->toHaveCount(21)
        ->and($response->json('data.summary.eligible_count'))->toBe(21);
});

it('ignores stale non-academic scope fields when previewing major charges', function () {
    grantFinance($this->user, ['create_finance_charges', 'view_finance_all_campus'], $this->campus);

    $this->actingAs($this->user)
        ->withHeaders(['X-CSRF-TOKEN' => BATCH_STUDIO_CSRF])
        ->postJson(route('finance.batch-studio.charges.preview'), [
            'fee_category' => 'major',
            'semester_id' => $this->semester->id,
            'scope' => [
                'filters' => [],
                'fee_type' => 'bhyt',
                'amount' => '',
                'due_date' => now()->addDays(30)->toDateString(),
                'note' => '',
            ],
        ])
        ->assertOk()
        ->assertJsonStructure(['data' => ['preview_token', 'lines', 'summary']]);
});

it('forbids an EGC preview without generate_egc_finance_charges', function () {
    grantFinance($this->user, ['create_finance_charges'], $this->campus);

    $this->actingAs($this->user)
        ->withHeaders(['X-CSRF-TOKEN' => BATCH_STUDIO_CSRF])
        ->postJson(route('finance.batch-studio.charges.preview'), [
            'fee_category' => 'egc',
            'semester_id' => $this->semester->id,
        ])
        ->assertForbidden();
});

it('allows an EGC-only operator to preview EGC charges', function () {
    grantFinance($this->user, ['generate_egc_finance_charges'], $this->campus);

    $this->actingAs($this->user)
        ->withHeaders(['X-CSRF-TOKEN' => BATCH_STUDIO_CSRF])
        ->postJson(route('finance.batch-studio.charges.preview'), [
            'fee_category' => 'egc',
            'semester_id' => $this->semester->id,
            'scope' => ['filters' => []],
        ])
        ->assertOk()
        ->assertJsonStructure(['data' => ['preview_token', 'lines', 'summary']]);
});

it('classifies voided same-semester EGC blocks as reissue updates in preview', function () {
    grantFinance($this->user, ['generate_egc_finance_charges', 'view_finance_all_campus'], $this->campus);

    $student = makeBatchEgcStudent($this->campus, $this->semester, 'EGC'.random_int(100000000, 999999999));
    seedBatchEgcBlockCharge($student, $this->semester, 1, 1, FinanceCharge::STATUS_VOID, 'void');
    seedBatchEgcBlockCharge($student, $this->semester, 2, 2, FinanceCharge::STATUS_VOID, 'void');

    $response = $this->actingAs($this->user)
        ->withHeaders(['X-CSRF-TOKEN' => BATCH_STUDIO_CSRF])
        ->postJson(route('finance.batch-studio.charges.preview'), [
            'fee_category' => 'egc',
            'semester_id' => $this->semester->id,
            'scope' => ['filters' => ['search' => $student->student_id]],
        ])
        ->assertOk();

    $line = $response->json('data.lines.0');

    expect($line['display']['student_id'])->toBe($student->student_id)
        ->and($line['display']['diff'])->toBe('update')
        ->and($line['display']['reason'])->toBe('egc_block_charge_voided')
        ->and($line['display']['block_count'])->toBe(2)
        ->and($response->json('data.summary.eligible_count'))->toBe(1);
});

it('forbids a major preview for an EGC-only operator', function () {
    grantFinance($this->user, ['generate_egc_finance_charges'], $this->campus);

    $this->actingAs($this->user)
        ->withHeaders(['X-CSRF-TOKEN' => BATCH_STUDIO_CSRF])
        ->postJson(route('finance.batch-studio.charges.preview'), [
            'fee_category' => 'major',
            'semester_id' => $this->semester->id,
            'scope' => ['filters' => []],
        ])
        ->assertForbidden();
});

it('validates required non-academic scope fields before running preview', function () {
    grantFinance($this->user, ['create_finance_charges'], $this->campus);

    $this->actingAs($this->user)
        ->withHeaders(['X-CSRF-TOKEN' => BATCH_STUDIO_CSRF])
        ->postJson(route('finance.batch-studio.charges.preview'), [
            'fee_category' => 'non_academic',
            'semester_id' => $this->semester->id,
            'scope' => ['filters' => []],
        ])
        ->assertUnprocessable()
        ->assertJsonFragment(['field' => 'scope.fee_type'])
        ->assertJsonFragment(['field' => 'scope.amount'])
        ->assertJsonMissing(['field' => 'scope.due_date']);
});

it('previews non-academic charges without requiring a charge due date', function () {
    grantFinance($this->user, ['create_finance_charges'], $this->campus);

    makeBatchStartedStudent($this->campus, $this->semester, 'BS'.random_int(100000000, 999999999));
    $existing = makeBatchStartedStudent($this->campus, $this->semester, 'BS'.random_int(100000000, 999999999));
    seedBatchActiveCharge($existing, $this->semester, 'bhyt');

    $response = $this->actingAs($this->user)
        ->withHeaders(['X-CSRF-TOKEN' => BATCH_STUDIO_CSRF])
        ->postJson(route('finance.batch-studio.charges.preview'), [
            'fee_category' => 'non_academic',
            'semester_id' => $this->semester->id,
            'scope' => [
                'filters' => [],
                'fee_type' => 'bhyt',
                'amount' => 500000,
            ],
        ])
        ->assertOk()
        ->assertJsonStructure(['data' => ['preview_token', 'lines', 'summary']]);

    $lines = collect($response->json('data.lines'));

    expect($lines)->toHaveCount(2)
        ->and($lines->pluck('display.diff')->sort()->values()->all())->toBe(['create', 'skip'])
        ->and($response->json('data.summary.new_charges_count'))->toBe(1)
        ->and($response->json('data.summary.skip_count'))->toBe(1);
});
