<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Department;
use App\Models\DepartmentMembership;
use App\Models\Role;
use App\Models\User;
use App\Modules\Engagement\Models\Form;
use App\Modules\Engagement\Models\FormResponse;
use App\Modules\Engagement\Models\FormTarget;
use App\Modules\Engagement\Models\FormVersion;
use App\Modules\Engagement\Models\QueryReply;
use App\Modules\Engagement\Models\QueryTicket;
use App\Modules\Upload\Models\UploadRecord;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use App\Shared\Contracts\Upload\FileUploadGateway;
use App\Shared\Contracts\Upload\StoredUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->assignee = User::factory()->create();
    $this->campus = Campus::factory()->create(['code' => 'HN']);
    $this->otherCampus = Campus::factory()->create(['code' => 'HCM']);
    $this->csrfToken = 'query-inbox-csrf';

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

    session([
        'current_campus_id' => $this->campus->id,
        '_token' => $this->csrfToken,
    ]);
    app()->singleton('campus', fn () => $this->campus);

    bindAdminQueryInboxPermissions(['view_queries', 'detail_queries']);
});

function bindAdminQueryInboxPermissions(array $permissions): void
{
    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')->andReturn($permissions);

    app()->forgetInstance(CampusPermissionReader::class);
    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);
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

function grantAdminRole(User $user, Campus $campus): void
{
    $adminRole = Role::factory()->create([
        'name' => 'Administrator',
        'code' => 'admin',
    ]);

    $user->campusRoles()->attach($adminRole, ['campus_id' => $campus->id]);
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

it('assigns a department query to an eligible staff member and records the assignment', function (): void {
    $response = createAdminQueryInboxResponse($this, $this->academicService);
    grantAdminRole($this->user, $this->campus);
    DepartmentMembership::factory()->create([
        'department_id' => $this->academicService->id,
        'user_id' => $this->assignee->id,
        'department_role' => 'staff',
        'is_active' => true,
    ]);

    actingAs($this->user)
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $this->csrfToken,
        ])
        ->post(route('forms.admin.inbox.assign', $response), [
            '_token' => $this->csrfToken,
            'assigned_to_user_id' => (string) $this->assignee->id,
            'note' => 'Please investigate this case.',
        ])
        ->assertRedirect();

    expect($response->fresh()->assigned_to_user_id)->toBe($this->assignee->id)
        ->and($response->fresh()->query_status)->toBe(QueryTicket::STATUS_PENDING)
        ->and($response->queryTicket()->firstOrFail()->assigned_to_user_id)->toBe($this->assignee->id)
        ->and($response->queryTicket()->firstOrFail()->status)->toBe(QueryTicket::STATUS_PENDING);

    $assignment = $response->assignments()->sole();

    expect($assignment->action)->toBe('assign')
        ->and($assignment->assigned_by_user_id)->toBe($this->user->id)
        ->and($assignment->to_assignee_user_id)->toBe($this->assignee->id)
        ->and($assignment->note)->toBe('Please investigate this case.');
});

it('rejects assignment by a staff member without department-head or administrator authority', function (): void {
    $response = createAdminQueryInboxResponse($this, $this->academicService);
    DepartmentMembership::factory()->create([
        'department_id' => $this->academicService->id,
        'user_id' => $this->assignee->id,
        'department_role' => 'staff',
        'is_active' => true,
    ]);

    actingAs($this->user)
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $this->csrfToken,
        ])
        ->post(route('forms.admin.inbox.assign', $response), [
            '_token' => $this->csrfToken,
            'assigned_to_user_id' => (string) $this->assignee->id,
        ])
        ->assertForbidden();

    expect($response->fresh()->assigned_to_user_id)->toBeNull();
    expect($response->assignments()->count())->toBe(0);
});

