<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ScholarshipDefinition;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentScholarshipAward;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\ScholarshipRestorationProposal;
use App\Modules\Finance\Models\ScholarshipSemesterAdjustment;
use App\Shared\Contracts\Finance\DTO\ScholarshipAdjustmentData;
use App\Shared\Contracts\Finance\ScholarshipAdjustmentContract;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

/**
 * Applied adjustment (targeting the given evaluated semester) plus an
 * academic record for that same semester matching the given is_passed
 * outcome — the minimal shape the verdict query and the command both need.
 *
 * @return array{campus: Campus, adjustment: ScholarshipSemesterAdjustment, student: Student}
 */
function commandRestorationFixture(bool $passed): array
{
    $campus = Campus::factory()->create();
    $source = Semester::factory()->create(['start_date' => now()->subMonths(12)]);
    $evaluated = Semester::factory()->create(['start_date' => now()->subMonths(6)]);

    $student = Student::factory()->create([
        'campus_id' => $campus->id,
        'intake' => $source->id,
        'intake_semester_id' => $source->id,
    ]);

    $definition = ScholarshipDefinition::create([
        'code' => 'CMD'.uniqid(),
        'name' => 'Command test scholarship',
        'description' => '30%',
        'type' => 'percentage',
        'amount' => 30,
        'valid_from' => now()->subYear()->toDateString(),
        'valid_until' => now()->addYear()->toDateString(),
        'is_active' => true,
    ]);

    $award = StudentScholarshipAward::create([
        'student_id' => $student->id,
        'scholarship_code' => $definition->code,
        'awarded_at' => now()->toDateString(),
    ]);

    $maker = User::factory()->create();
    $checker = User::factory()->create();

    $role = Role::firstOrCreate(['code' => 'restoration_command_checker_test'], ['name' => 'Restoration Command Checker Test']);
    $permission = Permission::firstOrCreate(
        ['code' => 'approve_scholarship_adjustment'],
        ['name' => 'approve_scholarship_adjustment', 'display_name' => 'Approve adjustment', 'module' => 'scholarship_adjustments', 'description' => 'test'],
    );

    DB::table('role_permissions')->insertOrIgnore([
        'role_id' => $role->id,
        'permission_id' => $permission->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('campus_user_roles')->insert([
        'user_id' => $checker->id,
        'campus_id' => $campus->id,
        'role_id' => $role->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // The command actor (= adjustment creator / proposer) holds restore_scholarship.
    $proposerRole = Role::firstOrCreate(['code' => 'restoration_command_proposer_test'], ['name' => 'Restoration Command Proposer Test']);
    $restorePermission = Permission::firstOrCreate(
        ['code' => 'restore_scholarship'],
        ['name' => 'restore_scholarship', 'display_name' => 'Restore scholarship', 'module' => 'scholarship_adjustments', 'description' => 'test'],
    );
    DB::table('role_permissions')->insertOrIgnore([
        'role_id' => $proposerRole->id, 'permission_id' => $restorePermission->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('campus_user_roles')->insert([
        'user_id' => $maker->id, 'campus_id' => $campus->id, 'role_id' => $proposerRole->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $checker->id);
    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $maker->id);

    // Bill the evaluated semester so the apply() call transitions to `applied`.
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => null,
        'source_system' => 'finance-test',
        'source_kind' => 'scholarship-restoration-command-test',
        'source_ref' => 'tuition:'.uniqid('', true),
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 20_000_000,
        'currency' => 'VND',
        'pricing_rule_version' => 'tuition:test',
        'pricing_snapshot' => ['catalog_rule_version' => 'tuition:test'],
        'accepted_at' => now(),
    ]);

    app(CreateFinanceChargeAction::class)->handle([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $evaluated->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 20_000_000,
        'description' => 'Tuition',
    ]);

    $result = app(ScholarshipAdjustmentContract::class)->apply(new ScholarshipAdjustmentData(
        student_id: $student->id,
        source_semester_id: $source->id,
        target_semester_id: $evaluated->id,
        adjusted_amount: 15.0,
        reason: 'Failed 2 courses',
        academic_dossier_id: 7001,
        maker_user_id: $maker->id,
        checker_user_id: $checker->id,
        award_fingerprint: ScholarshipAdjustmentData::fingerprint(
            (string) $award->scholarship_code,
            (string) $definition->type,
            (string) $definition->amount,
        ),
    ));

    expect($result->accepted)->toBeTrue()
        ->and($result->status)->toBe(ScholarshipSemesterAdjustment::STATUS_APPLIED);

    $unit = Unit::factory()->create(['unit_type' => 'general']);
    $offering = CourseOffering::factory()->create([
        'semester_id' => $evaluated->id,
        'unit_id' => $unit->id,
        'campus_id' => $campus->id,
    ]);

    AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $evaluated->id,
        'unit_id' => $unit->id,
        'campus_id' => $campus->id,
        'is_passed' => $passed,
        'override_pass' => false,
        'grade_finalized_date' => now()->subWeek(),
    ]);

    $adjustment = ScholarshipSemesterAdjustment::query()->findOrFail((int) $result->adjustment_id);

    return compact('campus', 'adjustment', 'student') + ['evaluated' => $evaluated];
}

it('creates a proposal for a clean adjustment', function () {
    $fixture = commandRestorationFixture(passed: true);

    test()->artisan('finance:evaluate-scholarship-restorations', [
        '--campus' => $fixture['campus']->id,
        '--semester' => $fixture['evaluated']->id,
        '--actor' => $fixture['adjustment']->created_by_user_id,
    ])->assertSuccessful();

    $proposal = ScholarshipRestorationProposal::query()
        ->where('scholarship_semester_adjustment_id', $fixture['adjustment']->id)
        ->first();

    expect($proposal)->not->toBeNull()
        ->and($proposal->status)->toBe(ScholarshipRestorationProposal::STATUS_PENDING_APPROVAL);
});

it('skips a still-failing adjustment without creating a proposal', function () {
    $fixture = commandRestorationFixture(passed: false);

    test()->artisan('finance:evaluate-scholarship-restorations', [
        '--campus' => $fixture['campus']->id,
        '--semester' => $fixture['evaluated']->id,
        '--actor' => $fixture['adjustment']->created_by_user_id,
    ])->assertSuccessful();

    expect(ScholarshipRestorationProposal::query()
        ->where('scholarship_semester_adjustment_id', $fixture['adjustment']->id)
        ->exists())->toBeFalse();
});

it('is idempotent on re-run: a second pass does not duplicate the proposal', function () {
    $fixture = commandRestorationFixture(passed: true);

    $run = fn () => test()->artisan('finance:evaluate-scholarship-restorations', [
        '--campus' => $fixture['campus']->id,
        '--semester' => $fixture['evaluated']->id,
        '--actor' => $fixture['adjustment']->created_by_user_id,
    ])->assertSuccessful();

    $run();
    $run();

    expect(ScholarshipRestorationProposal::query()
        ->where('scholarship_semester_adjustment_id', $fixture['adjustment']->id)
        ->count())->toBe(1);
});

it('requires all three options', function () {
    test()->artisan('finance:evaluate-scholarship-restorations', ['--campus' => 1])
        ->assertFailed();
});
