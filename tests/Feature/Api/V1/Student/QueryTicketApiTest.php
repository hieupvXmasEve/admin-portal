<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\FormResponse;
use App\Models\QueryReply;
use App\Models\QueryTicket;
use App\Models\Semester;
use App\Models\Student;
use App\Models\UploadRecord;
use App\Models\User;
use App\Modules\Engagement\Models\Form;
use App\Modules\Engagement\Models\FormTarget;
use App\Modules\Engagement\Models\FormVersion;
use App\Shared\Contracts\Upload\FileUploadGateway;
use App\Shared\Contracts\Upload\StoredUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('lists only the authenticated student tickets matching the status filter', function (): void {
    $campus = Campus::factory()->create();
    $student = createStudentQueryApiStudent($campus);
    $otherStudent = createStudentQueryApiStudent($campus);
    $openTicket = createStudentQueryTicketFixture($student, $campus);
    createStudentQueryTicketFixture($student, $campus, QueryTicket::STATUS_ANSWERED);
    createStudentQueryTicketFixture($otherStudent, $campus);

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.queries.index', [
        'status' => QueryTicket::STATUS_OPEN,
        'per_page' => 5,
    ]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $openTicket->id)
        ->assertJsonPath('data.0.status', QueryTicket::STATUS_OPEN)
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('meta.pagination.per_page', 5);
});

it('shows an owned query ticket with its response and reply details', function (): void {
    $campus = Campus::factory()->create();
    $student = createStudentQueryApiStudent($campus);
    $ticket = createStudentQueryTicketFixture($student, $campus, answerText: 'I need an academic transcript.');
    QueryReply::create([
        'ticket_id' => $ticket->id,
        'author_user_id' => User::factory()->create()->id,
        'message' => 'We are reviewing your request.',
        'is_official_answer' => true,
    ]);

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.queries.show', $ticket))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $ticket->id)
        ->assertJsonPath('data.response.id', $ticket->response_id)
        ->assertJsonPath('data.response.form.type', 'query')
        ->assertJsonPath('data.response.answers.0.answer_text', 'I need an academic transcript.')
        ->assertJsonPath('data.replies.0.message', 'We are reviewing your request.');
});

it('does not expose or allow a reply on another student ticket', function (): void {
    $campus = Campus::factory()->create();
    $student = createStudentQueryApiStudent($campus);
    $otherStudent = createStudentQueryApiStudent($campus);
    $ticket = createStudentQueryTicketFixture($otherStudent, $campus);

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.queries.show', $ticket))
        ->assertNotFound();

    $this->postJson(route('v1.student.queries.replies.store', $ticket), [
        'message' => 'This reply must not be persisted.',
    ])
        ->assertNotFound();

    expect(QueryReply::query()->where('ticket_id', $ticket->id)->count())->toBe(0)
        ->and($ticket->fresh()->status)->toBe(QueryTicket::STATUS_OPEN);
});

