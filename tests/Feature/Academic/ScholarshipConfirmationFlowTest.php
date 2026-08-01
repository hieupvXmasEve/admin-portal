<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScholarshipDefinition;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Models\User;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\DecideAdjustmentAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\EditMinutesAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\MarkOverdueAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\OverruleDisputeAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\RecordOnBehalfConfirmationAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\RecordStudentResponseAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\RequestConfirmationAction;
use App\Modules\Academic\Progression\Exceptions\StaleMinutesVersionException;
use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

function confirmationDossier(array $overrides = []): ScholarshipAdjustmentDossier
{
    $campus = Campus::factory()->create();
    $source = Semester::factory()->create();
    $target = Semester::factory()->create();
    $student = Student::factory()->create(['campus_id' => $campus->id, 'intake' => 1, 'intake_semester_id' => $source->id]);
    $definition = ScholarshipDefinition::create([
        'code' => 'CONF'.uniqid(),
        'name' => 'Confirmation test',
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

    return ScholarshipAdjustmentDossier::create(array_merge([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'source_semester_id' => $source->id,
        'target_semester_id' => $target->id,
        'status' => ScholarshipAdjustmentDossier::STATUS_INTERVIEWED,
        'source' => ScholarshipAdjustmentDossier::SOURCE_SYSTEM,
        'failed_courses_snapshot' => [],
        'original_scholarship_code' => $definition->code,
        'original_type' => $definition->type,
        'original_amount' => $definition->amount,
        'interview_status' => ScholarshipAdjustmentDossier::INTERVIEW_COMPLETED,
        'minutes' => 'Discussed failed courses.',
        'minutes_version' => 1,
        'created_by_user_id' => User::factory()->create()->id,
    ], $overrides));
}

// --- RequestConfirmationAction ---

it('opens a pending confirmation window when the interview is completed', function () {
    $dossier = confirmationDossier();

    $updated = RequestConfirmationAction::run($dossier);

    expect($updated->confirmation_status)->toBe(ScholarshipAdjustmentDossier::CONFIRMATION_PENDING)
        ->and($updated->confirmation_requested_at)->not->toBeNull();
});

it('refuses to request confirmation before the interview is completed', function () {
    $dossier = confirmationDossier([
        'interview_status' => ScholarshipAdjustmentDossier::INTERVIEW_SCHEDULED,
        'status' => ScholarshipAdjustmentDossier::STATUS_INTERVIEW_SCHEDULED,
    ]);

    expect(fn () => RequestConfirmationAction::run($dossier))->toThrow(DomainException::class);
});

// --- RecordStudentResponseAction ---

it('confirms the exact minutes version', function () {
    $dossier = confirmationDossier(['confirmation_status' => 'pending', 'confirmation_requested_at' => now()]);
    $userId = User::factory()->create()->id;

    $updated = RecordStudentResponseAction::run($dossier, 1, true, 'ok', $userId);

    expect($updated->confirmation_status)->toBe(ScholarshipAdjustmentDossier::CONFIRMATION_CONFIRMED)
        ->and($updated->confirmed_minutes_version)->toBe(1)
        ->and($updated->confirmed_by_user_id)->toBe($userId)
        ->and($updated->confirmed_on_behalf)->toBeFalse();
});

it('rejects a stale minutes version', function () {
    $dossier = confirmationDossier(['confirmation_status' => 'pending', 'confirmation_requested_at' => now(), 'minutes_version' => 3]);

    expect(fn () => RecordStudentResponseAction::run($dossier, 1, true, null, 42))
        ->toThrow(StaleMinutesVersionException::class);
});

it('records a dispute', function () {
    $dossier = confirmationDossier(['confirmation_status' => 'pending', 'confirmation_requested_at' => now()]);

    $updated = RecordStudentResponseAction::run($dossier, 1, false, 'I disagree with point 2');

    expect($updated->confirmation_status)->toBe(ScholarshipAdjustmentDossier::CONFIRMATION_DISPUTED)
        ->and($updated->student_comment)->toBe('I disagree with point 2')
        ->and($updated->confirmed_at)->toBeNull();
});

it('refuses a response when there is no open confirmation', function () {
    $dossier = confirmationDossier(['confirmation_status' => 'confirmed', 'confirmed_at' => now()]);

    expect(fn () => RecordStudentResponseAction::run($dossier, 1, true, null, 42))
        ->toThrow(DomainException::class);
});

// --- EditMinutes invalidation ---

it('resets a confirmed dossier to pending when minutes are edited', function () {
    $dossier = confirmationDossier([
        'confirmation_status' => 'confirmed',
        'confirmed_at' => now(),
        'confirmed_minutes_version' => 1,
        'confirmed_by_user_id' => User::factory()->create()->id,
    ]);

    $updated = EditMinutesAction::run($dossier, 'Corrected minutes');

    expect($updated->minutes_version)->toBe(2)
        ->and($updated->confirmation_status)->toBe(ScholarshipAdjustmentDossier::CONFIRMATION_PENDING)
        ->and($updated->confirmed_at)->toBeNull()
        ->and($updated->confirmed_minutes_version)->toBeNull();
});

// --- MarkOverdueAction + command ---

it('marks a pending confirmation overdue', function () {
    $dossier = confirmationDossier(['confirmation_status' => 'pending', 'confirmation_requested_at' => now()->subDays(2)]);

    expect(MarkOverdueAction::run($dossier)->confirmation_status)
        ->toBe(ScholarshipAdjustmentDossier::CONFIRMATION_OVERDUE);
});

it('never flips a just-confirmed dossier to overdue (race guard)', function () {
    $dossier = confirmationDossier([
        'confirmation_status' => 'confirmed',
        'confirmed_at' => now(),
        'confirmation_requested_at' => now()->subDays(2),
    ]);

    expect(MarkOverdueAction::run($dossier)->confirmation_status)
        ->toBe(ScholarshipAdjustmentDossier::CONFIRMATION_CONFIRMED);
});

it('does not clobber a confirmation that lands after the command loaded the row (atomic guard)', function () {
    // Simulate the TOCTOU window: the command loads a stale pending model,
    // the student confirms in the DB, then MarkOverdueAction runs on the stale
    // instance. The atomic conditional update must no-op, not overwrite.
    $dossier = confirmationDossier(['confirmation_status' => 'pending', 'confirmation_requested_at' => now()->subDays(2)]);
    $staleInstance = ScholarshipAdjustmentDossier::query()->find($dossier->id);

    // A concurrent confirm commits to the DB after the stale load.
    ScholarshipAdjustmentDossier::query()->whereKey($dossier->id)->update([
        'confirmation_status' => ScholarshipAdjustmentDossier::CONFIRMATION_CONFIRMED,
        'confirmed_at' => now(),
    ]);

    MarkOverdueAction::run($staleInstance);

    expect($dossier->fresh()->confirmation_status)->toBe(ScholarshipAdjustmentDossier::CONFIRMATION_CONFIRMED);
});

it('command marks only confirmations older than one calendar day, leaves recent + confirmed alone', function () {
    $old = confirmationDossier(['confirmation_status' => 'pending', 'confirmation_requested_at' => now()->subDay()->subHour()]);
    $recent = confirmationDossier(['confirmation_status' => 'pending', 'confirmation_requested_at' => now()->subHour()]);
    $confirmed = confirmationDossier(['confirmation_status' => 'confirmed', 'confirmed_at' => now(), 'confirmation_requested_at' => now()->subDays(3)]);

    $this->artisan('academic:mark-scholarship-confirmations-overdue')->assertSuccessful();

    expect($old->fresh()->confirmation_status)->toBe(ScholarshipAdjustmentDossier::CONFIRMATION_OVERDUE)
        ->and($recent->fresh()->confirmation_status)->toBe(ScholarshipAdjustmentDossier::CONFIRMATION_PENDING)
        ->and($confirmed->fresh()->confirmation_status)->toBe(ScholarshipAdjustmentDossier::CONFIRMATION_CONFIRMED);
});

// --- On-behalf ---

it('records a staff on-behalf confirmation with a note and the on-behalf flag', function () {
    $dossier = confirmationDossier(['confirmation_status' => 'overdue', 'confirmation_requested_at' => now()->subDays(2)]);

    $staffId = User::factory()->create()->id;
    $updated = RecordOnBehalfConfirmationAction::run($dossier, $staffId, 'Phoned student, agreed 2026-08-01');

    expect($updated->confirmation_status)->toBe(ScholarshipAdjustmentDossier::CONFIRMATION_CONFIRMED)
        ->and($updated->confirmed_on_behalf)->toBeTrue()
        ->and($updated->confirmed_by_user_id)->toBe($staffId)
        ->and($updated->on_behalf_note)->toBe('Phoned student, agreed 2026-08-01');
});

// --- Decision gate ---

/** Grant a user approve_scholarship_adjustment at a campus. */
function grantApprover(int $userId, int $campusId): void
{
    $role = Role::firstOrCreate(['code' => 'gate_approver_test'], ['name' => 'Gate Approver Test']);
    $permission = Permission::firstOrCreate(
        ['code' => 'approve_scholarship_adjustment'],
        ['name' => 'approve_scholarship_adjustment', 'display_name' => 'Approve', 'module' => 'scholarship_adjustments', 'description' => 'test'],
    );
    DB::table('role_permissions')->insertOrIgnore([
        'role_id' => $role->id, 'permission_id' => $permission->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('campus_user_roles')->insert([
        'user_id' => $userId, 'campus_id' => $campusId, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId($userId);
}

it('blocks a money decision until the student has confirmed', function () {
    $dossier = confirmationDossier(['confirmation_status' => 'pending', 'confirmation_requested_at' => now()]);
    $maker = User::factory()->create();

    expect(fn () => app(DecideAdjustmentAction::class)
        ->run($dossier, ScholarshipAdjustmentDossier::DECISION_REDUCE, 15.0, 'reason', $maker->id))
        ->toThrow(DomainException::class);
});

it('allows a money decision once the student has confirmed', function () {
    $dossier = confirmationDossier(['confirmation_status' => 'confirmed', 'confirmed_at' => now()]);
    $maker = User::factory()->create();

    $updated = app(DecideAdjustmentAction::class)
        ->run($dossier, ScholarshipAdjustmentDossier::DECISION_REDUCE, 15.0, 'reason', $maker->id);

    expect($updated->status)->toBe(ScholarshipAdjustmentDossier::STATUS_READY_FOR_DECISION);
});

it('does not gate a non-money decision on confirmation', function () {
    $dossier = confirmationDossier(['confirmation_status' => 'pending', 'confirmation_requested_at' => now()]);
    $maker = User::factory()->create();

    $updated = app(DecideAdjustmentAction::class)
        ->run($dossier, ScholarshipAdjustmentDossier::DECISION_KEEP, null, 'keep it', $maker->id);

    expect($updated->status)->toBe(ScholarshipAdjustmentDossier::STATUS_READY_FOR_DECISION);
});

it('allows an approver to override an overdue confirmation with a reason', function () {
    $dossier = confirmationDossier(['confirmation_status' => 'overdue', 'confirmation_requested_at' => now()->subDays(2)]);
    $maker = User::factory()->create();
    grantApprover($maker->id, (int) $dossier->campus_id);

    $updated = app(DecideAdjustmentAction::class)
        ->run($dossier, ScholarshipAdjustmentDossier::DECISION_REDUCE, 15.0, 'reason', $maker->id, 'Student unreachable, dept head approved');

    expect($updated->status)->toBe(ScholarshipAdjustmentDossier::STATUS_READY_FOR_DECISION);
});

it('refuses an overdue override without the approver permission', function () {
    $dossier = confirmationDossier(['confirmation_status' => 'overdue', 'confirmation_requested_at' => now()->subDays(2)]);
    $maker = User::factory()->create();

    expect(fn () => app(DecideAdjustmentAction::class)
        ->run($dossier, ScholarshipAdjustmentDossier::DECISION_REDUCE, 15.0, 'reason', $maker->id, 'trying to override'))
        ->toThrow(DomainException::class);
});

// --- Dispute resolution (a student who disagreed is never "confirmed" for) ---

it('refuses to confirm on behalf of a student who disputed', function () {
    $dossier = confirmationDossier([
        'confirmation_status' => ScholarshipAdjustmentDossier::CONFIRMATION_DISPUTED,
        'student_comment' => 'The notes are wrong about my resit.',
    ]);

    expect(fn () => RecordOnBehalfConfirmationAction::run($dossier, User::factory()->create()->id, 'Called the student'))
        ->toThrow(DomainException::class);

    expect($dossier->fresh()->confirmation_status)
        ->toBe(ScholarshipAdjustmentDossier::CONFIRMATION_DISPUTED);
});

it('lets an approver overrule a dispute without recording a confirmation', function () {
    $dossier = confirmationDossier([
        'confirmation_status' => ScholarshipAdjustmentDossier::CONFIRMATION_DISPUTED,
        'student_comment' => 'I disagree.',
    ]);
    $approver = User::factory()->create();
    grantApprover($approver->id, (int) $dossier->campus_id);

    $updated = app(OverruleDisputeAction::class)->run($dossier, $approver->id, 'Resit record checked; the notes are accurate.');

    expect($updated->confirmation_status)->toBe(ScholarshipAdjustmentDossier::CONFIRMATION_DISPUTE_OVERRULED)
        ->and($updated->dispute_overrule_reason)->toBe('Resit record checked; the notes are accurate.')
        ->and($updated->dispute_overruled_by_user_id)->toBe($approver->id)
        // The student's own words and the absence of a confirmation both stand.
        ->and($updated->student_comment)->toBe('I disagree.')
        ->and($updated->isStudentConfirmed())->toBeFalse()
        ->and($updated->confirmed_at)->toBeNull();
});

it('refuses to overrule a dispute without the approver permission', function () {
    $dossier = confirmationDossier(['confirmation_status' => ScholarshipAdjustmentDossier::CONFIRMATION_DISPUTED]);

    expect(fn () => app(OverruleDisputeAction::class)->run($dossier, User::factory()->create()->id, 'Because I say so.'))
        ->toThrow(DomainException::class);

    expect($dossier->fresh()->confirmation_status)
        ->toBe(ScholarshipAdjustmentDossier::CONFIRMATION_DISPUTED);
});

it('refuses to overrule anything that is not a dispute', function () {
    $dossier = confirmationDossier(['confirmation_status' => ScholarshipAdjustmentDossier::CONFIRMATION_PENDING]);
    $approver = User::factory()->create();
    grantApprover($approver->id, (int) $dossier->campus_id);

    expect(fn () => app(OverruleDisputeAction::class)->run($dossier, $approver->id, 'Nothing to overrule here.'))
        ->toThrow(DomainException::class);
});

it('blocks a money decision while a dispute is unaddressed', function () {
    $dossier = confirmationDossier(['confirmation_status' => ScholarshipAdjustmentDossier::CONFIRMATION_DISPUTED]);

    expect(fn () => app(DecideAdjustmentAction::class)
        ->run($dossier, ScholarshipAdjustmentDossier::DECISION_REDUCE, 15.0, 'reason', User::factory()->create()->id))
        ->toThrow(DomainException::class);
});

it('allows a money decision once the dispute has been overruled', function () {
    $dossier = confirmationDossier(['confirmation_status' => ScholarshipAdjustmentDossier::CONFIRMATION_DISPUTED]);
    $approver = User::factory()->create();
    grantApprover($approver->id, (int) $dossier->campus_id);

    $dossier = app(OverruleDisputeAction::class)->run($dossier, $approver->id, 'Reviewed with the department head.');

    $updated = app(DecideAdjustmentAction::class)
        ->run($dossier, ScholarshipAdjustmentDossier::DECISION_REDUCE, 15.0, 'reason', User::factory()->create()->id);

    expect($updated->status)->toBe(ScholarshipAdjustmentDossier::STATUS_READY_FOR_DECISION);
});

it('resets a disputed dossier to pending when the minutes are corrected', function () {
    $dossier = confirmationDossier([
        'confirmation_status' => ScholarshipAdjustmentDossier::CONFIRMATION_DISPUTED,
        'student_comment' => 'Wrong course listed.',
    ]);

    $updated = EditMinutesAction::run($dossier, 'Corrected: the resit for COS10001 was counted.');

    expect($updated->confirmation_status)->toBe(ScholarshipAdjustmentDossier::CONFIRMATION_PENDING)
        ->and($updated->minutes_version)->toBe(2)
        ->and($updated->student_comment)->toBeNull();
});
