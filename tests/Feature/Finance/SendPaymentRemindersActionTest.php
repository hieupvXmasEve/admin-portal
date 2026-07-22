<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\Operations\SendPaymentRemindersAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Notification\Actions\HandleOutboxEventAction;
use App\Modules\Notification\Models\NotificationDelivery;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use App\Modules\Notification\Models\NotificationEventOutbox;
use App\Modules\Notification\Models\NotificationMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'notification.v2_enabled' => true,
        'notification.write_mode' => 'v2',
    ]);
    Queue::fake();
});

function makeStudentReminderStudent(Campus $campus, Program $program, Semester $semester, string $studentId, string $fullName = 'Student', string $email = 'student@example.com'): Student
{
    return Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'student_id' => $studentId,
            'full_name' => $fullName,
            'email' => $email,
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

function addStudentReminderInvoiceLine(StudentInvoice $invoice, Student $student, Semester $semester, float $amount, string $description): void
{
    $obligation = FinanceObligation::query()->create([
        'source_system' => 'test',
        'source_kind' => 'payment_reminder',
        'source_ref' => 'payment-reminder:'.uniqid('', true),
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

it('queues a campus-scoped V2 reminder event for an unpaid invoice and updates the invoice marker', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();

    $student = makeStudentReminderStudent($campus, $program, $semester, 'STU001', 'Student One', 'student.one@example.com');
    $student->update([
        'user_id' => User::factory()->create(['email' => $student->email])->id,
    ]);
    NotificationEmailTemplate::updateOrCreate(
        ['campus_id' => $campus->id, 'type_key' => 'payment_reminder'],
        ['subject' => 'Payment reminder for {{student_name}}', 'body_html' => '<p>{{balance_formatted}}</p>'],
    );

    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-001',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(3),
        'subtotal' => 1000000,
        'discount_total' => 0,
        'total_amount' => 1000000,
        'paid_amount' => 0,
    ]);

    addStudentReminderInvoiceLine($invoice, $student, $semester, 1000000, 'Tuition fee');

    $result = SendPaymentRemindersAction::run([
        'invoice_ids' => [$invoice->id],
    ]);

    expect($result['sent_count'])->toBe(1)
        ->and($result['failed_count'])->toBe(0)
        ->and($result['skipped_no_debt_count'])->toBe(0)
        ->and($result['skipped_no_student_email_count'])->toBe(0);

    expect($invoice->fresh()->last_reminder_at)->not->toBeNull()
        ->and(NotificationEventOutbox::query()->count())->toBe(1)
        ->and(NotificationEventOutbox::query()->sole()->event_name)->toBe('finance.payment_reminder_requested')
        ->and(NotificationEventOutbox::query()->sole()->campus_id)->toBe($campus->id)
        ->and(NotificationEventOutbox::query()->sole()->payload['type_key'])->toBe('payment_reminder')
        ->and(NotificationEventOutbox::query()->sole()->payload['recipient_targets'])->toBe([
            ['type' => 'student', 'id' => $student->id],
        ]);

    app(HandleOutboxEventAction::class)->run(NotificationEventOutbox::query()->sole());

    expect(NotificationMessage::query()->count())->toBe(1)
        ->and(NotificationMessage::query()->sole()->recipient_user_id)->toBe($student->user_id)
        ->and(NotificationDelivery::query()->where('channel', 'email')->count())->toBe(1)
        ->and(NotificationDelivery::query()->sole()->rendered_subject)->toBe('Payment reminder for STUDENT ONE');
});

it('routes campus_id to the db email provider and renders campus-specific template text', function () {
    config(['notifications.use_db_templates' => true]);

    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();

    $student = makeStudentReminderStudent($campus, $program, $semester, 'STU099', 'Campus Student', 'campus.student@example.com');

    NotificationEmailTemplate::updateOrCreate(
        ['campus_id' => $campus->id, 'type_key' => 'payment_reminder'],
        ['subject' => 'campus_id_ok subject', 'body_html' => '<p>campus_id_ok {{student_name}}</p>'],
    );

    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-099',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(3),
        'subtotal' => 1000000,
        'discount_total' => 0,
        'total_amount' => 1000000,
        'paid_amount' => 0,
    ]);

    addStudentReminderInvoiceLine($invoice, $student, $semester, 1000000, 'Tuition fee');

    SendPaymentRemindersAction::run(['invoice_ids' => [$invoice->id]]);

    expect(NotificationEventOutbox::query()->sole()->campus_id)->toBe($campus->id)
        ->and(NotificationEventOutbox::query()->sole()->payload['data']['student_name'])->toBe($student->fresh()->full_name);
});

it('skips student reminders when invoice has no debt or student email is missing', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();

    $studentWithoutEmail = makeStudentReminderStudent($campus, $program, $semester, 'STU002', 'Student Two', '');
    $studentWithoutDebt = makeStudentReminderStudent($campus, $program, $semester, 'STU003', 'Student Three', 'student.three@example.com');

    $invoiceWithoutEmail = StudentInvoice::create([
        'invoice_number' => 'INV-002',
        'student_id' => $studentWithoutEmail->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(5),
        'subtotal' => 500000,
        'discount_total' => 0,
        'total_amount' => 500000,
        'paid_amount' => 0,
    ]);

    addStudentReminderInvoiceLine($invoiceWithoutEmail, $studentWithoutEmail, $semester, 500000, 'Outstanding tuition');

    $invoiceWithoutDebt = StudentInvoice::create([
        'invoice_number' => 'INV-003',
        'student_id' => $studentWithoutDebt->id,
        'semester_id' => $semester->id,
        'status' => 'paid',
        'due_date' => now()->addDays(5),
        'subtotal' => 0,
        'discount_total' => 0,
        'total_amount' => 0,
        'paid_amount' => 0,
    ]);

    $result = SendPaymentRemindersAction::run([
        'invoice_ids' => [$invoiceWithoutEmail->id, $invoiceWithoutDebt->id],
    ]);

    expect($result['sent_count'])->toBe(0)
        ->and($result['failed_count'])->toBe(0)
        ->and($result['skipped_no_debt_count'])->toBe(1)
        ->and($result['skipped_no_student_email_count'])->toBe(1);
});
