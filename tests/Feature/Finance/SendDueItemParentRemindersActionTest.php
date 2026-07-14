<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\EmailLog;
use App\Models\ParentProfile;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\Operations\SendDueItemParentRemindersAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Notification\Models\NotificationEmailTemplate;
use App\Services\EmailService;
use App\Shared\Support\Enums\UserType;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeDueItemParentStudent(Campus $campus, Program $program, Semester $semester, string $studentId): Student
{
    return Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'student_id' => $studentId,
            'full_name' => 'Parent Test Student',
            'email' => 'ptest@example.com',
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

it('sends parent reminder for a DNG request and updates last_reminder_at', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();
    $program = Program::factory()->create();

    $student = makeDueItemParentStudent($campus, $program, $semester, 'DNGP001');

    $dngRequest = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'TEST',
        'student_code' => 'DNGP001',
        'item_id' => 'ITEM-DNGP001',
        'fee_type' => 'tuition',
        'description' => 'Tuition',
        'semester_id' => $semester->id,
        'due_date' => now()->addDays(5),
        'amount' => 3000000,
        'status' => 'pushed_to_dng',
    ]);
    $obligation = FinanceObligation::query()->create([
        'source_system' => 'test',
        'source_kind' => 'parent_due_item_reminder',
        'source_ref' => 'parent-due-item-reminder:'.uniqid('', true),
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 3_000_000,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    $charge = FinanceCharge::query()->create([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 3_000_000,
        'description' => 'Parent DNG tuition fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'DUE-PARENT-DNG-001',
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(5),
    ]);
    $line = InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 3_000_000,
        'description_snapshot' => 'Parent DNG tuition fee',
        'status' => 'active',
    ]);
    $dngRequest->reservationTargets()->create([
        'invoice_line_id' => $line->id,
        'captured_collectible' => 3_000_000,
        'target_identity' => 'invoice_line:'.$line->id,
    ]);

    $parentUser = User::factory()->create([
        'email' => 'parent.dng@example.com',
        'type' => UserType::PARENT,
        'status' => User::STATUS_ACTIVE,
    ]);
    $parentProfile = ParentProfile::factory()->create(['user_id' => $parentUser->id]);
    $student->parentProfiles()->attach($parentProfile->id, ['relationship' => 'father']);

    // Seed a NotificationEmailTemplate — proves campus_id reaches the provider
    config(['notifications.use_db_templates' => true]);
    NotificationEmailTemplate::updateOrCreate(
        ['campus_id' => $campus->id, 'type_key' => 'parent_payment_reminder'],
        ['subject' => 'Reminder for {{parent_name}}', 'body_html' => '<p>Dear {{parent_name}}, student: {{student_name}}</p>'],
    );

    $emailService = Mockery::mock(EmailService::class);
    $emailService->shouldReceive('sendSingleEmail')
        ->once()
        ->withArgs(fn (...$args) => $args[0] === 'parent.dng@example.com')
        ->andReturn(Mockery::mock(EmailLog::class));
    app()->instance(EmailService::class, $emailService);

    $result = SendDueItemParentRemindersAction::run([
        'item_ids' => ['dng_request:'.$dngRequest->id],
    ]);

    expect($result['sent_count'])->toBe(1)
        ->and($result['failed_count'])->toBe(0)
        ->and($result['skipped_no_parent_email_count'])->toBe(0);

    expect($dngRequest->fresh()->last_reminder_at)->not->toBeNull();
});