it('stores an official reply with an uploaded attachment and marks the ticket answered', function (): void {
    $response = createAdminQueryInboxResponse($this, $this->academicService);
    $ticketId = $response->queryTicket()->firstOrFail()->id;
    $uploadRecord = UploadRecord::factory()->create([
        'context' => 'form_attachment',
        'user_id' => $this->user->id,
    ]);

    // linkToQueryReply()'s real effect (writing upload_records.reply_id,
    // which the new UploadRecordReader read path depends on) is simulated
    // here rather than delegated to a real gateway — UploadFileGateway is
    // final, so Mockery can't partial-mock a wrapped instance of it.
    $uploadGateway = Mockery::mock(FileUploadGateway::class);
    $uploadGateway
        ->shouldReceive('store')
        ->once()
        ->withArgs(function (UploadedFile $file, string $context, ?int $userId): bool {
            return $file->getClientOriginalName() === 'answer.pdf'
                && $context === 'form_attachment'
                && $userId === $this->user->id;
        })
        ->andReturn(new StoredUpload($uploadRecord->id));
    $uploadGateway
        ->shouldReceive('linkToQueryReply')
        ->once()
        ->withArgs(fn (int $uploadId, int $ticketId2, int $replyId): bool => $uploadId === $uploadRecord->id && $ticketId2 === $ticketId)
        ->andReturnUsing(function (int $uploadId, int $ticketId2, int $replyId) use ($uploadRecord): void {
            $uploadRecord->update(['reply_id' => $replyId, 'ticket_id' => $ticketId2]);
        });
    app()->instance(FileUploadGateway::class, $uploadGateway);

    actingAs($this->user)
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $this->csrfToken,
        ])
        ->post(route('forms.admin.inbox.reply', $response), [
            '_token' => $this->csrfToken,
            'message' => 'We have reviewed your request.',
            'is_official_answer' => true,
            'attachment' => UploadedFile::fake()->create('answer.pdf', 8, 'application/pdf'),
        ])
        ->assertRedirect();

    $reply = QueryReply::query()->sole();

    expect($reply->ticket_id)->toBe($ticketId)
        ->and($reply->author_user_id)->toBe($this->user->id)
        ->and($reply->message)->toBe('We have reviewed your request.')
        ->and($reply->is_official_answer)->toBeTrue()
        ->and($reply->upload_record_id)->toBe($uploadRecord->id)
        ->and($response->fresh()->query_status)->toBe(QueryTicket::STATUS_ANSWERED)
        ->and($uploadRecord->fresh()->reply_id)->toBe($reply->id);
});

it('validates status and reply content before changing a query ticket', function (): void {
    $response = createAdminQueryInboxResponse($this, $this->academicService);

    actingAs($this->user)
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $this->csrfToken,
        ])
        ->post(route('forms.admin.inbox.status.update', $response), [
            '_token' => $this->csrfToken,
            'status' => 'invalid-status',
        ])
        ->assertSessionHasErrors('status');

    actingAs($this->user)
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $this->csrfToken,
        ])
        ->post(route('forms.admin.inbox.reply', $response), [
            '_token' => $this->csrfToken,
            'message' => 'x',
        ])
        ->assertSessionHasErrors('message');

    expect($response->fresh()->query_status)->toBe(QueryTicket::STATUS_OPEN);
    expect(QueryReply::count())->toBe(0);
});

it('does not allow any inbox mutation against a query from another campus', function (): void {
    $response = createAdminQueryInboxResponse($this, $this->academicService, $this->otherCampus);

    actingAs($this->user)
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $this->csrfToken,
        ])
        ->post(route('forms.admin.inbox.status.update', $response), [
            '_token' => $this->csrfToken,
            'status' => QueryTicket::STATUS_ANSWERED,
        ])
        ->assertNotFound();

    actingAs($this->user)
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $this->csrfToken,
        ])
        ->post(route('forms.admin.inbox.assign', $response), [
            '_token' => $this->csrfToken,
            'assigned_to_user_id' => 'none',
        ])
        ->assertNotFound();

    actingAs($this->user)
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $this->csrfToken,
        ])
        ->post(route('forms.admin.inbox.reply', $response), [
            '_token' => $this->csrfToken,
            'message' => 'This mutation must not be accepted.',
        ])
        ->assertNotFound();

    expect($response->fresh()->query_status)->toBe(QueryTicket::STATUS_OPEN)
        ->and($response->fresh()->assigned_to_user_id)->toBeNull()
        ->and(QueryReply::count())->toBe(0);
});

it('does not expose non-query responses through the inbox detail route', function () {
    $response = createAdminQueryInboxResponse($this, $this->studentHq);
    $response->form->update(['type' => 'survey']);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('forms.admin.inbox.show', $response))
        ->assertNotFound();
});
