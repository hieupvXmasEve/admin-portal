<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\User;
use App\Modules\Finance\Dng\Models\DngWebhookEvent;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);
    $user = User::factory()->create();
    $mock = Mockery::mock(CampusPermissionReader::class);
    $mock->shouldReceive('permissionCodesForUserId')->andReturn([
        'view_finance_cockpit',
        'view_finance_dng_webhook_events',
    ]);
    app()->singleton(CampusPermissionReader::class, fn () => $mock);
    $this->user = $user;
});

it('returns top rows for the webhook queue with a retry action', function () {
    DngWebhookEvent::create([
        'processing_status' => DngWebhookEvent::STATUS_MISMATCH,
        'event_type' => 'payment',
        'payload' => ['id' => 1],
        'payload_hash' => 'cockpit-test-hash-'.uniqid(),
    ]);

    actingAs($this->user)->getJson('/finance/cockpit/queue/webhook_errors')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.queue', 'webhook_errors')
        ->assertJsonPath('data.rows.0.primary_action.kind', 'retry_webhook');
});

it('returns no webhook rows when the user lacks the webhook permission', function () {
    DngWebhookEvent::create([
        'processing_status' => DngWebhookEvent::STATUS_MISMATCH,
        'event_type' => 'payment',
        'payload' => ['id' => 1],
        'payload_hash' => 'cockpit-denied-hash-'.uniqid(),
    ]);

    $user = User::factory()->create();
    $mock = Mockery::mock(CampusPermissionReader::class);
    $mock->shouldReceive('permissionCodesForUserId')->andReturn([
        'view_finance_cockpit',
        'view_finance_operations_due_calendar',
    ]);
    app()->singleton(CampusPermissionReader::class, fn () => $mock);

    actingAs($user)->getJson('/finance/cockpit/queue/webhook_errors')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.rows', []);
});
