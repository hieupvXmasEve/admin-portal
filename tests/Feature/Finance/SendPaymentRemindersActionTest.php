<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\EmailLog;
use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Modules\Finance\Actions\Operations\SendPaymentRemindersAction;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use App\Services\EmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
    $charge = FinanceCharge::create([
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

it('sends reminders to student emails for unpaid invoices and updates invoice marker', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();

    $student = makeStudentReminderStudent($campus, $program, $semester, 'STU001', 'Student One', 'student.one@example.com');

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

    $emailService = Mockery::mock(EmailService::class);
    $emailService->shouldReceive('sendSingleEmail')
        ->once()
        ->withArgs(fn (...$args) => $args[0] === 'student.one@example.com')
        ->andReturn(Mockery::mock(EmailLog::class));
    app()->instance(EmailService::class, $emailService);

    $result = SendPaymentRemindersAction::run([
        'invoice_ids' => [$invoice->id],
    ]);

    expect($result['sent_count'])->toBe(1)
        ->and($result['failed_count'])->toBe(0)
        ->and($result['skipped_no_debt_count'])->toBe(0)
        ->and($result['skipped_no_student_email_count'])->toBe(0);

    expect($invoice->fresh()->last_reminder_at)->not->toBeNull();
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

    $capturedContent = null;
    $emailService = Mockery::mock(EmailService::class);
    $emailService->shouldReceive('sendSingleEmail')
        ->once()
        ->andReturnUsing(function ($recipient, $subject, $content) use (&$capturedContent) {
            $capturedContent = $content;

            return Mockery::mock(EmailLog::class);
        });
    app()->instance(EmailService::class, $emailService);

    SendPaymentRemindersAction::run(['invoice_ids' => [$invoice->id]]);

    expect($capturedContent)->toContain('campus_id_ok');
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

    addStudentReminderInvoiceLine($invoiceWithoutDebt, $studentWithoutDebt, $semester, 0, 'Paid invoice');

    $emailService = Mockery::mock(EmailService::class);
    $emailService->shouldNotReceive('sendSingleEmail');
    app()->instance(EmailService::class, $emailService);

    $result = SendPaymentRemindersAction::run([
        'invoice_ids' => [$invoiceWithoutEmail->id, $invoiceWithoutDebt->id],
    ]);

    expect($result['sent_count'])->toBe(0)
        ->and($result['failed_count'])->toBe(0)
        ->and($result['skipped_no_debt_count'])->toBe(1)
        ->and($result['skipped_no_student_email_count'])->toBe(1);
});
