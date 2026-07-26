<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\Operations\AcknowledgeLifecycleDueExceptionAction;
use App\Modules\Finance\Actions\Operations\ResolveLifecycleDueExceptionAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Enums\LifecycleDueExceptionResolutionAction;
use App\Modules\Finance\Enums\LifecycleDueExceptionReviewEventType;
use App\Modules\Finance\Models\FinanceLifecycleDueExceptionReview;
use App\Modules\Finance\Models\FinanceLifecycleDueExceptionReviewEvent;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function historyStudent(Campus $campus, Program $program, Semester $semester, string $code, ?Campus $otherCampus = null): Student
{
    return Student::factory()
        ->forCampus($otherCampus ?? $campus)
        ->forProgram($program)
        ->state([
            'student_id' => $code,
            'full_name' => "Student {$code}",
            'email' => strtolower($code).'@example.com',
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $semester->id,
            'status' => 'deferred',
            'intake_gc' => $semester->id,
            'intake_course' => $semester->id,
            'intake_major' => $semester->id,
        ])
        ->create();
}

function historyDng(Student $student, Semester $semester, array $overrides = []): DngPaymentRequest
{
    return DngPaymentRequest::create(array_merge([
        'student_id' => $student->id,
        'campus_code' => 'TEST',
        'student_code' => $student->student_id,
        'item_id' => 'ITEM-'.$student->student_id,
        'fee_type' => 'tuition',
        'description' => 'Tuition',
        'semester_id' => $semester->id,
        'due_date' => now()->subDay(),
        'amount' => 2000000,
        'status' => 'pushed_to_dng',
    ], $overrides));
}

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')->andReturn(['view_finance_operations_due_calendar']);
    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);
});

it('appends acknowledged and keep-as-debt events without overwriting prior history', function () {
    $student = historyStudent($this->campus, $this->program, $this->semester, 'HIST001');
    $request = historyDng($student, $this->semester);

    app(AcknowledgeLifecycleDueExceptionAction::class)->run($request, 'First review', $this->user->id);
    app(ResolveLifecycleDueExceptionAction::class)->run(
        $request,
        LifecycleDueExceptionResolutionAction::KeepAsDebt,
        'Debt remains collectible',
        $this->user->id,
        canCancelDng: false,
        canVoidCharges: false,
    );

    $events = FinanceLifecycleDueExceptionReviewEvent::query()
        ->where('dng_payment_request_id', $request->id)
        ->orderBy('id')
        ->get();

    expect($events)->toHaveCount(2)
        ->and($events[0]->event_type)->toBe(LifecycleDueExceptionReviewEventType::Acknowledged)
        ->and($events[1]->event_type)->toBe(LifecycleDueExceptionReviewEventType::KeptAsDebt)
        ->and($events[0]->resolution_reason)->toBe('First review')
        ->and($events[1]->resolution_reason)->toBe('Debt remains collectible');
});

