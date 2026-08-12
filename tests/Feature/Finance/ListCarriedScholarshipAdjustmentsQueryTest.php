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
use App\Modules\Finance\Actions\ApproveRestorationProposalAction;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Actions\CreateRestorationProposalAction;
use App\Modules\Finance\Actions\RejectRestorationProposalAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\ScholarshipSemesterAdjustment;
use App\Modules\Finance\Queries\ListCarriedScholarshipAdjustmentsQuery;
use App\Shared\Contracts\Finance\DTO\ScholarshipAdjustmentData;
use App\Shared\Contracts\Finance\DTO\ScholarshipRestorationWatchlistRow;
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
 * @return array{campus: Campus, maker: User, checker: User}
 */
function carriedListContext(): array
{
    $campus = Campus::factory()->create();
    $maker = User::factory()->create();

    $checker = User::factory()->create();
    $role = Role::firstOrCreate(['code' => 'carried_list_checker_test'], ['name' => 'Carried List Checker Test']);
    $permission = Permission::firstOrCreate(
        ['code' => 'approve_scholarship_adjustment'],
        ['name' => 'approve_scholarship_adjustment', 'display_name' => 'Approve adjustment', 'module' => 'scholarship_adjustments', 'description' => 'test'],
    );
    DB::table('role_permissions')->insertOrIgnore(['role_id' => $role->id, 'permission_id' => $permission->id, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('campus_user_roles')->insert(['user_id' => $checker->id, 'campus_id' => $campus->id, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now()]);

    $proposerRole = Role::firstOrCreate(['code' => 'carried_list_proposer_test'], ['name' => 'Carried List Proposer Test']);
    $restorePermission = Permission::firstOrCreate(
        ['code' => 'restore_scholarship'],
        ['name' => 'restore_scholarship', 'display_name' => 'Restore scholarship', 'module' => 'scholarship_adjustments', 'description' => 'test'],
    );
    DB::table('role_permissions')->insertOrIgnore(['role_id' => $proposerRole->id, 'permission_id' => $restorePermission->id, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('campus_user_roles')->insert(['user_id' => $maker->id, 'campus_id' => $campus->id, 'role_id' => $proposerRole->id, 'created_at' => now(), 'updated_at' => now()]);

    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $checker->id);
    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $maker->id);

    return compact('campus', 'maker', 'checker');
}

function carriedListAdjustment(array $ctx, float $adjustedAmount = 15.0): ScholarshipSemesterAdjustment
{
    $fall = Semester::factory()->create(['start_date' => now()->subMonths(6)]);
    $source = Semester::factory()->create(['start_date' => now()->subMonths(12)]);

    $student = Student::factory()->create([
        'campus_id' => $ctx['campus']->id, 'intake' => $fall->id, 'intake_semester_id' => $fall->id,
    ]);

    $definition = ScholarshipDefinition::create([
        'code' => 'CARRIED'.uniqid(), 'name' => 'Carried test', 'description' => '30%',
        'type' => 'percentage', 'amount' => 30,
        'valid_from' => now()->subYear()->toDateString(), 'valid_until' => now()->addYear()->toDateString(), 'is_active' => true,
    ]);
    StudentScholarshipAward::create([
        'student_id' => $student->id, 'scholarship_code' => $definition->code, 'awarded_at' => now()->toDateString(),
    ]);

    // A tuition charge must exist on the target semester FIRST so apply()
    // transitions the adjustment straight to `applied` (not left `pending_apply`,
    // which the watchlist query intentionally excludes).
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => null, 'source_system' => 'finance-test', 'source_kind' => 'carried-list-test',
        'source_ref' => 'tuition:'.uniqid('', true), 'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED, 'amount' => 20_000_000, 'currency' => 'VND',
        'pricing_rule_version' => 'tuition:test', 'pricing_snapshot' => ['catalog_rule_version' => 'tuition:test'], 'accepted_at' => now(),
    ]);
    app(CreateFinanceChargeAction::class)->handle([
        'finance_obligation_id' => $obligation->id, 'student_id' => $student->id, 'semester_id' => $fall->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM, 'amount' => 20_000_000, 'description' => 'Tuition',
    ]);

    $data = new ScholarshipAdjustmentData(
        student_id: $student->id, source_semester_id: $source->id, target_semester_id: $fall->id,
        adjusted_amount: $adjustedAmount, reason: 'Failed courses', academic_dossier_id: random_int(10000, 99999),
        maker_user_id: $ctx['maker']->id, checker_user_id: $ctx['checker']->id,
        award_fingerprint: ScholarshipAdjustmentData::fingerprint($definition->code, $definition->type, (string) $definition->amount),
    );
    $result = app(ScholarshipAdjustmentContract::class)->apply($data);

    return ScholarshipSemesterAdjustment::query()->findOrFail($result->adjustment_id);
}

