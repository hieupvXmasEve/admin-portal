<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\EmailLog;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\Operations\SendDueItemRemindersAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Queries\Operations\ListDueItemsQuery;
use App\Modules\Finance\Queries\Operations\ListExamResitHandoffQuery;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use App\Services\EmailService;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);

    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);
});

function dueExamStudent(object $ctx, string $code, string $status = 'intake_course', ?string $email = null): Student
{
    $email ??= strtolower($code).'@example.com';

    return Student::factory()
        ->forCampus($ctx->campus)
        ->forProgram($ctx->program)
        ->state([
            'student_id' => $code,
            'full_name' => "Student {$code}",
            'email' => $email,
            'status' => $status,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $ctx->semester->id,
        ])
        ->create();
}

function findRow(iterable $items, int $id): ?array
{
    foreach ($items as $row) {
        if (($row['id'] ?? null) === $id) {
            return $row;
        }
    }

    return null;
}

it('enriches a PTL DNG due row with exam-resit source context', function () {
    $student = dueExamStudent($this, 'PTL001');
    $attempt = makeApprovedExamResitAttempt($student, $this->campus, $this->semester);
    $charge = createExamResitChargeFor($attempt);
    $attempt = scheduleExamResitAttemptInSlot($attempt->fresh(), now()->subDays(30));
    $dng = createPushedDngForExamResitCharge($charge, [
        'semester_id' => $this->semester->id,
        'due_date' => now()->subDays(3),
    ]);

    $list = app(ListDueItemsQuery::class)->handle($this->semester->id, null, null);
    $row = findRow($list->items(), $dng->id);

    expect($row)->not->toBeNull()
        ->and($row['source_type'])->toBe('exam_resit')
        ->and($row['source_id'])->toBe($attempt->id)
        ->and($row['fee_type'])->toBe('PTL')
        ->and($row['unit_code'])->not->toBeNull()
        ->and($row['exam_date'])->not->toBeNull()
        ->and($row['reminder_state'])->toBe('remindable');
});

it('leaves a non-PTL DNG due row unchanged and remindable', function () {
    $student = dueExamStudent($this, 'HP001');
    $dng = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'TEST',
        'student_code' => $student->student_id,
        'fee_type' => 'HP',
        'description' => 'Tuition',
        'semester_id' => $this->semester->id,
        'due_date' => now()->subDay(),
        'item_id' => 'HP-ITEM-1',
        'amount' => 2_500_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);

    $list = app(ListDueItemsQuery::class)->handle($this->semester->id, null, null);
    $row = findRow($list->items(), $dng->id);

    expect($row)->not->toBeNull()
        ->and($row['source_type'])->toBe('dng_request')
        ->and($row['source_id'])->toBeNull()
        ->and($row['fee_type'])->toBe('HP')
        ->and($row['reminder_state'])->toBe('remindable');
});

it('surfaces overdue exam-resit sources without a DNG as handoff rows only', function () {
    // (a) overdue, charge created, NO pushed DNG -> handoff candidate
    $a = dueExamStudent($this, 'HOFF1');
    $attemptA = makeApprovedExamResitAttempt($a, $this->campus, $this->semester);
    createExamResitChargeFor($attemptA);
    $attemptA = scheduleExamResitAttemptInSlot($attemptA->fresh(), now()->subDays(40));

    // (b) overdue, charge + pushed DNG -> excluded (already remindable)
    $b = dueExamStudent($this, 'HOFF2');
    $attemptB = makeApprovedExamResitAttempt($b, $this->campus, $this->semester);
    $chargeB = createExamResitChargeFor($attemptB);
    scheduleExamResitAttemptInSlot($attemptB->fresh(), now()->subDays(40));
    createPushedDngForExamResitCharge($chargeB, ['semester_id' => $this->semester->id]);

    // (c) future sitting, charge, no DNG -> not overdue -> excluded
    $c = dueExamStudent($this, 'HOFF3');
    $attemptC = makeApprovedExamResitAttempt($c, $this->campus, $this->semester);
    createExamResitChargeFor($attemptC);
    scheduleExamResitAttemptInSlot($attemptC->fresh(), now()->addDays(20));

    $handoff = app(ListExamResitHandoffQuery::class)->handle($this->semester->id, null);

    expect($handoff->total())->toBe(1);
    $row = $handoff->items()[0];
    expect($row['source_id'])->toBe($attemptA->id)
        ->and($row['source_type'])->toBe('exam_resit')
        ->and($row['days_overdue'])->toBeGreaterThan(0)
        ->and($row['handoff']['route_name'])->toBe('finance.batch-studio.dng');
});

