<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\ScholarshipDefinition;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Models\User;
use App\Modules\Academic\Models\ScholarshipAdjustmentDossier;
use App\Modules\Academic\Services\ScholarshipAdjustmentInterviewService;
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

    $updated = app(ScholarshipAdjustmentInterviewService::class)
        ->schedule($dossier, now()->addDays(3), 'online', null, $staff->id);

    expect($updated->status)->toBe(ScholarshipAdjustmentDossier::STATUS_INTERVIEW_SCHEDULED)
        ->and($updated->interview_status)->toBe(ScholarshipAdjustmentDossier::INTERVIEW_SCHEDULED)
        ->and($updated->interview_staff_id)->toBe($staff->id);
});

it('records a student no-show', function () {
    $dossier = interviewServiceDossier();

    $updated = app(ScholarshipAdjustmentInterviewService::class)->recordNoShow($dossier);

    expect($updated->status)->toBe(ScholarshipAdjustmentDossier::STATUS_STUDENT_NO_SHOW)
        ->and($updated->interview_status)->toBe(ScholarshipAdjustmentDossier::INTERVIEW_STUDENT_NO_SHOW);
});

it('completes an interview, sets minutes, and makes the dossier decidable', function () {
    $dossier = interviewServiceDossier();

    $updated = app(ScholarshipAdjustmentInterviewService::class)
        ->complete($dossier, 'Discussed 2 failed courses.', ['staff', 'student']);

    expect($updated->status)->toBe(ScholarshipAdjustmentDossier::STATUS_INTERVIEWED)
        ->and($updated->interview_status)->toBe(ScholarshipAdjustmentDossier::INTERVIEW_COMPLETED)
        ->and($updated->minutes_version)->toBe(1)
        ->and($updated->isDecidable())->toBeTrue();
});

it('refuses to reopen a dossier that already has an approved decision', function () {
    $dossier = interviewServiceDossier();
    $service = app(ScholarshipAdjustmentInterviewService::class);
    $service->complete($dossier, 'Initial minutes', []);
    $dossier->update(['status' => ScholarshipAdjustmentDossier::STATUS_APPLIED]);

    expect(fn () => $service->complete($dossier->fresh(), 'Attempted reopen', []))
        ->toThrow(DomainException::class);

    expect(fn () => $service->recordNoShow($dossier->fresh()))
        ->toThrow(DomainException::class);

    expect(fn () => $service->schedule($dossier->fresh(), now()->addDay(), 'online', null, User::factory()->create()->id))
        ->toThrow(DomainException::class);
});

it('refuses to edit minutes once the dossier is applied — evidence is frozen', function () {
    $dossier = interviewServiceDossier();
    $service = app(ScholarshipAdjustmentInterviewService::class);
    $completed = $service->complete($dossier, 'Initial minutes', []);
    $completed->update(['status' => ScholarshipAdjustmentDossier::STATUS_APPLIED]);

    expect(fn () => $service->editMinutes($completed->fresh(), 'Late edit'))
        ->toThrow(DomainException::class);
});

it('bumps minutes_version on every edit, invalidating a prior student confirmation', function () {
    $dossier = interviewServiceDossier();
    $service = app(ScholarshipAdjustmentInterviewService::class);

    $completed = $service->complete($dossier, 'Draft minutes', []);
    expect($completed->minutes_version)->toBe(1);

    $edited = $service->editMinutes($completed, 'Corrected minutes');
    expect($edited->minutes_version)->toBe(2)
        ->and($edited->minutes)->toBe('Corrected minutes');
});
