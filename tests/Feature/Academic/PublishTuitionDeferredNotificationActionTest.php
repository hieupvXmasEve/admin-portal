<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\ScholarshipDefinition;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Models\User;
use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Modules\Notification\Models\NotificationEventOutbox;
use App\Shared\Contracts\Academic\ScholarshipReviewDeferralNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function seedTuitionDeferredScenario(): array
{
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create(['name' => 'Summer2026']);
    $student = Student::factory()->forCampus($campus)->state(['intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => $semester->id])->create();
    $maker = User::factory()->create();

    $definition = ScholarshipDefinition::create([
        'code' => 'TDN'.uniqid(),
        'name' => 'Asia Pioneer',
        'description' => 'test',
        'type' => 'percentage',
        'amount' => 30,
        'valid_from' => now()->subYear()->toDateString(),
        'valid_until' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);
    StudentScholarshipAward::create([
        'student_id' => $student->id,
        'scholarship_code' => $definition->code,
        'awarded_at' => now()->toDateString(),
    ]);

    $dossier = ScholarshipAdjustmentDossier::query()->create([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'source_semester_id' => $semester->id,
        'target_semester_id' => $semester->id,
        'status' => ScholarshipAdjustmentDossier::STATUS_INTERVIEW_SCHEDULED,
        'source' => ScholarshipAdjustmentDossier::SOURCE_SYSTEM,
        'failed_courses_snapshot' => [],
        'original_scholarship_code' => $definition->code,
        'original_type' => $definition->type,
        'original_amount' => $definition->amount,
        'minutes_version' => 1,
        'created_by_user_id' => $maker->id,
    ]);

    return compact('campus', 'semester', 'student', 'dossier');
}

beforeEach(function () {
    config([
        'notification.outbox.push_enabled' => false,
        'notification.v2_enabled' => true,
        'notification.write_mode' => 'v2',
    ]);
});

it('publishes a realtime+email deferral notice with all template fields', function () {
    $ctx = seedTuitionDeferredScenario();

    app(ScholarshipReviewDeferralNotifier::class)
        ->notifyTuitionDeferred([$ctx['student']->id], $ctx['semester']->id);

    $outbox = NotificationEventOutbox::query()->sole();

    expect($outbox->event_name)->toBe('academic.scholarship_adjustment_tuition_deferred')
        ->and($outbox->payload['channels'])->toBe(['email', 'realtime'])
        ->and($outbox->payload['data']['student_code'])->toBe($ctx['student']->student_id)
        ->and($outbox->payload['data']['semester_code'])->toBe('Summer2026')
        ->and($outbox->payload['data']['scholarship_name'])->toBe('Asia Pioneer')
        ->and($outbox->payload['data']['dossier_id'])->toBe($ctx['dossier']->id);
});

it('does not duplicate the notice when the batch re-runs for the same dossier', function () {
    $ctx = seedTuitionDeferredScenario();
    $notifier = app(ScholarshipReviewDeferralNotifier::class);

    $notifier->notifyTuitionDeferred([$ctx['student']->id], $ctx['semester']->id);
    $notifier->notifyTuitionDeferred([$ctx['student']->id], $ctx['semester']->id);

    expect(NotificationEventOutbox::query()->count())->toBe(1);
});

it('sends a fresh notice when the dossier minutes are edited (version bump)', function () {
    $ctx = seedTuitionDeferredScenario();
    $notifier = app(ScholarshipReviewDeferralNotifier::class);

    $notifier->notifyTuitionDeferred([$ctx['student']->id], $ctx['semester']->id);

    $ctx['dossier']->update(['minutes_version' => 2]);
    $notifier->notifyTuitionDeferred([$ctx['student']->id], $ctx['semester']->id);

    expect(NotificationEventOutbox::query()->count())->toBe(2);
});

it('does nothing for a student with no in-flight dossier for the semester', function () {
    $ctx = seedTuitionDeferredScenario();
    $ctx['dossier']->update(['status' => ScholarshipAdjustmentDossier::STATUS_APPLIED]);

    app(ScholarshipReviewDeferralNotifier::class)
        ->notifyTuitionDeferred([$ctx['student']->id], $ctx['semester']->id);

    expect(NotificationEventOutbox::query()->count())->toBe(0);
});
