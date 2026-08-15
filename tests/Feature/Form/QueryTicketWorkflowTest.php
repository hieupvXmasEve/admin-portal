<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\UploadRecord;
use App\Models\User;
use App\Modules\Engagement\Models\Form;
use App\Modules\Engagement\Models\FormResponse;
use App\Modules\Engagement\Models\FormTarget;
use App\Modules\Engagement\Models\FormVersion;
use App\Modules\Engagement\Models\QueryReply;
use App\Modules\Engagement\Models\QueryTicket;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use App\Shared\Contracts\Upload\FileUploadGateway;
use App\Shared\Contracts\Upload\StoredUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->otherCampus = Campus::factory()->create();
    $this->csrfToken = 'query-ticket-workflow-csrf';

    session([
        'current_campus_id' => $this->campus->id,
        '_token' => $this->csrfToken,
    ]);
    app()->singleton('campus', fn (): Campus => $this->campus);

    bindQueryTicketReviewPermissions(['review_form']);
});

function bindQueryTicketReviewPermissions(array $permissions): void
{
    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')->andReturn($permissions);

    app()->forgetInstance(CampusPermissionReader::class);
    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);
}

/**
 * @return array{0: QueryTicket, 1: int}
 */
function createReviewQueryTicket(
    object $context,
    Campus $campus,
    string $status = QueryTicket::STATUS_OPEN,
    string $answerText = 'I need help with my course.',
): array {
    $form = Form::create([
        'code' => 'REVIEW-QUERY-'.uniqid('', false),
        'type' => 'query',
        'title' => 'Student support query',
        'status' => 'active',
        'created_by' => $context->user->id,
    ]);
    $version = FormVersion::create([
        'form_id' => $form->id,
        'version_no' => 1,
        'is_published' => true,
        'effective_from' => now()->subDay(),
    ]);
    $question = $version->questions()->create([
        'code' => 'DETAILS',
        'text' => 'Please describe your request.',
        'type' => 'long_text',
        'order_index' => 1,
    ]);
    $target = FormTarget::create([
        'form_id' => $form->id,
        'form_version_id' => $version->id,
        'campus_id' => $campus->id,
        'scope_type' => 'global',
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
        'target_scope_type' => 'global',
        'anonymized' => false,
        'status' => 'submitted',
        'query_status' => $status,
        'origin' => 'web',
        'submitted_at' => now(),
    ]);
    $response->answers()->create([
        'question_id' => $question->id,
        'answer_text' => $answerText,
    ]);
    $ticket = QueryTicket::create([
        'response_id' => $response->id,
        'status' => $status,
        'priority' => QueryTicket::PRIORITY_NORMAL,
    ]);

    return [$ticket, $question->id];
}

it('lists only current-campus review tickets matching status and question filters', function (): void {
    [$matchingTicket, $questionId] = createReviewQueryTicket($this, $this->campus);
    [$differentStatusTicket] = createReviewQueryTicket($this, $this->campus, QueryTicket::STATUS_ANSWERED);
    [$foreignCampusTicket] = createReviewQueryTicket($this, $this->otherCampus);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('forms.queries.index', [
            'status' => QueryTicket::STATUS_OPEN,
            'question_id' => $questionId,
            'per_page' => 10,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Forms/Review/Queries/Index')
            ->where('filters.status', QueryTicket::STATUS_OPEN)
            ->where('filters.question_id', (string) $questionId)
            ->where('filters.per_page', 10)
            ->where('tickets', function ($tickets) use ($matchingTicket, $differentStatusTicket, $foreignCampusTicket): bool {
                $ids = collect($tickets)->pluck('id');

                return $ids->contains($matchingTicket->id)
                    && ! $ids->contains($differentStatusTicket->id)
                    && ! $ids->contains($foreignCampusTicket->id);
            })
        );
});

it('shows a current-campus query ticket with its response details', function (): void {
    [$ticket] = createReviewQueryTicket($this, $this->campus);

    actingAs($this->user)
        ->get(route('forms.queries.show', $ticket))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Forms/Review/Queries/Show')
            ->where('ticket.id', $ticket->id)
            ->where('ticket.status', QueryTicket::STATUS_OPEN)
            ->where('ticket.response.form.type', 'query')
        );
});

