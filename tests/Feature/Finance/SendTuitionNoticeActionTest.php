<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\Operations\SendTuitionNoticeAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Notification\Actions\HandleOutboxEventAction;
use App\Modules\Notification\Enums\NotificationDeliveryStatus;
use App\Modules\Notification\Enums\NotificationTemplateTypeKey;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use App\Modules\Notification\Models\NotificationEventOutbox;
use App\Modules\Notification\Models\NotificationMessage;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use App\Shared\Contracts\Identity\GuardianAccessGrantWriter;
use App\Shared\Contracts\StudentRegistry\StudentGuardianRelationshipWriter;
use App\Shared\Support\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'notification.v2_enabled' => true,
        'notification.write_mode' => 'v2',
        'notifications.use_db_templates' => true,
    ]);
    Queue::fake();
});

function makeTuitionNoticeStudent(Campus $campus, Program $program, Semester $semester, string $studentId, string $fullName, string $email): Student
{
    $user = User::factory()->create(['email' => $email]);

    return Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'student_id' => $studentId,
            'full_name' => $fullName,
            'email' => $email,
            'user_id' => $user->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $semester->id,
            'status' => 'intake_course',
            'intake_gc' => $semester->id,
            'intake_course' => $semester->id,
            'intake_major' => $semester->id,
        ])
        ->create();
}

function addTuitionNoticeInvoiceLine(StudentInvoice $invoice, Student $student, Semester $semester, float $amount, string $description): void
{
    $obligation = FinanceObligation::query()->create([
        'source_system' => 'test',
        'source_kind' => 'tuition_notice',
        'source_ref' => 'tuition-notice:'.uniqid('', true),
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);

    $charge = FinanceCharge::create([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => $amount,
        'description' => $description,
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $amount,
        'description_snapshot' => $description,
        'status' => 'active',
    ]);
}

function seedTuitionNoticeTemplates(Campus $campus): void
{
    NotificationEmailTemplate::updateOrCreate(
        ['campus_id' => $campus->id, 'type_key' => NotificationTemplateTypeKey::TuitionNotice->value],
        ['subject' => 'Thong bao {{student_name}}', 'body_html' => '<p>{{total_due}}</p><p>{{items_summary}}</p>'],
    );
    NotificationEmailTemplate::updateOrCreate(
        ['campus_id' => $campus->id, 'type_key' => NotificationTemplateTypeKey::ParentTuitionNotice->value],
        ['subject' => 'PH {{student_name}}', 'body_html' => '<p>{{total_due}}</p><p>{{items_summary}}</p>'],
    );
}

function grantTuitionNoticeParent(Student $student, User $parent): void
{
    $relationship = app(StudentGuardianRelationshipWriter::class)->preserveForStudent((int) $student->id, [[
        'full_name' => $parent->name,
        'relationship_type' => 'guardian',
        'email' => $parent->email,
        'is_primary' => true,
    ]])[0];

    app(GuardianAccessGrantWriter::class)->grant($relationship, isPrimaryPortalAccount: true);
}

function processTuitionNoticeOutbox(): void
{
    NotificationEventOutbox::query()->orderBy('id')->each(function (NotificationEventOutbox $outbox): void {
        app(HandleOutboxEventAction::class)->run($outbox);
    });
}

it('publishes student and parent tuition notices from settlement remaining', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create(['name' => 'Hoc ky he 2026']);
    $program = Program::factory()->create();
    seedTuitionNoticeTemplates($campus);

    $student = makeTuitionNoticeStudent($campus, $program, $semester, 'TN001', 'Student One', 'student.one@example.com');
    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-TN-001',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(5),
        'subtotal' => 1500000,
        'discount_total' => 0,
        'total_amount' => 1500000,
        'paid_amount' => 0,
    ]);
    addTuitionNoticeInvoiceLine($invoice, $student, $semester, 1500000, 'Hoc phi ky');

    $parent = User::factory()->create([
        'email' => 'parent.tn@example.com',
        'type' => UserType::PARENT,
        'status' => User::STATUS_ACTIVE,
    ]);
    grantTuitionNoticeParent($student, $parent);

    $result = SendTuitionNoticeAction::run(['student_ids' => [$student->id]]);

    expect($result['sent_count'])->toBe(2)
        ->and(NotificationEventOutbox::query()->count())->toBe(2);

    $position = app(SettlementPositionReader::class)->forInvoice((int) $invoice->id);
    $remaining = (float) $position->amounts->remaining->amount;

    processTuitionNoticeOutbox();

    $studentMessage = NotificationMessage::query()
        ->where('type_key', NotificationTemplateTypeKey::TuitionNotice->value)
        ->sole();
    $parentMessage = NotificationMessage::query()
        ->where('type_key', NotificationTemplateTypeKey::ParentTuitionNotice->value)
        ->sole();

    expect($studentMessage->recipient_user_id)->toBe($student->user_id)
        ->and($parentMessage->recipient_email)->toBe('parent.tn@example.com')
        ->and($studentMessage->data['total_due'])->toBe(number_format($remaining, 0, ',', '.'))
        ->and($studentMessage->data['items_summary'])->toContain('Hoc phi ky')
        ->and($studentMessage->data['title'] ?? null)->not->toBe('Payment reminder')
        ->and($studentMessage->data['body'] ?? null)->not->toBe('Please complete your outstanding payment before the due date.');

    $html = NotificationDelivery::query()->where('message_id', $studentMessage->id)->sole()->rendered_html;
    expect($html)->toContain(number_format($remaining, 0, ',', '.'));
});

