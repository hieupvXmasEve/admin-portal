<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\EmailLog;
use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\ParentProfile;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\User;
use App\Modules\Finance\Actions\Operations\SendParentPaymentRemindersAction;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use App\Services\EmailService;
use App\Shared\Support\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeParentReminderStudent(Campus $campus, Program $program, Semester $semester, string $studentId, string $fullName = 'Student'): Student
{
    return Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'student_id' => $studentId,
            'full_name' => $fullName,
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

function addParentReminderInvoiceLine(StudentInvoice $invoice, Student $student, Semester $semester, float $amount, string $description): void
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

it('sends reminders to all linked parent emails for unpaid invoices and updates invoice marker', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();

    $student = makeParentReminderStudent($campus, $program, $semester, 'STU101', 'Student One');

    $invoice = StudentInvoice::create([
        'invoice_number' => 'PINV-001',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(3),
        'subtotal' => 1000000,
        'discount_total' => 0,
        'total_amount' => 1000000,
        'paid_amount' => 0,
    ]);

    addParentReminderInvoiceLine($invoice, $student, $semester, 1000000, 'Tuition fee');

    $parentUserOne = User::factory()->create([
        'email' => 'parent.one@example.com',
        'type' => UserType::PARENT,
        'status' => User::STATUS_ACTIVE,
    ]);
    $parentUserTwo = User::factory()->create([
        'email' => 'parent.two@example.com',
        'type' => UserType::PARENT,
        'status' => User::STATUS_ACTIVE,
    ]);

    $parentOne = ParentProfile::factory()->create(['user_id' => $parentUserOne->id]);
    $parentTwo = ParentProfile::factory()->create(['user_id' => $parentUserTwo->id]);

    $student->parentProfiles()->attach($parentOne->id, ['relationship' => 'father']);
    $student->parentProfiles()->attach($parentTwo->id, ['relationship' => 'mother']);

    $emailService = Mockery::mock(EmailService::class);
    $emailService->shouldReceive('sendSingleEmail')
        ->once()
        ->withArgs(fn (...$args) => $args[0] === 'parent.one@example.com')
        ->andReturn(Mockery::mock(EmailLog::class));
    $emailService->shouldReceive('sendSingleEmail')
        ->once()
        ->withArgs(fn (...$args) => $args[0] === 'parent.two@example.com')
        ->andReturn(Mockery::mock(EmailLog::class));
    app()->instance(EmailService::class, $emailService);

    $result = SendParentPaymentRemindersAction::run([
        'invoice_ids' => [$invoice->id],
    ]);

    expect($result['sent_count'])->toBe(2)
        ->and($result['failed_count'])->toBe(0)
        ->and($result['skipped_no_debt_count'])->toBe(0)
        ->and($result['skipped_no_parent_email_count'])->toBe(0);

    expect($invoice->fresh()->last_reminder_at)->not->toBeNull();
});

it('routes campus_id to the db email provider and renders campus-specific template text', function () {
    config(['notifications.use_db_templates' => true]);

    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();

    $student = makeParentReminderStudent($campus, $program, $semester, 'STU199', 'Campus Test Student');

    NotificationEmailTemplate::updateOrCreate(
        ['campus_id' => $campus->id, 'type_key' => 'parent_payment_reminder'],
        ['subject' => 'Reminder campus_id_ok', 'body_html' => '<p>campus_id_ok {{parent_name}}</p>'],
    );

    $invoice = StudentInvoice::create([
        'invoice_number' => 'PINV-199',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(3),
        'subtotal' => 1000000,
        'discount_total' => 0,
        'total_amount' => 1000000,
        'paid_amount' => 0,
    ]);

    addParentReminderInvoiceLine($invoice, $student, $semester, 1000000, 'Tuition fee');

    $parentUser = User::factory()->create([
        'email' => 'parent.campustest@example.com',
        'type' => UserType::PARENT,
        'status' => User::STATUS_ACTIVE,
    ]);
    $parentProfile = ParentProfile::factory()->create(['user_id' => $parentUser->id]);
    $student->parentProfiles()->attach($parentProfile->id, ['relationship' => 'father']);

    $capturedContent = null;
    $emailService = Mockery::mock(EmailService::class);
    $emailService->shouldReceive('sendSingleEmail')
        ->once()
        ->andReturnUsing(function ($recipient, $subject, $content) use (&$capturedContent) {
            $capturedContent = $content;

            return Mockery::mock(EmailLog::class);
        });
    app()->instance(EmailService::class, $emailService);

    SendParentPaymentRemindersAction::run(['invoice_ids' => [$invoice->id]]);

    expect($capturedContent)->toContain('campus_id_ok');
});