it('stores an official staff reply with an attachment and marks the ticket answered', function (): void {
    [$ticket] = createReviewQueryTicket($this, $this->campus);
    $uploadRecord = UploadRecord::factory()->create([
        'context' => 'form_attachment',
        'user_id' => $this->user->id,
    ]);

    $uploadGateway = Mockery::mock(FileUploadGateway::class);
    $uploadGateway
        ->shouldReceive('store')
        ->once()
        ->withArgs(function (UploadedFile $file, string $context, ?int $userId, ?int $studentId, array $metadata) use ($ticket): bool {
            return $file->getClientOriginalName() === 'staff-reply.pdf'
                && $context === 'form_attachment'
                && $userId === $this->user->id
                && $studentId === null
                && $metadata === [
                    'ticket_id' => $ticket->id,
                    'response_id' => $ticket->response_id,
                    'uploaded_by' => 'user',
                ];
        })
        ->andReturn(new StoredUpload($uploadRecord->id));
    $uploadGateway
        ->shouldReceive('linkToQueryReply')
        ->once()
        ->with($uploadRecord->id, $ticket->id, Mockery::type('int'));
    app()->instance(FileUploadGateway::class, $uploadGateway);

    actingAs($this->user)
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $this->csrfToken,
        ])
        ->post(route('forms.queries.replies.store', $ticket), [
            '_token' => $this->csrfToken,
            'message' => 'We have escalated your request to the academic team.',
            'is_official_answer' => true,
            'attachment' => UploadedFile::fake()->create('staff-reply.pdf', 8, 'application/pdf'),
        ])
        ->assertRedirect();

    $reply = QueryReply::query()->sole();

    expect($reply->ticket_id)->toBe($ticket->id)
        ->and($reply->author_user_id)->toBe($this->user->id)
        ->and($reply->is_official_answer)->toBeTrue()
        ->and($reply->upload_record_id)->toBe($uploadRecord->id)
        ->and($ticket->fresh()->status)->toBe(QueryTicket::STATUS_ANSWERED);
});

it('updates the ticket status and closes it with a close timestamp', function (): void {
    [$ticket] = createReviewQueryTicket($this, $this->campus);

    actingAs($this->user)
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $this->csrfToken,
        ])
        ->post(route('forms.queries.status.update', $ticket), [
            '_token' => $this->csrfToken,
            'status' => QueryTicket::STATUS_CLOSED,
        ])
        ->assertRedirect(route('forms.queries.show', $ticket));

    expect($ticket->fresh()->status)->toBe(QueryTicket::STATUS_CLOSED)
        ->and($ticket->fresh()->closed_at)->not->toBeNull();
});

it('rejects staff query-ticket routes without review permission', function (): void {
    [$ticket] = createReviewQueryTicket($this, $this->campus);
    bindQueryTicketReviewPermissions([]);

    actingAs($this->user)
        ->get(route('forms.queries.index'))
        ->assertForbidden();

    actingAs($this->user)
        ->get(route('forms.queries.show', $ticket))
        ->assertForbidden();
});

it('does not expose or mutate a query ticket from another campus', function (): void {
    [$ticket] = createReviewQueryTicket($this, $this->otherCampus);

    actingAs($this->user)
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $this->csrfToken,
        ])
        ->get(route('forms.queries.show', $ticket))
        ->assertNotFound();

    actingAs($this->user)
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $this->csrfToken,
        ])
        ->post(route('forms.queries.replies.store', $ticket), [
            '_token' => $this->csrfToken,
            'message' => 'This reply must not be persisted.',
        ])
        ->assertNotFound();

    actingAs($this->user)
        ->withSession([
            'current_campus_id' => $this->campus->id,
            '_token' => $this->csrfToken,
        ])
        ->post(route('forms.queries.status.update', $ticket), [
            '_token' => $this->csrfToken,
            'status' => QueryTicket::STATUS_CLOSED,
        ])
        ->assertNotFound();

    expect($ticket->fresh()->status)->toBe(QueryTicket::STATUS_OPEN);
    expect(QueryReply::count())->toBe(0);
});
