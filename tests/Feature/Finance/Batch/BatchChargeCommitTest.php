<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\FinanceCharge;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Finance\Queries\Batch\AssembleBatchChargePreviewQuery;
use App\Modules\Finance\Services\Batch\BatchPreviewTokenService;
use App\Modules\Finance\Support\Batch\BatchJobType;
use App\Modules\Finance\Support\Batch\BatchPreviewLine;

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create(['is_active' => true]);
    $this->user = User::factory()->create();
    grantFinance($this->user, ['create_finance_charges'], $this->campus);
    $this->key = 'charge:major:student:1:semester:'.$this->semester->id;
});

function stubChargeAssembler(string $key, array $payload): void
{
    $stub = Mockery::mock(AssembleBatchChargePreviewQuery::class);
    $stub->shouldReceive('handle')->andReturn([
        'lines' => [new BatchPreviewLine($key, $payload, [])],
        'summary' => [],
    ]);
    app()->instance(AssembleBatchChargePreviewQuery::class, $stub);
}

it('rejects an unknown or expired token (no scope to re-resolve against)', function () {
    stubChargeAssembler($this->key, ['net' => 500000.0]);

    $this->actingAs($this->user)
        ->withSession(financeWebSession($this->campus))
        ->from(route('finance.batch-studio.charges'))
        ->post(route('finance.batch-studio.charges.commit'), financePostPayload([
            'preview_token' => 'not-a-real-token',
            'selected_keys' => [$this->key],
        ]))
        ->assertSessionHasErrors('preview_token');
});

it('blocks the commit when the re-resolved line drifted from the issued hash', function () {
    $token = app(BatchPreviewTokenService::class)->issue(
        (int) $this->user->id,
        BatchJobType::ChargeGeneration,
        ['fee_category' => 'major', 'semester_id' => $this->semester->id, 'scope' => []],
        [new BatchPreviewLine($this->key, ['net' => 500000.0], [])],
    );
    stubChargeAssembler($this->key, ['net' => 999999.0]);

    $this->actingAs($this->user)
        ->withSession(financeWebSession($this->campus))
        ->from(route('finance.batch-studio.charges'))
        ->post(route('finance.batch-studio.charges.commit'), financePostPayload([
            'preview_token' => $token,
            'selected_keys' => [$this->key],
        ]))
        ->assertSessionHasErrors('preview_token');
});