it('skips parent reminders when invoice has no parent email or no outstanding balance', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();

    $studentWithoutParent = makeParentReminderStudent($campus, $program, $semester, 'STU102');
    $studentWithoutDebt = makeParentReminderStudent($campus, $program, $semester, 'STU103');

    $invoiceWithoutParent = StudentInvoice::create([
        'invoice_number' => 'PINV-002',
        'student_id' => $studentWithoutParent->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(5),
        'subtotal' => 500000,
        'discount_total' => 0,
        'total_amount' => 500000,
        'paid_amount' => 0,
    ]);

    addParentReminderInvoiceLine($invoiceWithoutParent, $studentWithoutParent, $semester, 500000, 'Outstanding tuition');

    $invoiceWithoutDebt = StudentInvoice::create([
        'invoice_number' => 'PINV-003',
        'student_id' => $studentWithoutDebt->id,
        'semester_id' => $semester->id,
        'status' => 'paid',
        'due_date' => now()->addDays(5),
        'subtotal' => 0,
        'discount_total' => 0,
        'total_amount' => 0,
        'paid_amount' => 0,
    ]);

    addParentReminderInvoiceLine($invoiceWithoutDebt, $studentWithoutDebt, $semester, 0, 'Paid invoice');

    $emailService = Mockery::mock(EmailService::class);
    $emailService->shouldNotReceive('sendSingleEmail');
    app()->instance(EmailService::class, $emailService);

    $result = SendParentPaymentRemindersAction::run([
        'invoice_ids' => [$invoiceWithoutParent->id, $invoiceWithoutDebt->id],
    ]);

    expect($result['sent_count'])->toBe(0)
        ->and($result['failed_count'])->toBe(0)
        ->and($result['skipped_no_debt_count'])->toBe(1)
        ->and($result['skipped_no_parent_email_count'])->toBe(1);
});

it('dedupes duplicate parent emails and still updates reminder marker when one delivery succeeds', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();

    $student = makeParentReminderStudent($campus, $program, $semester, 'STU104', 'Student Four');

    $invoice = StudentInvoice::create([
        'invoice_number' => 'PINV-004',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(4),
        'subtotal' => 750000,
        'discount_total' => 0,
        'total_amount' => 750000,
        'paid_amount' => 0,
    ]);

    addParentReminderInvoiceLine($invoice, $student, $semester, 750000, 'Outstanding tuition');

    $duplicateEmailUser = User::factory()->create([
        'email' => 'shared-parent@example.com',
        'type' => UserType::PARENT,
        'status' => User::STATUS_ACTIVE,
    ]);
    $successfulParentUser = User::factory()->create([
        'email' => 'active-parent@example.com',
        'type' => UserType::PARENT,
        'status' => User::STATUS_ACTIVE,
    ]);
    $inactiveParentUser = User::factory()->create([
        'email' => 'inactive-parent@example.com',
        'type' => UserType::PARENT,
        'status' => User::STATUS_INACTIVE,
    ]);

    $profileOne = ParentProfile::factory()->create([
        'user_id' => $duplicateEmailUser->id,
        'status' => 'active',
    ]);
    $profileTwo = ParentProfile::factory()->create([
        'user_id' => $successfulParentUser->id,
        'status' => 'active',
    ]);
    $profileThree = ParentProfile::factory()->create([
        'user_id' => $inactiveParentUser->id,
        'status' => 'active',
    ]);

    $student->parentProfiles()->attach($profileOne->id, ['relationship' => 'guardian']);
    $student->parentProfiles()->attach($profileTwo->id, ['relationship' => 'guardian']);
    $student->parentProfiles()->attach($profileThree->id, ['relationship' => 'guardian']);

    $emailService = Mockery::mock(EmailService::class);
    $emailService->shouldReceive('sendSingleEmail')
        ->once()
        ->withArgs(fn (...$args) => $args[0] === 'shared-parent@example.com')
        ->andThrow(new RuntimeException('SMTP failure'));
    $emailService->shouldReceive('sendSingleEmail')
        ->once()
        ->withArgs(fn (...$args) => $args[0] === 'active-parent@example.com')
        ->andReturn(Mockery::mock(EmailLog::class));
    app()->instance(EmailService::class, $emailService);

    $result = SendParentPaymentRemindersAction::run([
        'invoice_ids' => [$invoice->id],
    ]);

    expect($result['sent_count'])->toBe(1)
        ->and($result['failed_count'])->toBe(1)
        ->and($result['skipped_no_debt_count'])->toBe(0)
        ->and($result['skipped_no_parent_email_count'])->toBe(0);

    expect($invoice->fresh()->last_reminder_at)->not->toBeNull();
});
