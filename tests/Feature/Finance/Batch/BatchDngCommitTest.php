<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Finance\Actions\CreateBatchDngFromChargesAction;
use App\Modules\Finance\Queries\Batch\AssembleBatchDngPreviewQuery;
use App\Modules\Finance\Services\Batch\BatchPreviewTokenService;
use App\Modules\Finance\Support\Batch\BatchJobType;
use App\Modules\Finance\Support\Batch\BatchPreviewLine;

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create(['is_active' => true]);
    $this->user = User::factory()->create();
    $this->key = 'dng:student:1:fee:tuition';
    $this->base = [
        'due_date' => now()->addWeek()->toDateString(),
        'description' => 'Học phí kỳ', 'estimate_time' => '3d',
    ];
});

function stubDngAssembler(string $key, array $payload, array $display = []): void
{
    $stub = Mockery::mock(AssembleBatchDngPreviewQuery::class);
    $stub->shouldReceive('handle')->andReturn([
        'lines' => [new BatchPreviewLine($key, $payload, $display)],
        'summary' => [],
    ]);
    app()->instance(AssembleBatchDngPreviewQuery::class, $stub);
}

function stubDngAction(): void
{
    $action = Mockery::mock(CreateBatchDngFromChargesAction::class);
    $action->shouldReceive('handle')->once()->andReturn(['created' => 1, 'failed' => 0, 'errors' => []]);
    app()->instance(CreateBatchDngFromChargesAction::class, $action);
}

it('allows the DNG commit without void_finance_charges because conflicts fail closed', function () {
    grantFinance($this->user, ['create_finance_payments'], $this->campus);

    $token = app(BatchPreviewTokenService::class)->issue(
        (int) $this->user->id,
        BatchJobType::DngPush,
        ['dng_fee_type' => 'HP', 'semester_id' => $this->semester->id, 'scope' => []],
        [new BatchPreviewLine($this->key, ['net' => 500000.0], [])],
    );
    stubDngAssembler($this->key, ['net' => 500000.0]);
    stubDngAction();

    $this->actingAs($this->user)
        ->withSession(financeWebSession($this->campus))
        ->from(route('finance.batch-studio.dng'))
        ->post(route('finance.batch-studio.dng.commit'), financePostPayload(array_merge($this->base, [
            'preview_token' => $token, 'selected_keys' => [$this->key],
        ])))
        ->assertSessionHasNoErrors();
});

it('caps the batch at 100 students', function () {
    grantFinance($this->user, ['create_finance_payments', 'void_finance_charges'], $this->campus);

    $keys = collect(range(1, 101))->map(fn ($i) => "dng:student:$i:fee:tuition")->all();

    $this->actingAs($this->user)
        ->withSession(financeWebSession($this->campus))
        ->from(route('finance.batch-studio.dng'))
        ->post(route('finance.batch-studio.dng.commit'), financePostPayload(array_merge($this->base, [
            'preview_token' => 'x', 'selected_keys' => $keys,
        ])))
        ->assertSessionHasErrors('selected_keys');
});

it('blocks the commit when the re-resolved DNG line drifted', function () {
    grantFinance($this->user, ['create_finance_payments', 'void_finance_charges'], $this->campus);

    $token = app(BatchPreviewTokenService::class)->issue(
        (int) $this->user->id,
        BatchJobType::DngPush,
        ['dng_fee_type' => 'HP', 'semester_id' => $this->semester->id, 'scope' => []],
        [new BatchPreviewLine($this->key, ['net' => 500000.0], [])],
    );
    stubDngAssembler($this->key, ['net' => 700000.0]);

    $this->actingAs($this->user)
        ->withSession(financeWebSession($this->campus))
        ->from(route('finance.batch-studio.dng'))
        ->post(route('finance.batch-studio.dng.commit'), financePostPayload(array_merge($this->base, [
            'preview_token' => $token, 'selected_keys' => [$this->key],
        ])))
        ->assertSessionHasErrors('preview_token');
});

it('blocks selected DNG rows that still require active-request resolution', function () {
    grantFinance($this->user, ['create_finance_payments'], $this->campus);

    $token = app(BatchPreviewTokenService::class)->issue(
        (int) $this->user->id,
        BatchJobType::DngPush,
        ['dng_fee_type' => 'HP', 'semester_id' => $this->semester->id, 'scope' => []],
        [new BatchPreviewLine($this->key, ['net' => 500000.0], [])],
    );
    stubDngAssembler($this->key, ['net' => 500000.0], ['diff' => 'skip']);

    $this->actingAs($this->user)
        ->withSession(financeWebSession($this->campus))
        ->from(route('finance.batch-studio.dng'))
        ->post(route('finance.batch-studio.dng.commit'), financePostPayload(array_merge($this->base, [
            'preview_token' => $token, 'selected_keys' => [$this->key],
        ])))
        ->assertSessionHasErrors('selected_keys');
});
