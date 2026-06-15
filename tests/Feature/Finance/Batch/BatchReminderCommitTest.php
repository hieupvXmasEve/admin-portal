<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\User;
use App\Modules\Finance\Queries\Batch\AssembleBatchReminderPreviewQuery;
use App\Modules\Finance\Services\Batch\BatchPreviewTokenService;
use App\Modules\Finance\Support\Batch\BatchJobType;
use App\Modules\Finance\Support\Batch\BatchPreviewLine;

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create(['is_active' => true]);
    $this->user = User::factory()->create();
    $this->key = 'reminder:invoice:1';
});

function stubReminderAssembler(string $key, array $payload): void
{
    $stub = Mockery::mock(AssembleBatchReminderPreviewQuery::class);
    $stub->shouldReceive('handle')->andReturn([
        'lines' => [new BatchPreviewLine($key, $payload, [])],
        'summary' => [],
    ]);
    app()->instance(AssembleBatchReminderPreviewQuery::class, $stub);
}

it('forbids the reminder commit without the due-calendar permission', function () {
    grantFinance($this->user, ['view_finance_batch_studio'], $this->campus);

    $this->actingAs($this->user)
        ->withSession(financeWebSession($this->campus))
        ->from(route('finance.batch-studio.reminders'))
        ->post(route('finance.batch-studio.reminders.commit'), financePostPayload([
            'preview_token' => 'x', 'selected_keys' => [$this->key],
        ]))
        ->assertForbidden();
});

it('blocks the reminder commit when a recipient was reminded since preview (drift = stale double-send)', function () {
    grantFinance($this->user, ['view_finance_operations_due_calendar'], $this->campus);

    $token = app(BatchPreviewTokenService::class)->issue(
        (int) $this->user->id,
        BatchJobType::Reminder,
        ['recipient' => 'student', 'semester_id' => $this->semester->id, 'scope' => []],
        [new BatchPreviewLine($this->key, ['item' => 'invoice:1', 'recipient' => 'student', 'last_reminder_at' => null], [])],
    );
    stubReminderAssembler($this->key, ['item' => 'invoice:1', 'recipient' => 'student', 'last_reminder_at' => '2026-06-15 09:00:00']);

    $this->actingAs($this->user)
        ->withSession(financeWebSession($this->campus))
        ->from(route('finance.batch-studio.reminders'))
        ->post(route('finance.batch-studio.reminders.commit'), financePostPayload([
            'preview_token' => $token, 'selected_keys' => [$this->key],
        ]))
        ->assertSessionHasErrors('preview_token');
});