it('commits the clean subset and flashes a job summary when nothing drifted', function () {
    $payload = ['net' => 500000.0];
    $token = app(BatchPreviewTokenService::class)->issue(
        (int) $this->user->id,
        BatchJobType::ChargeGeneration,
        ['fee_category' => 'major', 'semester_id' => $this->semester->id, 'scope' => []],
        [new BatchPreviewLine($this->key, $payload, [])],
    );
    stubChargeAssembler($this->key, $payload);

    $this->actingAs($this->user)
        ->withSession(financeWebSession($this->campus))
        ->from(route('finance.batch-studio.charges'))
        ->post(route('finance.batch-studio.charges.commit'), financePostPayload([
            'preview_token' => $token,
            'selected_keys' => [$this->key],
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('finance.batch-studio.charges'));
});

it('forbids the commit without create_finance_charges', function () {
    grantFinance($this->user, ['view_finance_batch_studio'], $this->campus);

    $this->actingAs($this->user)
        ->withSession(financeWebSession($this->campus))
        ->from(route('finance.batch-studio.charges'))
        ->post(route('finance.batch-studio.charges.commit'), financePostPayload([
            'preview_token' => 'x', 'selected_keys' => [$this->key],
        ]))
        ->assertForbidden();
});

it('forbids a major commit for an EGC-only operator after reading the trusted token scope', function () {
    grantFinance($this->user, ['generate_egc_finance_charges'], $this->campus);

    $token = app(BatchPreviewTokenService::class)->issue(
        (int) $this->user->id,
        BatchJobType::ChargeGeneration,
        ['fee_category' => 'major', 'semester_id' => $this->semester->id, 'scope' => []],
        [new BatchPreviewLine($this->key, ['net' => 500000.0], [])],
    );

    $this->actingAs($this->user)
        ->withSession(financeWebSession($this->campus))
        ->from(route('finance.batch-studio.charges'))
        ->post(route('finance.batch-studio.charges.commit'), financePostPayload([
            'preview_token' => $token,
            'selected_keys' => [$this->key],
        ]))
        ->assertForbidden();
});

it('allows an EGC-only operator to commit an unchanged EGC preview token', function () {
    grantFinance($this->user, ['generate_egc_finance_charges'], $this->campus);

    $key = 'charge:egc:student:1:semester:'.$this->semester->id;
    $payload = ['net' => 15000000.0];
    $token = app(BatchPreviewTokenService::class)->issue(
        (int) $this->user->id,
        BatchJobType::ChargeGeneration,
        ['fee_category' => 'egc', 'semester_id' => $this->semester->id, 'scope' => []],
        [new BatchPreviewLine($key, $payload, ['block_count' => 1])],
    );
    stubChargeAssembler($key, $payload);

    $this->actingAs($this->user)
        ->withSession(financeWebSession($this->campus))
        ->from(route('finance.batch-studio.charges'))
        ->post(route('finance.batch-studio.charges.commit'), financePostPayload([
            'preview_token' => $token,
            'selected_keys' => [$key],
            'block_overrides' => [$key => 1],
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('finance.batch-studio.charges'));
});

it('commits major charges for intake-major students', function () {
    $student = makeBatchHpStudent($this->campus, $this->semester, 'BS'.random_int(100000000, 999999999), 'intake_major');

    $preview = $this->actingAs($this->user)
        ->withHeaders(['X-CSRF-TOKEN' => BATCH_STUDIO_CSRF])
        ->postJson(route('finance.batch-studio.charges.preview'), [
            'fee_category' => 'major',
            'semester_id' => $this->semester->id,
            'scope' => ['filters' => ['search' => $student->student_id]],
        ])
        ->assertOk();

    $key = (string) $preview->json('data.lines.0.key');

    $this->actingAs($this->user)
        ->withSession(financeWebSession($this->campus))
        ->from(route('finance.batch-studio.charges'))
        ->post(route('finance.batch-studio.charges.commit'), financePostPayload([
            'preview_token' => $preview->json('data.preview_token'),
            'selected_keys' => [$key],
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('finance.batch-studio.charges'));

    expect(FinanceCharge::query()
        ->where('student_id', $student->id)
        ->where('semester_id', $this->semester->id)
        ->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)
        ->where('amount', 45000000)
        ->where('status', FinanceCharge::STATUS_ACTIVE)
        ->exists())->toBeTrue();
});

it('commits a non-academic preview through the non-academic charge action', function () {
    $student = makeBatchStartedStudent($this->campus, $this->semester, 'BS'.random_int(100000000, 999999999));

    $preview = $this->actingAs($this->user)
        ->withHeaders(['X-CSRF-TOKEN' => BATCH_STUDIO_CSRF])
        ->postJson(route('finance.batch-studio.charges.preview'), [
            'fee_category' => 'non_academic',
            'semester_id' => $this->semester->id,
            'scope' => [
                'filters' => [],
                'fee_type' => 'bhyt',
                'amount' => 450000,
                'note' => 'BHYT batch studio',
            ],
        ])
        ->assertOk();

    $key = (string) $preview->json('data.lines.0.key');

    $this->actingAs($this->user)
        ->withSession(financeWebSession($this->campus))
        ->from(route('finance.batch-studio.charges'))
        ->post(route('finance.batch-studio.charges.commit'), financePostPayload([
            'preview_token' => $preview->json('data.preview_token'),
            'selected_keys' => [$key],
        ]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('finance.batch-studio.charges'));

    expect(FinanceCharge::query()
        ->where('student_id', $student->id)
        ->where('semester_id', $this->semester->id)
        ->where('charge_type', 'bhyt')
        ->where('amount', 450000)
        ->where('status', FinanceCharge::STATUS_ACTIVE)
        ->exists())->toBeTrue();
});
