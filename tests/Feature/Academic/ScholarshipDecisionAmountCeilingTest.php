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
use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

const DECISION_CEILING_CSRF = 'decision-ceiling-csrf';

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

function decisionCeilingUser(Campus $campus): User
{
    $user = User::factory()->create();
    $role = Role::firstOrCreate(['code' => 'decision_ceiling_role'], ['name' => 'Decision Ceiling Role']);

    foreach (['decide_scholarship_adjustment', 'view_scholarship_adjustment'] as $code) {
        $permission = Permission::firstOrCreate(
            ['code' => $code],
            ['name' => $code, 'display_name' => $code, 'module' => 'scholarship_adjustments', 'description' => 'test'],
        );

        DB::table('role_permissions')->insertOrIgnore([
            'role_id' => $role->id, 'permission_id' => $permission->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    DB::table('campus_user_roles')->insert([
        'user_id' => $user->id, 'campus_id' => $campus->id, 'role_id' => $role->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $user->id);

    return $user;
}

/** A dossier already confirmed by the student, so only the amount is under test. */
function decisionCeilingDossier(Campus $campus, string $type, float $awardAmount): ScholarshipAdjustmentDossier
{
    $source = Semester::factory()->create(['start_date' => now()->subMonths(6)]);
    $target = Semester::factory()->create(['start_date' => now()]);
    $student = Student::factory()->create([
        'campus_id' => $campus->id,
        'intake' => 1,
        'intake_semester_id' => $source->id,
    ]);

    $definition = ScholarshipDefinition::create([
        'code' => 'CEIL'.uniqid(),
        'name' => 'Ceiling test',
        'description' => 'test',
        'type' => $type,
        'amount' => $awardAmount,
        'valid_from' => now()->subYear()->toDateString(),
        'valid_until' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);

    StudentScholarshipAward::create([
        'student_id' => $student->id,
        'scholarship_code' => $definition->code,
        'awarded_at' => now()->toDateString(),
    ]);

    return ScholarshipAdjustmentDossier::create([
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
        'minutes' => 'Discussed.',
        'minutes_version' => 1,
        'confirmation_status' => ScholarshipAdjustmentDossier::CONFIRMATION_CONFIRMED,
        'confirmed_at' => now(),
        'confirmed_minutes_version' => 1,
        'created_by_user_id' => User::factory()->create()->id,
    ]);
}

function postDecision(User $user, Campus $campus, ScholarshipAdjustmentDossier $dossier, float $amount)
{
    session(['_token' => DECISION_CEILING_CSRF, 'current_campus_id' => $campus->id]);
    app()->instance('campus', $campus);

    return test()->actingAs($user)
        ->withHeader('X-CSRF-TOKEN', DECISION_CEILING_CSRF)
        ->from(route('scholarship-adjustments.show', $dossier->id))
        ->post(route('scholarship-adjustments.decide', $dossier->id), [
            '_token' => DECISION_CEILING_CSRF,
            'decision_type' => 'reduce',
            'adjusted_amount' => $amount,
            'reason' => 'Failed two courses.',
        ]);
}

it('rejects a percentage above the awarded percentage', function () {
    $campus = Campus::factory()->create();
    $dossier = decisionCeilingDossier($campus, 'percentage', 30);

    postDecision(decisionCeilingUser($campus), $campus, $dossier, 40)
        ->assertSessionHasErrors('adjusted_amount');

    expect($dossier->fresh()->decision_type)->toBeNull();
});

it('rejects a fixed amount above the awarded amount', function () {
    $campus = Campus::factory()->create();
    $dossier = decisionCeilingDossier($campus, 'fixed_amount', 20_000_000);

    postDecision(decisionCeilingUser($campus), $campus, $dossier, 25_000_000)
        ->assertSessionHasErrors('adjusted_amount');

    expect($dossier->fresh()->decision_type)->toBeNull();
});

it('accepts a value below the awarded amount', function () {
    $campus = Campus::factory()->create();
    $dossier = decisionCeilingDossier($campus, 'percentage', 30);

    postDecision(decisionCeilingUser($campus), $campus, $dossier, 15)
        ->assertSessionHasNoErrors();

    expect($dossier->fresh()->decision_type)->toBe('reduce')
        ->and((float) $dossier->fresh()->decision_adjusted_amount)->toBe(15.0);
});

it('accepts a value exactly equal to the awarded amount', function () {
    // Equal is not a reduction, but it is not an out-of-range value either —
    // the ceiling only rejects amounts that could never be honoured.
    $campus = Campus::factory()->create();
    $dossier = decisionCeilingDossier($campus, 'percentage', 30);

    postDecision(decisionCeilingUser($campus), $campus, $dossier, 30)
        ->assertSessionHasNoErrors();
});

// The FormRequest only guards the HTTP door. These assert the same ceiling at
// the Action, which every caller (HTTP, console, future jobs) routes through.

it('refuses at the action when the amount exceeds the awarded percentage', function () {
    $campus = Campus::factory()->create();
    $dossier = decisionCeilingDossier($campus, 'percentage', 30);

    expect(fn () => app(DecideAdjustmentAction::class)->run(
        $dossier,
        ScholarshipAdjustmentDossier::DECISION_REDUCE,
        40.0,
        'reason',
        User::factory()->create()->id,
    ))->toThrow(InvalidArgumentException::class);

    expect($dossier->fresh()->decision_type)->toBeNull();
});

it('refuses at the action when the amount exceeds the awarded fixed amount', function () {
    $campus = Campus::factory()->create();
    $dossier = decisionCeilingDossier($campus, 'fixed_amount', 20_000_000);

    expect(fn () => app(DecideAdjustmentAction::class)->run(
        $dossier,
        ScholarshipAdjustmentDossier::DECISION_REDUCE,
        25_000_000.0,
        'reason',
        User::factory()->create()->id,
    ))->toThrow(InvalidArgumentException::class);
});

it('accepts at the action when the amount is within the award', function () {
    $campus = Campus::factory()->create();
    $dossier = decisionCeilingDossier($campus, 'percentage', 30);

    $updated = app(DecideAdjustmentAction::class)->run(
        $dossier,
        ScholarshipAdjustmentDossier::DECISION_REDUCE,
        15.0,
        'reason',
        User::factory()->create()->id,
    );

    expect($updated->status)->toBe(ScholarshipAdjustmentDossier::STATUS_READY_FOR_DECISION)
        ->and((float) $updated->decision_adjusted_amount)->toBe(15.0);
});

it('discards an amount typed against a non-money outcome', function () {
    // `keep` never reaches Finance, so storing the number would leave the
    // dossier claiming a scholarship change that will never be applied — and
    // the fee-impact preview reads exactly that column.
    $campus = Campus::factory()->create();
    $dossier = decisionCeilingDossier($campus, 'percentage', 30);

    $updated = app(DecideAdjustmentAction::class)->run(
        $dossier,
        ScholarshipAdjustmentDossier::DECISION_KEEP,
        15.0,
        'Kept after review.',
        User::factory()->create()->id,
    );

    expect($updated->decision_type)->toBe(ScholarshipAdjustmentDossier::DECISION_KEEP)
        ->and($updated->decision_adjusted_amount)->toBeNull();
});

it('keeps the amount for a reduction', function () {
    $campus = Campus::factory()->create();
    $dossier = decisionCeilingDossier($campus, 'percentage', 30);

    $updated = app(DecideAdjustmentAction::class)->run(
        $dossier,
        ScholarshipAdjustmentDossier::DECISION_REDUCE,
        15.0,
        'Reduced after review.',
        User::factory()->create()->id,
    );

    expect((float) $updated->decision_adjusted_amount)->toBe(15.0);
});