it('does not enqueue a second email when content is unchanged', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();
    seedTuitionNoticeTemplates($campus);

    $student = makeTuitionNoticeStudent($campus, $program, $semester, 'TN002', 'Student Two', 'student.two@example.com');
    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-TN-002',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(5),
        'subtotal' => 1000000,
        'discount_total' => 0,
        'total_amount' => 1000000,
        'paid_amount' => 0,
    ]);
    addTuitionNoticeInvoiceLine($invoice, $student, $semester, 1000000, 'Hoc phi');

    SendTuitionNoticeAction::run(['student_ids' => [$student->id]]);
    processTuitionNoticeOutbox();

    $second = SendTuitionNoticeAction::run(['student_ids' => [$student->id]]);

    expect($second['skipped_duplicate_count'])->toBe(1)
        ->and(NotificationEventOutbox::query()->count())->toBe(1)
        ->and(NotificationMessage::query()->count())->toBe(1);
});

it('allows a resend after the previous delivery failed', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();
    seedTuitionNoticeTemplates($campus);

    $student = makeTuitionNoticeStudent($campus, $program, $semester, 'TN003', 'Student Three', 'student.three@example.com');
    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-TN-003',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(5),
        'subtotal' => 1000000,
        'discount_total' => 0,
        'total_amount' => 1000000,
        'paid_amount' => 0,
    ]);
    addTuitionNoticeInvoiceLine($invoice, $student, $semester, 1000000, 'Hoc phi');

    SendTuitionNoticeAction::run(['student_ids' => [$student->id]]);
    processTuitionNoticeOutbox();

    NotificationDelivery::query()->update([
        'status' => NotificationDeliveryStatus::Failed,
        'failed_at' => now(),
    ]);

    $second = SendTuitionNoticeAction::run(['student_ids' => [$student->id]]);
    processTuitionNoticeOutbox();

    expect($second['sent_count'])->toBe(1)
        ->and(NotificationEventOutbox::query()->count())->toBe(2)
        ->and(NotificationMessage::query()->count())->toBe(2);
});

it('allows a resend when the due date on the invoice changes', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();
    seedTuitionNoticeTemplates($campus);

    $student = makeTuitionNoticeStudent($campus, $program, $semester, 'TN004', 'Student Four', 'student.four@example.com');
    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-TN-004',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(5),
        'subtotal' => 1000000,
        'discount_total' => 0,
        'total_amount' => 1000000,
        'paid_amount' => 0,
    ]);
    addTuitionNoticeInvoiceLine($invoice, $student, $semester, 1000000, 'Hoc phi');

    SendTuitionNoticeAction::run(['student_ids' => [$student->id]]);
    processTuitionNoticeOutbox();

    $invoice->update(['due_date' => now()->addDays(20)]);

    $second = SendTuitionNoticeAction::run(['student_ids' => [$student->id]]);

    expect($second['sent_count'])->toBe(1)
        ->and(NotificationEventOutbox::query()->count())->toBe(2);
});

