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
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\ApproveAdjustmentAction;
use App\Modules\Academic\Progression\Actions\ScholarshipAdjustment\DecideAdjustmentAction;
use App\Modules\Academic\Progression\Models\ScholarshipAdjustmentDossier;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\ScholarshipSemesterAdjustment;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

/**
 * @return array{dossier: ScholarshipAdjustmentDossier, campus: Campus, maker: User, checker: User}
 */
function decisionServiceDossier(array $overrides = []): array
{
    $campus = Campus::factory()->create();
    $source = Semester::factory()->create();
    $target = Semester::factory()->create();
    $student = Student::factory()->create(['campus_id' => $campus->id, 'intake' => 1, 'intake_semester_id' => $source->id]);

    $definition = ScholarshipDefinition::create([
        'code' => 'DEC'.uniqid(),
        'name' => 'Decision test scholarship',
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

    $maker = User::factory()->create();
    $checker = User::factory()->create();

    $role = Role::firstOrCreate(['code' => 'decision_checker_test'], ['name' => 'Decision Checker Test']);
    $permission = Permission::firstOrCreate(
        ['code' => 'approve_scholarship_adjustment'],
        ['name' => 'approve_scholarship_adjustment', 'display_name' => 'Approve', 'module' => 'scholarship_adjustments', 'description' => 'test'],
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

    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $checker->id);

    $dossier = ScholarshipAdjustmentDossier::create(array_merge([
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
        // Default to an already-confirmed dossier so money-decision tests pass
        // the P4 gate; gate-specific tests override confirmation_status.
        'confirmation_status' => ScholarshipAdjustmentDossier::CONFIRMATION_CONFIRMED,
        'confirmed_at' => now(),
        'created_by_user_id' => $maker->id,
    ], $overrides));

    return compact('dossier', 'campus', 'maker', 'checker');
}

/** Create a tuition invoice for the dossier's student in its target semester, so Finance actually applies. */
function decisionServiceTuitionCharge(ScholarshipAdjustmentDossier $dossier, float $amount = 20_000_000): FinanceCharge
{
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => null,
        'source_system' => 'academic-decision-test',
        'source_kind' => 'scholarship-adjustment-test',
        'source_ref' => 'tuition:'.uniqid('', true),
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'tuition:test',
        'pricing_snapshot' => ['catalog_rule_version' => 'tuition:test'],
        'accepted_at' => now(),
    ]);

    return app(CreateFinanceChargeAction::class)->handle([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $dossier->student_id,
        'semester_id' => $dossier->target_semester_id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => $amount,
        'description' => 'Tuition',
    ]);
}

it('blocks a decision before the interview is completed without an exception reason', function () {
    $ctx = decisionServiceDossier(['interview_status' => ScholarshipAdjustmentDossier::INTERVIEW_SCHEDULED]);

    expect(fn () => app(DecideAdjustmentAction::class)
        ->run($ctx['dossier'], ScholarshipAdjustmentDossier::DECISION_REDUCE, 15.0, 'Failed 2 courses', $ctx['maker']->id))
        ->toThrow(DomainException::class);
});