it('excludes a campus with no carried adjustments', function () {
    $ctx = carriedListContext();

    $rows = app(ListCarriedScholarshipAdjustmentsQuery::class)->listCarried($ctx['campus']->id);

    expect($rows)->toBeEmpty();
});

it('includes an adjustment with no proposal at all as state=none', function () {
    $ctx = carriedListContext();
    $adjustment = carriedListAdjustment($ctx);

    $rows = app(ListCarriedScholarshipAdjustmentsQuery::class)->listCarried($ctx['campus']->id);

    expect($rows)->toHaveCount(1);
    $row = $rows->first();
    expect($row->adjustment_id)->toBe($adjustment->id)
        ->and($row->proposal_state)->toBe(ScholarshipRestorationWatchlistRow::STATE_NONE)
        ->and($row->effective_amount)->toBe(15.0);
});

it('includes an adjustment with a pending proposal as state=pending', function () {
    $ctx = carriedListContext();
    $adjustment = carriedListAdjustment($ctx);
    app(CreateRestorationProposalAction::class)->run($adjustment->id, 'Clean result', $ctx['maker']->id);

    $row = app(ListCarriedScholarshipAdjustmentsQuery::class)->listCarried($ctx['campus']->id)->first();

    expect($row->proposal_state)->toBe(ScholarshipRestorationWatchlistRow::STATE_PENDING);
});

it('includes an adjustment with a rejected proposal as state=rejected', function () {
    $ctx = carriedListContext();
    $adjustment = carriedListAdjustment($ctx);
    $proposal = app(CreateRestorationProposalAction::class)->run($adjustment->id, 'Clean result', $ctx['maker']->id);
    app(RejectRestorationProposalAction::class)->run($proposal->id, $ctx['checker']->id);

    $row = app(ListCarriedScholarshipAdjustmentsQuery::class)->listCarried($ctx['campus']->id)->first();

    expect($row->proposal_state)->toBe(ScholarshipRestorationWatchlistRow::STATE_REJECTED);
});

it('includes an approved-PARTIAL restore with its effective amount, and excludes an approved-FULL one', function () {
    $ctx = carriedListContext();
    $partialAdjustment = carriedListAdjustment($ctx, 15.0);
    $fullAdjustment = carriedListAdjustment($ctx, 10.0);

    $partialProposal = app(CreateRestorationProposalAction::class)
        ->run($partialAdjustment->id, 'Partial', $ctx['maker']->id, 22.0);
    app(ApproveRestorationProposalAction::class)->run($partialProposal->id, $ctx['checker']->id);

    $fullProposal = app(CreateRestorationProposalAction::class)
        ->run($fullAdjustment->id, 'Full', $ctx['maker']->id);
    app(ApproveRestorationProposalAction::class)->run($fullProposal->id, $ctx['checker']->id);

    $rows = app(ListCarriedScholarshipAdjustmentsQuery::class)->listCarried($ctx['campus']->id);

    expect($rows)->toHaveCount(1);
    $row = $rows->first();
    expect($row->adjustment_id)->toBe($partialAdjustment->id)
        ->and($row->proposal_state)->toBe(ScholarshipRestorationWatchlistRow::STATE_APPROVED_PARTIAL)
        ->and($row->latest_restored_amount)->toBe(22.0)
        ->and($row->effective_amount)->toBe(22.0);
});

it('scopes rows to the given campus', function () {
    $ctxA = carriedListContext();
    $ctxB = carriedListContext();
    carriedListAdjustment($ctxA);
    carriedListAdjustment($ctxB);

    $rowsA = app(ListCarriedScholarshipAdjustmentsQuery::class)->listCarried($ctxA['campus']->id);

    expect($rowsA)->toHaveCount(1)
        ->and($rowsA->first()->campus_id)->toBe($ctxA['campus']->id);
});
