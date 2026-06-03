<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Department;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\FormTarget;
use App\Models\FormVersion;
use App\Models\QueryTicket;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->assignee = User::factory()->create();
    $this->campus = Campus::factory()->create(['code' => 'HN']);
    $this->otherCampus = Campus::factory()->create(['code' => 'HCM']);

    $this->studentHq = Department::factory()->create([
        'code' => 'HQ',
        'name' => 'Student HQ',
    ]);
    $this->academicService = Department::factory()->create([
        'code' => 'ACA',
        'name' => 'Academic Service',
    ]);
    $this->otherDepartment = Department::factory()->create([
        'code' => 'OPS',
        'name' => 'Operations',
    ]);
    Department::factory()->create([
        'code' => 'OLD',
        'name' => 'Inactive Department',
        'is_active' => false,
    ]);

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    bindAdminQueryInboxPermissions(['view_queries', 'detail_queries']);
});

function bindAdminQueryInboxPermissions(array $permissions): void
{
    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')->andReturn($permissions);

    app()->forgetInstance(PermissionService::class);
    app()->singleton(PermissionService::class, fn () => $permissionService);
}

function createAdminQueryInboxResponse(
    object $context,
    Department $department,
    ?Campus $campus = null,
    ?User $assignee = null,
    string $status = QueryTicket::STATUS_OPEN,
): FormResponse {
    $campus ??= $context->campus;

    $form = Form::create([
        'code' => 'QUERY-'.uniqid('', false),
        'type' => 'query',
        'title' => "Query {$department->code}",
        'status' => 'active',
        'created_by' => $context->user->id,
    ]);

    $version = FormVersion::create([
        'form_id' => $form->id,
        'version_no' => 1,
        'is_published' => true,
        'effective_from' => now()->subDay(),
    ]);

    $target = FormTarget::create([
        'form_id' => $form->id,
        'form_version_id' => $version->id,
        'campus_id' => $campus->id,
        'scope_type' => 'department',
        'scope_id' => $department->id,
        'status' => 'active',
        'start_at' => now()->subDay(),
        'submission_limit_per_user' => 1,
        'is_mandatory' => false,
    ]);

    $response = FormResponse::create([
        'form_id' => $form->id,
        'form_version_id' => $version->id,
        'form_target_id' => $target->id,
        'campus_id' => $campus->id,
        'target_scope_type' => 'department',
        'target_scope_id' => $department->id,
        'anonymized' => false,
        'status' => 'submitted',
        'query_status' => $status,
        'assigned_to_user_id' => $assignee?->id,
        'origin' => 'web',
        'submitted_at' => now(),
    ]);

    QueryTicket::create([
        'response_id' => $response->id,
        'status' => $status,
        'priority' => QueryTicket::PRIORITY_NORMAL,
        'assigned_to_user_id' => $assignee?->id,
    ]);

    return $response;
}

it('shows all current campus query responses without logged-in user department filtering', function () {
    $hqResponse = createAdminQueryInboxResponse($this, $this->studentHq, assignee: $this->assignee);
    $academicResponse = createAdminQueryInboxResponse($this, $this->academicService);
    $otherDepartmentResponse = createAdminQueryInboxResponse($this, $this->otherDepartment);
    $otherCampusResponse = createAdminQueryInboxResponse($this, $this->studentHq, $this->otherCampus);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('forms.admin.inbox.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Forms/Queries/Inbox')
            ->where('tickets.total', 3)
            ->where('tickets.data', function ($tickets) use ($hqResponse, $academicResponse, $otherDepartmentResponse, $otherCampusResponse) {
                $ids = collect($tickets)->pluck('id');

                return $ids->contains($hqResponse->id)
                    && $ids->contains($academicResponse->id)
                    && $ids->contains($otherDepartmentResponse->id)
                    && ! $ids->contains($otherCampusResponse->id);
            })
            ->where('departments', fn ($departments) => collect($departments)->pluck('code')->all() === ['HQ', 'ACA'])
        );
});

it('uses department selection only as a convenience filter', function () {
    $hqResponse = createAdminQueryInboxResponse($this, $this->studentHq);
    $academicResponse = createAdminQueryInboxResponse($this, $this->academicService);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('forms.admin.inbox.index', [
            'department_id' => $this->academicService->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Forms/Queries/Inbox')
            ->where('tickets.total', 1)
            ->where('tickets.data', function ($tickets) use ($hqResponse, $academicResponse) {
                $ids = collect($tickets)->pluck('id');

                return $ids->contains($academicResponse->id)
                    && ! $ids->contains($hqResponse->id);
            })
            ->where('filters.department_id', (string) $this->academicService->id)
        );
});

it('opens details for a current campus query from another department', function () {
    $response = createAdminQueryInboxResponse($this, $this->academicService, assignee: $this->assignee);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('forms.admin.inbox.show', $response))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Forms/Queries/Detail')
            ->where('ticket.id', $response->id)
            ->where('canAssign', false)
        );
});

it('allows a detail-permitted inbox user to update status for another department query', function () {
    $response = createAdminQueryInboxResponse($this, $this->academicService, assignee: $this->assignee);

    actingAs($this->user)
        ->withSession(['_token' => 'test-token', 'current_campus_id' => $this->campus->id])
        ->post(route('forms.admin.inbox.status.update', $response), [
            '_token' => 'test-token',
            'status' => QueryTicket::STATUS_ANSWERED,
        ])
        ->assertRedirect();

    expect($response->fresh()->query_status)->toBe(QueryTicket::STATUS_ANSWERED);
    expect($response->queryTicket()->first()->status)->toBe(QueryTicket::STATUS_ANSWERED);
});

it('does not expose non-query responses through the inbox detail route', function () {
    $response = createAdminQueryInboxResponse($this, $this->studentHq);
    $response->form->update(['type' => 'survey']);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('forms.admin.inbox.show', $response))
        ->assertNotFound();
});
