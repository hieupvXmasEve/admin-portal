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