it('mirrors last_reminded_at onto the linked exam-resit attempt only after a successful send', function () {
    $student = dueExamStudent($this, 'PTL002', email: 'ptl.send@example.com');
    $attempt = makeApprovedExamResitAttempt($student, $this->campus, $this->semester);
    $charge = createExamResitChargeFor($attempt);
    scheduleExamResitAttemptInSlot($attempt->fresh(), now()->subDays(30));
    $dng = createPushedDngForExamResitCharge($charge, ['semester_id' => $this->semester->id]);

    expect($attempt->fresh()->last_reminded_at)->toBeNull();

    config(['notifications.use_db_templates' => true]);
    NotificationEmailTemplate::updateOrCreate(
        ['campus_id' => $this->campus->id, 'type_key' => 'payment_reminder'],
        ['subject' => 'Reminder {{student_name}}', 'body_html' => '<p>Dear {{student_name}}</p>'],
    );

    $emailService = Mockery::mock(EmailService::class);
    $emailService->shouldReceive('sendSingleEmail')
        ->once()
        ->withArgs(fn (...$args) => $args[0] === 'ptl.send@example.com')
        ->andReturn(Mockery::mock(EmailLog::class));
    app()->instance(EmailService::class, $emailService);

    $result = SendDueItemRemindersAction::run(['item_ids' => ['dng_request:'.$dng->id]]);

    expect($result['sent_count'])->toBe(1)
        ->and($dng->fresh()->last_reminder_at)->not->toBeNull()
        ->and($attempt->fresh()->last_reminded_at)->not->toBeNull();
});

it('does not touch the attempt timestamp when the row is skipped for missing email', function () {
    $student = dueExamStudent($this, 'PTL003', email: '');
    $attempt = makeApprovedExamResitAttempt($student, $this->campus, $this->semester);
    $charge = createExamResitChargeFor($attempt);
    scheduleExamResitAttemptInSlot($attempt->fresh(), now()->subDays(30));
    $dng = createPushedDngForExamResitCharge($charge, ['semester_id' => $this->semester->id]);

    $emailService = Mockery::mock(EmailService::class);
    $emailService->shouldNotReceive('sendSingleEmail');
    app()->instance(EmailService::class, $emailService);

    $result = SendDueItemRemindersAction::run(['item_ids' => ['dng_request:'.$dng->id]]);

    expect($result['sent_count'])->toBe(0)
        ->and($result['skipped_no_student_email_count'])->toBe(1)
        ->and($dng->fresh()->last_reminder_at)->toBeNull()
        ->and($attempt->fresh()->last_reminded_at)->toBeNull();
});

it('renders the Due Reminders page with PTL rows and the handoff list', function () {
    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')->andReturn(['view_finance_operations_due_calendar']);
    app()->singleton(PermissionService::class, fn () => $permissionService);

    $student = dueExamStudent($this, 'PTL004');
    $attempt = makeApprovedExamResitAttempt($student, $this->campus, $this->semester);
    $charge = createExamResitChargeFor($attempt);
    scheduleExamResitAttemptInSlot($attempt->fresh(), now()->subDays(30));
    createPushedDngForExamResitCharge($charge, [
        'semester_id' => $this->semester->id,
        'due_date' => now()->subDays(3),
    ]);

    // a handoff-only source (overdue, charge, no DNG)
    $handoffStudent = dueExamStudent($this, 'PTL005');
    $handoffAttempt = makeApprovedExamResitAttempt($handoffStudent, $this->campus, $this->semester);
    createExamResitChargeFor($handoffAttempt);
    scheduleExamResitAttemptInSlot($handoffAttempt->fresh(), now()->subDays(40));

    get(route('finance.operations.due-calendar'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Finance/Operations/DueCalendar')
            ->has('invoices')
            ->has('handoffItems')
            ->where('handoffItems.total', 1)
            ->where('filters.source', 'all'));
});
