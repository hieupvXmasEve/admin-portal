<?php

declare(strict_types=1);

use App\Models\Campus;
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