it('renders shared history page with timeline events', function () {
    $student = historyStudent($this->campus, $this->program, $this->semester, 'HIST002');
    $request = historyDng($student, $this->semester);

    app(ResolveLifecycleDueExceptionAction::class)->run(
        $request,
        LifecycleDueExceptionResolutionAction::RouteToSettlement,
        'Route to settlement team',
        $this->user->id,
        canCancelDng: false,
        canVoidCharges: false,
    );

    actingAs($this->user);

    get(route('finance.operations.lifecycle-exception-history'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Finance/Operations/LifecycleExceptionHistory')
            ->has('history_events')
            ->has('summary')
            ->has('filters')
            ->has('filter_options')
            ->has('selected_timeline')
            ->has('links')
            ->where('history_events.data.0.event_type', 'routed_to_settlement')
            ->where('history_events.total', 1));
});

it('returns history for cancelled DNG requests that left the active exception queue', function () {
    $student = historyStudent($this->campus, $this->program, $this->semester, 'HIST003');
    $request = historyDng($student, $this->semester, ['status' => 'cancel_pushed_to_dng']);

    FinanceLifecycleDueExceptionReviewEvent::query()->create([
        'dng_payment_request_id' => $request->id,
        'student_id' => $student->id,
        'event_type' => LifecycleDueExceptionReviewEventType::CancelSucceeded,
        'from_status' => 'cancel_requested',
        'to_status' => 'resolved',
        'resolution_action' => 'cancel_dng',
        'resolution_reason' => 'Cancelled after review',
        'performed_by_user_id' => $this->user->id,
        'performed_at' => now(),
        'dng_status_before' => 'pushed_to_dng',
        'dng_status_after' => 'cancel_pushed_to_dng',
    ]);

    actingAs($this->user);

    get(route('finance.operations.lifecycle-exception-history', ['dng_payment_request_id' => $request->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Finance/Operations/LifecycleExceptionHistory')
            ->where('history_events.data.0.dng_request.status', 'cancel_pushed_to_dng')
            ->where('history_events.total', 1)
            ->where('selected_timeline.0.event_type', 'cancel_succeeded'));
});

it('filters shared history by DNG request id and search', function () {
    $studentA = historyStudent($this->campus, $this->program, $this->semester, 'HIST004A');
    $studentB = historyStudent($this->campus, $this->program, $this->semester, 'HIST004B');
    $requestA = historyDng($studentA, $this->semester);
    $requestB = historyDng($studentB, $this->semester);

    FinanceLifecycleDueExceptionReviewEvent::query()->create([
        'dng_payment_request_id' => $requestA->id,
        'student_id' => $studentA->id,
        'event_type' => LifecycleDueExceptionReviewEventType::Acknowledged,
        'resolution_reason' => 'A only',
        'performed_by_user_id' => $this->user->id,
        'performed_at' => now()->subHour(),
    ]);

    FinanceLifecycleDueExceptionReviewEvent::query()->create([
        'dng_payment_request_id' => $requestB->id,
        'student_id' => $studentB->id,
        'event_type' => LifecycleDueExceptionReviewEventType::Acknowledged,
        'resolution_reason' => 'B only',
        'performed_by_user_id' => $this->user->id,
        'performed_at' => now(),
    ]);

    actingAs($this->user);

    get(route('finance.operations.lifecycle-exception-history', ['dng_payment_request_id' => $requestA->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('history_events.total', 1)
            ->where('history_events.data.0.resolution_reason', 'A only'));

    get(route('finance.operations.lifecycle-exception-history', ['search' => 'HIST004B']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('history_events.total', 1)
            ->where('history_events.data.0.student.student_code', 'HIST004B'));
});

it('denies users without lifecycle exception view permission', function () {
    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')->andReturn([]);
    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);

    actingAs($this->user);

    get(route('finance.operations.lifecycle-exception-history'))
        ->assertForbidden();
});

it('backfills history events from existing reviews that predate event capture', function () {
    $student = historyStudent($this->campus, $this->program, $this->semester, 'HIST006');
    $request = historyDng($student, $this->semester, ['status' => 'cancel_pushed_to_dng']);

    FinanceLifecycleDueExceptionReview::query()->create([
        'dng_payment_request_id' => $request->id,
        'student_id' => $student->id,
        'exception_reason' => 'deferred',
        'status' => 'resolved',
        'resolution_action' => 'cancel_dng_and_void_linked_charge',
        'resolution_reason' => 'Legacy review without events',
        'resolved_at' => now()->subHour(),
        'resolved_by_user_id' => $this->user->id,
        'metadata' => [
            'after_dng_status' => 'cancel_pushed_to_dng',
        ],
    ]);

    expect(FinanceLifecycleDueExceptionReviewEvent::query()->where('dng_payment_request_id', $request->id)->count())->toBe(0);

    actingAs($this->user);

    get(route('finance.operations.lifecycle-exception-history'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('history_events.total', 2)
            ->where('history_events.data.0.is_legacy_backfill', true));

    expect(FinanceLifecycleDueExceptionReviewEvent::query()->where('dng_payment_request_id', $request->id)->count())->toBe(2);
});

it('hides out-of-scope campus history from campus-scoped users', function () {
    $otherCampus = Campus::factory()->create();
    $student = historyStudent($this->campus, $this->program, $this->semester, 'HIST005', $otherCampus);
    $request = historyDng($student, $this->semester);

    FinanceLifecycleDueExceptionReviewEvent::query()->create([
        'dng_payment_request_id' => $request->id,
        'student_id' => $student->id,
        'event_type' => LifecycleDueExceptionReviewEventType::Acknowledged,
        'resolution_reason' => 'Out of scope',
        'performed_by_user_id' => $this->user->id,
        'performed_at' => now(),
    ]);

    actingAs($this->user);

    get(route('finance.operations.lifecycle-exception-history', ['dng_payment_request_id' => $request->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('history_events.total', 0));
});
