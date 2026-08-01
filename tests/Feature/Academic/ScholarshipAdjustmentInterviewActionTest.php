<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\ScholarshipDefinition;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Models\User;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\CompleteInterviewAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\EditMinutesAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\RecordInterviewNoShowAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\ScheduleInterviewAction;
use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function interviewServiceDossier(): ScholarshipAdjustmentDossier
{
    $campus = Campus::factory()->create();
    $source = Semester::factory()->create();
    $target = Semester::factory()->create();
    $student = Student::factory()->create(['campus_id' => $campus->id, 'intake' => 1, 'intake_semester_id' => $source->id]);
    $definition = ScholarshipDefinition::create([
        'code' => 'INT'.uniqid(),
        'name' => 'Interview test scholarship',
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
    $creator = User::factory()->create();

    return ScholarshipAdjustmentDossier::create([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'source_semester_id' => $source->id,
        'target_semester_id' => $target->id,
        'status' => ScholarshipAdjustmentDossier::STATUS_IDENTIFIED,
        'source' => ScholarshipAdjustmentDossier::SOURCE_SYSTEM,
        'failed_courses_snapshot' => [],
        'original_scholarship_code' => $definition->code,
        'original_type' => $definition->type,
        'original_amount' => $definition->amount,
        'created_by_user_id' => $creator->id,
    ]);
}

it('schedules an interview and moves the dossier to interview_scheduled', function () {
    $dossier = interviewServiceDossier();
    $staff = User::factory()->create();

    $updated = ScheduleInterviewAction::run($dossier, now()->addDays(3), 'online', null, $staff->id);

    expect($updated->status)->toBe(ScholarshipAdjustmentDossier::STATUS_INTERVIEW_SCHEDULED)
        ->and($updated->interview_status)->toBe(ScholarshipAdjustmentDossier::INTERVIEW_SCHEDULED)
        ->and($updated->interview_staff_id)->toBe($staff->id);
});

it('records a student no-show', function () {
    $dossier = interviewServiceDossier();

    $updated = RecordInterviewNoShowAction::run($dossier);

    expect($updated->status)->toBe(ScholarshipAdjustmentDossier::STATUS_STUDENT_NO_SHOW)
        ->and($updated->interview_status)->toBe(ScholarshipAdjustmentDossier::INTERVIEW_STUDENT_NO_SHOW);
});

it('completes an interview, sets minutes, and makes the dossier decidable', function () {
    $dossier = interviewServiceDossier();

    $updated = CompleteInterviewAction::run($dossier, 'Discussed 2 failed courses.', ['staff', 'student']);

    expect($updated->status)->toBe(ScholarshipAdjustmentDossier::STATUS_INTERVIEWED)
        ->and($updated->interview_status)->toBe(ScholarshipAdjustmentDossier::INTERVIEW_COMPLETED)
        ->and($updated->minutes_version)->toBe(1)
        ->and($updated->isDecidable())->toBeTrue();
});

it('refuses to reopen a dossier that already has an approved decision', function () {
    $dossier = interviewServiceDossier();
    CompleteInterviewAction::run($dossier, 'Initial minutes', []);
    $dossier->update(['status' => ScholarshipAdjustmentDossier::STATUS_APPLIED]);

    expect(fn () => CompleteInterviewAction::run($dossier->fresh(), 'Attempted reopen', []))
        ->toThrow(DomainException::class);

    expect(fn () => RecordInterviewNoShowAction::run($dossier->fresh()))
        ->toThrow(DomainException::class);

    expect(fn () => ScheduleInterviewAction::run($dossier->fresh(), now()->addDay(), 'online', null, User::factory()->create()->id))
        ->toThrow(DomainException::class);
});

it('refuses to edit minutes once the dossier is applied — evidence is frozen', function () {
    $dossier = interviewServiceDossier();
    $completed = CompleteInterviewAction::run($dossier, 'Initial minutes', []);
    $completed->update(['status' => ScholarshipAdjustmentDossier::STATUS_APPLIED]);

    expect(fn () => EditMinutesAction::run($completed->fresh(), 'Late edit'))
        ->toThrow(DomainException::class);
});

it('bumps minutes_version on every edit, invalidating a prior student confirmation', function () {
    $dossier = interviewServiceDossier();

    $completed = CompleteInterviewAction::run($dossier, 'Draft minutes', []);
    expect($completed->minutes_version)->toBe(1);

    $edited = EditMinutesAction::run($completed, 'Corrected minutes');
    expect($edited->minutes_version)->toBe(2)
        ->and($edited->minutes)->toBe('Corrected minutes');
});