it('allows the exception path when the maker holds approve_scholarship_adjustment at the campus', function () {
    $ctx = decisionServiceDossier(['interview_status' => ScholarshipAdjustmentDossier::INTERVIEW_SCHEDULED]);

    // Grant the maker the checker permission for the exception override.
    $role = Role::firstOrCreate(['code' => 'exception_maker_test'], ['name' => 'Exception Maker Test']);
    $permission = Permission::where('code', 'approve_scholarship_adjustment')->first();
    DB::table('role_permissions')->insertOrIgnore([
        'role_id' => $role->id, 'permission_id' => $permission->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('campus_user_roles')->insert([
        'user_id' => $ctx['maker']->id, 'campus_id' => $ctx['campus']->id, 'role_id' => $role->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $ctx['maker']->id);

    $dossier = app(DecideAdjustmentAction::class)->run(
        $ctx['dossier'],
        ScholarshipAdjustmentDossier::DECISION_REDUCE,
        15.0,
        'Urgent case',
        $ctx['maker']->id,
        'Student traveling, interview waived by department head',
    );

    expect($dossier->status)->toBe(ScholarshipAdjustmentDossier::STATUS_READY_FOR_DECISION);
});

it('requires adjusted_amount for a reduce decision', function () {
    $ctx = decisionServiceDossier();

    expect(fn () => app(DecideAdjustmentAction::class)
        ->run($ctx['dossier'], ScholarshipAdjustmentDossier::DECISION_REDUCE, null, 'reason', $ctx['maker']->id))
        ->toThrow(InvalidArgumentException::class);
});

it('lets the proposer approve their own decision when they hold the permission', function () {
    // Product decision: separation of maker and checker is not enforced. What
    // gates approval is the approve permission at the dossier campus; who
    // proposed and who approved both stay on the record.
    $ctx = decisionServiceDossier();
    $decide = app(DecideAdjustmentAction::class);
    $approve = app(ApproveAdjustmentAction::class);

    $role = Role::firstOrCreate(['code' => 'self_approve_test'], ['name' => 'Self Approve Test']);
    $permission = Permission::where('code', 'approve_scholarship_adjustment')->first();
    DB::table('role_permissions')->insertOrIgnore([
        'role_id' => $role->id, 'permission_id' => $permission->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('campus_user_roles')->insert([
        'user_id' => $ctx['maker']->id, 'campus_id' => $ctx['campus']->id, 'role_id' => $role->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $ctx['maker']->id);

    $decide->run($ctx['dossier'], ScholarshipAdjustmentDossier::DECISION_KEEP, null, 'ok', $ctx['maker']->id);

    $updated = $approve->run($ctx['dossier'], $ctx['maker']->id);

    expect($updated->approved_by_user_id)->toBe($ctx['maker']->id)
        ->and($updated->proposed_by_user_id)->toBe($ctx['maker']->id)
        ->and($updated->approved_at)->not->toBeNull();
});

it('rejects approval by a checker without the campus permission', function () {
    $ctx = decisionServiceDossier();
    $decide = app(DecideAdjustmentAction::class);
    $approve = app(ApproveAdjustmentAction::class);
    $decide->run($ctx['dossier'], ScholarshipAdjustmentDossier::DECISION_KEEP, null, 'ok', $ctx['maker']->id);

    $stranger = User::factory()->create();

    expect(fn () => $approve->run($ctx['dossier'], $stranger->id))
        ->toThrow(DomainException::class);
});

it('resolves keep to no_adjustment without calling Finance', function () {
    $ctx = decisionServiceDossier();
    $decide = app(DecideAdjustmentAction::class);
    $approve = app(ApproveAdjustmentAction::class);
    $decide->run($ctx['dossier'], ScholarshipAdjustmentDossier::DECISION_KEEP, null, 'Kept as-is', $ctx['maker']->id);

    $dossier = $approve->run($ctx['dossier'], $ctx['checker']->id);

    expect($dossier->status)->toBe(ScholarshipAdjustmentDossier::STATUS_NO_ADJUSTMENT);
});

it('resolves cancel to cancelled', function () {
    $ctx = decisionServiceDossier();
    $decide = app(DecideAdjustmentAction::class);
    $approve = app(ApproveAdjustmentAction::class);
    $decide->run($ctx['dossier'], ScholarshipAdjustmentDossier::DECISION_CANCEL, null, 'Not applicable', $ctx['maker']->id);

    $dossier = $approve->run($ctx['dossier'], $ctx['checker']->id);

    expect($dossier->status)->toBe(ScholarshipAdjustmentDossier::STATUS_CANCELLED);
});

it('hands off a reduce decision to Finance and marks the dossier applied when an invoice already exists', function () {
    $ctx = decisionServiceDossier();
    decisionServiceTuitionCharge($ctx['dossier']);

    $decide = app(DecideAdjustmentAction::class);
    $approve = app(ApproveAdjustmentAction::class);
    $decide->run($ctx['dossier'], ScholarshipAdjustmentDossier::DECISION_REDUCE, 15.0, 'Failed 2 courses', $ctx['maker']->id);

    $dossier = $approve->run($ctx['dossier'], $ctx['checker']->id);

    expect($dossier->status)->toBe(ScholarshipAdjustmentDossier::STATUS_APPLIED)
        ->and(ScholarshipSemesterAdjustment::query()
            ->where('academic_dossier_id', $dossier->id)
            ->where('adjusted_amount', 15.0)
            ->exists())->toBeTrue();
});

it('hands off a suspend_full decision as adjusted_amount zero when an invoice already exists', function () {
    $ctx = decisionServiceDossier();
    decisionServiceTuitionCharge($ctx['dossier']);

    $decide = app(DecideAdjustmentAction::class);
    $approve = app(ApproveAdjustmentAction::class);
    $decide->run($ctx['dossier'], ScholarshipAdjustmentDossier::DECISION_SUSPEND_FULL, 0.0, 'Full suspension', $ctx['maker']->id);

    $dossier = $approve->run($ctx['dossier'], $ctx['checker']->id);

    expect($dossier->status)->toBe(ScholarshipAdjustmentDossier::STATUS_APPLIED)
        ->and(ScholarshipSemesterAdjustment::query()
            ->where('academic_dossier_id', $dossier->id)
            ->where('adjusted_amount', 0.0)
            ->exists())->toBeTrue();
});

it('does NOT mark the dossier applied when no invoice exists yet — status stays approved', function () {
    // Regression test for the pending_apply mislabel: Finance accepts and
    // stores the adjustment for later, but no money has actually moved yet.
    $ctx = decisionServiceDossier();
    $decide = app(DecideAdjustmentAction::class);
    $approve = app(ApproveAdjustmentAction::class);
    $decide->run($ctx['dossier'], ScholarshipAdjustmentDossier::DECISION_REDUCE, 15.0, 'Failed 2 courses', $ctx['maker']->id);

    $dossier = $approve->run($ctx['dossier'], $ctx['checker']->id);

    expect($dossier->status)->toBe(ScholarshipAdjustmentDossier::STATUS_APPROVED)
        ->and(ScholarshipSemesterAdjustment::query()
            ->where('academic_dossier_id', $dossier->id)
            ->where('status', ScholarshipSemesterAdjustment::STATUS_PENDING_APPLY)
            ->exists())->toBeTrue();
});

it('rejects a new decision on a dossier that is already applied', function () {
    $ctx = decisionServiceDossier();
    decisionServiceTuitionCharge($ctx['dossier']);

    $decide = app(DecideAdjustmentAction::class);
    $approve = app(ApproveAdjustmentAction::class);
    $decide->run($ctx['dossier'], ScholarshipAdjustmentDossier::DECISION_REDUCE, 15.0, 'first pass', $ctx['maker']->id);
    $applied = $approve->run($ctx['dossier'], $ctx['checker']->id);

    expect(fn () => $decide->run($applied, ScholarshipAdjustmentDossier::DECISION_SUSPEND_FULL, 0.0, 'second pass', $ctx['maker']->id))
        ->toThrow(DomainException::class);
});

it('rejects the defer decision type as unsupported', function () {
    $ctx = decisionServiceDossier();

    expect(fn () => app(DecideAdjustmentAction::class)
        ->run($ctx['dossier'], ScholarshipAdjustmentDossier::DECISION_DEFER, null, 'reason', $ctx['maker']->id))
        ->toThrow(InvalidArgumentException::class);
});

it('approved decisions are immutable — a second approve call is rejected', function () {
    $ctx = decisionServiceDossier();
    $decide = app(DecideAdjustmentAction::class);
    $approve = app(ApproveAdjustmentAction::class);
    $decide->run($ctx['dossier'], ScholarshipAdjustmentDossier::DECISION_KEEP, null, 'ok', $ctx['maker']->id);
    $approve->run($ctx['dossier'], $ctx['checker']->id);

    expect(fn () => $approve->run($ctx['dossier']->fresh(), $ctx['checker']->id))
        ->toThrow(DomainException::class);
});
