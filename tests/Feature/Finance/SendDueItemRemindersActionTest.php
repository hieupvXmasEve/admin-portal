<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Actions\Operations\SendDueItemRemindersAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use App\Modules\Notification\Models\NotificationEventOutbox;
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

function makeDueItemStudent(Campus $campus, Program $program, Semester $semester, string $studentId, string $email = 'student@example.com'): Student
{
    return Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'student_id' => $studentId,
            'full_name' => 'Test Student',
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

function addDueItemInvoiceLine(StudentInvoice $invoice, Student $student, Semester $semester, float $amount, string $description): InvoiceLine
{
    $obligation = FinanceObligation::query()->create([
        'source_system' => 'test',
        'source_kind' => 'due_item_reminder',
        'source_ref' => 'due-item-reminder:'.uniqid('', true),
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

    return InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $amount,
        'description_snapshot' => $description,
        'status' => 'active',
    ]);
}

it('sends reminder for a DNG payment request branch and updates last_reminder_at', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();

    $student = makeDueItemStudent($campus, $program, $semester, 'DNG001', 'dng.student@example.com');

    $dngRequest = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'TEST',
        'student_code' => 'DNG001',
        'item_id' => 'ITEM-DNG001',
        'fee_type' => 'tuition',
        'description' => 'Tuition',
        'semester_id' => $semester->id,
        'due_date' => now()->addDays(5),
        'amount' => 2000000,
        'status' => 'pushed_to_dng',
    ]);
    $invoice = StudentInvoice::create([
        'invoice_number' => 'DUE-DNG-001',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(5),
    ]);
    $line = addDueItemInvoiceLine($invoice, $student, $semester, 2_000_000, 'DNG tuition fee');
    $dngRequest->reservationTargets()->create([
        'invoice_line_id' => $line->id,
        'captured_collectible' => 2_000_000,
        'target_identity' => 'invoice_line:'.$line->id,
    ]);

    // Seed a NotificationEmailTemplate so the DbEmailContentProvider can resolve campus_id
    config(['notifications.use_db_templates' => true]);
    NotificationEmailTemplate::updateOrCreate(
        ['campus_id' => $campus->id, 'type_key' => 'payment_reminder'],
        ['subject' => 'Reminder {{balance_formatted}}', 'body_html' => '<p>Dear {{student_name}}</p>'],
    );

    $result = SendDueItemRemindersAction::run([
        'item_ids' => ['dng_request:'.$dngRequest->id],
    ]);

    expect($result['sent_count'])->toBe(1)
        ->and($result['failed_count'])->toBe(0);

    expect($dngRequest->fresh()->last_reminder_at)->not->toBeNull()
        ->and(NotificationEventOutbox::query()->sole()->payload['type_key'])->toBe('payment_reminder')
        ->and(NotificationEventOutbox::query()->sole()->campus_id)->toBe($campus->id);
});

it('does not send a DNG reminder without canonical reservation targets', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();
    $student = makeDueItemStudent($campus, $program, $semester, 'DNG-NO-TARGET');
    $dngRequest = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'TEST',
        'student_code' => $student->student_id,
        'item_id' => 'DNG-NO-TARGET',
        'fee_type' => 'tuition',
        'description' => 'Legacy DNG without targets',
        'semester_id' => $semester->id,
        'due_date' => now()->addDays(5),
        'amount' => 2_000_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);

    $result = SendDueItemRemindersAction::run([
        'item_ids' => ['dng_request:'.$dngRequest->id],
    ]);

    expect($result['sent_count'])->toBe(0)
        ->and($result['failed_count'])->toBe(1)
        ->and($dngRequest->fresh()->last_reminder_at)->toBeNull();
});

it('sends reminder for an invoice branch with outstanding balance and updates last_reminder_at', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();

    $student = makeDueItemStudent($campus, $program, $semester, 'INV001', 'inv.student@example.com');

    $invoice = StudentInvoice::create([
        'invoice_number' => 'DUE-INV-001',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(3),
        'subtotal' => 1500000,
        'discount_total' => 0,
        'total_amount' => 1500000,
        'paid_amount' => 0,
    ]);

    addDueItemInvoiceLine($invoice, $student, $semester, 1500000, 'Tuition fee');

    // Seed a NotificationEmailTemplate — proves campus_id reaches the provider
    config(['notifications.use_db_templates' => true]);
    NotificationEmailTemplate::updateOrCreate(
        ['campus_id' => $campus->id, 'type_key' => 'payment_reminder'],
        ['subject' => 'Reminder {{student_name}}', 'body_html' => '<p>Dear {{student_name}}</p>'],
    );

    $result = SendDueItemRemindersAction::run([
        'item_ids' => ['invoice:'.$invoice->id],
    ]);

    expect($result['sent_count'])->toBe(1)
        ->and($result['failed_count'])->toBe(0)
        ->and($result['skipped_no_debt_count'])->toBe(0);

    expect($invoice->fresh()->last_reminder_at)->not->toBeNull()
        ->and(NotificationEventOutbox::query()->sole()->payload['type_key'])->toBe('payment_reminder');
});
