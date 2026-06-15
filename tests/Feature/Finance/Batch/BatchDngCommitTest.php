<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
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

function stubDngAssembler(string $key, array $payload): void
{
    $stub = Mockery::mock(AssembleBatchDngPreviewQuery::class);
    $stub->shouldReceive('handle')->andReturn([
        'lines' => [new BatchPreviewLine($key, $payload, [])],
        'summary' => [],
    ]);
    app()->instance(AssembleBatchDngPreviewQuery::class, $stub);
}

it('forbids the DNG commit without void_finance_charges (rerun can void linked charges)', function () {
    grantFinance($this->user, ['create_finance_payments'], $this->campus);

    $this->actingAs($this->user)
        ->withSession(financeWebSession($this->campus))
        ->from(route('finance.batch-studio.dng'))
        ->post(route('finance.batch-studio.dng.commit'), financePostPayload(array_merge($this->base, [
            'preview_token' => 'x', 'selected_keys' => [$this->key],
        ])))
        ->assertForbidden();
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
        ['dng_fee_type' => 'tuition', 'semester_id' => $this->semester->id, 'scope' => []],
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