it('records a student reply and moves an open ticket to pending', function (): void {
    $campus = Campus::factory()->create();
    $student = createStudentQueryApiStudent($campus);
    $ticket = createStudentQueryTicketFixture($student, $campus);

    Sanctum::actingAs($student);

    $this->postJson(route('v1.student.queries.replies.store', $ticket), [
        'message' => 'I would like an update on this request.',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.message', 'I would like an update on this request.')
        ->assertJsonPath('data.author.type', 'student')
        ->assertJsonPath('data.author.id', $student->id);

    $reply = QueryReply::query()->sole();

    expect($reply->ticket_id)->toBe($ticket->id)
        ->and($reply->author_student_id)->toBe($student->id)
        ->and($reply->is_official_answer)->toBeFalse()
        ->and($ticket->fresh()->status)->toBe(QueryTicket::STATUS_PENDING)
        ->and($ticket->fresh()->closed_at)->toBeNull();
});

it('stores a student reply attachment through the upload contract', function (): void {
    $campus = Campus::factory()->create();
    $student = createStudentQueryApiStudent($campus);
    $ticket = createStudentQueryTicketFixture($student, $campus);
    $uploadRecord = UploadRecord::factory()->create([
        'context' => 'form_attachment',
        'student_id' => $student->id,
    ]);

    $uploadGateway = Mockery::mock(FileUploadGateway::class);
    $uploadGateway
        ->shouldReceive('store')
        ->once()
        ->withArgs(function (UploadedFile $file, string $context, ?int $userId, ?int $studentId, array $metadata) use ($ticket, $student): bool {
            return $file->getClientOriginalName() === 'student-reply.pdf'
                && $context === 'form_attachment'
                && $userId === null
                && $studentId === $student->id
                && $metadata === [
                    'ticket_id' => $ticket->id,
                    'response_id' => $ticket->response_id,
                    'uploaded_by' => 'student',
                ];
        })
        ->andReturn(new StoredUpload($uploadRecord->id));
    $uploadGateway
        ->shouldReceive('linkToQueryReply')
        ->once()
        ->with($uploadRecord->id, $ticket->id, Mockery::type('int'));
    $uploadGateway
        ->shouldReceive('urlFor')
        ->once()
        ->with($uploadRecord->id)
        ->andReturn('/uploads/student-reply.pdf');
    app()->instance(FileUploadGateway::class, $uploadGateway);

    Sanctum::actingAs($student);

    $this->withHeader('Accept', 'application/json')
        ->post(route('v1.student.queries.replies.store', $ticket), [
            'message' => 'I have attached the requested evidence.',
            'attachment' => UploadedFile::fake()->create('student-reply.pdf', 8, 'application/pdf'),
        ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.attachment.id', $uploadRecord->id)
        ->assertJsonPath('data.attachment.download_url', '/uploads/student-reply.pdf');

    expect(QueryReply::query()->sole()->upload_record_id)->toBe($uploadRecord->id);
});

it('does not allow a student reply after the ticket is closed', function (): void {
    $campus = Campus::factory()->create();
    $student = createStudentQueryApiStudent($campus);
    $ticket = createStudentQueryTicketFixture($student, $campus, QueryTicket::STATUS_CLOSED);

    Sanctum::actingAs($student);

    $this->postJson(route('v1.student.queries.replies.store', $ticket), [
        'message' => 'This reply must not be stored.',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.0.code', 'BUSINESS_LOGIC_ERROR');

    expect(QueryReply::count())->toBe(0)
        ->and($ticket->fresh()->status)->toBe(QueryTicket::STATUS_CLOSED);
});

it('validates list filters and requires a message before creating a reply', function (): void {
    $campus = Campus::factory()->create();
    $student = createStudentQueryApiStudent($campus);
    $ticket = createStudentQueryTicketFixture($student, $campus);

    Sanctum::actingAs($student);

    $this->getJson(route('v1.student.queries.index', ['status' => 'invalid']))
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.field', 'status')
        ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');

    $this->getJson(route('v1.student.queries.index', ['per_page' => 4]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.field', 'per_page')
        ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');

    $this->postJson(route('v1.student.queries.replies.store', $ticket), [])
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.field', 'message')
        ->assertJsonPath('errors.0.code', 'VALIDATION_ERROR');

    expect(QueryReply::count())->toBe(0)
        ->and($ticket->fresh()->status)->toBe(QueryTicket::STATUS_OPEN);
});

function createStudentQueryTicketFixture(
    Student $student,
    Campus $campus,
    string $status = QueryTicket::STATUS_OPEN,
    string $answerText = 'I need help with my course.',
): QueryTicket {
    $form = Form::create([
        'code' => 'STUDENT-QUERY-'.uniqid('', false),
        'type' => 'query',
        'title' => 'Student support query',
        'status' => 'active',
        'created_by' => User::factory()->create()->id,
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
        'submitted_by_student_id' => $student->id,
        'anonymized' => false,
        'status' => 'submitted',
        'query_status' => $status,
        'origin' => 'api',
        'submitted_at' => now(),
    ]);
    $response->answers()->create([
        'question_id' => $question->id,
        'answer_text' => $answerText,
    ]);

    return QueryTicket::create([
        'response_id' => $response->id,
        'status' => $status,
        'priority' => QueryTicket::PRIORITY_NORMAL,
    ]);
}

function createStudentQueryApiStudent(Campus $campus): Student
{
    $semester = Semester::factory()->create();

    return Student::factory()->forCampus($campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
}