it('fails closed when V2 notifications are disabled', function () {
    config(['notification.v2_enabled' => false]);

    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();
    $student = makeTuitionNoticeStudent($campus, $program, $semester, 'TN005', 'Student Five', 'student.five@example.com');

    expect(fn () => SendTuitionNoticeAction::run(['student_ids' => [$student->id]]))
        ->toThrow(ValidationException::class);
    expect(NotificationEventOutbox::query()->count())->toBe(0);
});

it('fails closed when the campus is missing tuition notice templates', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();
    NotificationEmailTemplate::query()
        ->where('campus_id', $campus->id)
        ->whereIn('type_key', [
            NotificationTemplateTypeKey::TuitionNotice->value,
            NotificationTemplateTypeKey::ParentTuitionNotice->value,
        ])
        ->delete();

    $student = makeTuitionNoticeStudent($campus, $program, $semester, 'TN006', 'Student Six', 'student.six@example.com');
    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-TN-006',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(5),
        'subtotal' => 1000000,
        'discount_total' => 0,
        'total_amount' => 1000000,
        'paid_amount' => 0,
    ]);
    addTuitionNoticeInvoiceLine($invoice, $student, $semester, 1000000, 'Hoc phi');

    expect(fn () => SendTuitionNoticeAction::run(['student_ids' => [$student->id]]))
        ->toThrow(ValidationException::class);
    expect(NotificationEventOutbox::query()->count())->toBe(0);
});

it('escapes charge descriptions that contain HTML in the rendered email', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();
    seedTuitionNoticeTemplates($campus);

    $student = makeTuitionNoticeStudent($campus, $program, $semester, 'TN007', 'Student Seven', 'student.seven@example.com');
    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-TN-007',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(5),
        'subtotal' => 1000000,
        'discount_total' => 0,
        'total_amount' => 1000000,
        'paid_amount' => 0,
    ]);
    addTuitionNoticeInvoiceLine($invoice, $student, $semester, 1000000, '<a href="https://evil.test">click</a>');

    SendTuitionNoticeAction::run(['student_ids' => [$student->id]]);
    processTuitionNoticeOutbox();

    $html = NotificationDelivery::query()->sole()->rendered_html;
    expect($html)->toContain('&lt;a href=&quot;https://evil.test&quot;&gt;click&lt;/a&gt;')
        ->and($html)->not->toContain('<a href="https://evil.test">');
});

it('prints the frozen rendered HTML for a tuition notice', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();
    seedTuitionNoticeTemplates($campus);

    $student = makeTuitionNoticeStudent($campus, $program, $semester, 'TN008', 'Student Eight', 'student.eight@example.com');
    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-TN-008',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(5),
        'subtotal' => 1000000,
        'discount_total' => 0,
        'total_amount' => 1000000,
        'paid_amount' => 0,
    ]);
    addTuitionNoticeInvoiceLine($invoice, $student, $semester, 1000000, 'Hoc phi');

    SendTuitionNoticeAction::run(['student_ids' => [$student->id]]);
    processTuitionNoticeOutbox();

    $message = NotificationMessage::query()->sole();
    $staff = User::factory()->create();
    session(['current_campus_id' => $campus->id]);
    app()->singleton('campus', fn () => $campus);
    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')->andReturn([
        'view_finance_student_overview',
        'view_finance_operations_due_calendar',
    ]);
    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);

    actingAs($staff);

    get(route('finance.students.tuition-notices.print', [
        'student' => $student->id,
        'messageId' => $message->id,
    ]))
        ->assertOk()
        ->assertSee('THÔNG BÁO HỌC PHÍ', false)
        ->assertSee('1.000.000', false);
});
