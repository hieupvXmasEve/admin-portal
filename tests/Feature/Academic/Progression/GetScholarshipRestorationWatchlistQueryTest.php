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
use App\Modules\Academic\Progression\Queries\GetScholarshipRestorationWatchlistQuery;
use App\Modules\Finance\Actions\ApproveRestorationProposalAction;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Actions\CreateRestorationProposalAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\ScholarshipSemesterAdjustment;
use App\Shared\Contracts\Academic\ScholarshipRestorationVerdictReader;
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
 * @return array{campus: Campus, fall: Semester, maker: User, checker: User}
 */
function watchlistQueryContext(): array
{
    $campus = Campus::factory()->create();
    $fall = Semester::factory()->create(['start_date' => now()->subMonths(6)]);

    $maker = User::factory()->create();

    $checker = User::factory()->create();
    $role = Role::firstOrCreate(['code' => 'watchlist_query_checker_test'], ['name' => 'Watchlist Query Checker Test']);
    $permission = Permission::firstOrCreate(
        ['code' => 'approve_scholarship_adjustment'],
        ['name' => 'approve_scholarship_adjustment', 'display_name' => 'Approve adjustment', 'module' => 'scholarship_adjustments', 'description' => 'test'],
    );
    DB::table('role_permissions')->insertOrIgnore(['role_id' => $role->id, 'permission_id' => $permission->id, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('campus_user_roles')->insert(['user_id' => $checker->id, 'campus_id' => $campus->id, 'role_id' => $role->id, 'created_at' => now(), 'updated_at' => now()]);

    $proposerRole = Role::firstOrCreate(['code' => 'watchlist_query_proposer_test'], ['name' => 'Watchlist Query Proposer Test']);
    $restorePermission = Permission::firstOrCreate(
        ['code' => 'restore_scholarship'],
        ['name' => 'restore_scholarship', 'display_name' => 'Restore scholarship', 'module' => 'scholarship_adjustments', 'description' => 'test'],
    );
    DB::table('role_permissions')->insertOrIgnore(['role_id' => $proposerRole->id, 'permission_id' => $restorePermission->id, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('campus_user_roles')->insert(['user_id' => $maker->id, 'campus_id' => $campus->id, 'role_id' => $proposerRole->id, 'created_at' => now(), 'updated_at' => now()]);

    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $checker->id);
    app(CampusPermissionReader::class)->forgetPermissionCodesForUserId((int) $maker->id);

    return compact('campus', 'fall', 'maker', 'checker');
}

/**
 * @return array{student: Student, adjustment: ScholarshipSemesterAdjustment}
 */
function watchlistQueryCarriedStudent(array $ctx, float $adjustedAmount = 15.0): array
{
    $student = Student::factory()->create([
        'campus_id' => $ctx['campus']->id, 'intake' => $ctx['fall']->id, 'intake_semester_id' => $ctx['fall']->id,
    ]);

    $definition = ScholarshipDefinition::create([
        'code' => 'WLQ'.uniqid(), 'name' => 'Watchlist query test', 'description' => '30%',
        'type' => 'percentage', 'amount' => 30,
        'valid_from' => now()->subYear()->toDateString(), 'valid_until' => now()->addYear()->toDateString(), 'is_active' => true,
    ]);
    StudentScholarshipAward::create([
        'student_id' => $student->id, 'scholarship_code' => $definition->code, 'awarded_at' => now()->toDateString(),
    ]);

    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => null, 'source_system' => 'finance-test', 'source_kind' => 'watchlist-query-test',
        'source_ref' => 'tuition:'.uniqid('', true), 'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED, 'amount' => 20_000_000, 'currency' => 'VND',
        'pricing_rule_version' => 'tuition:test', 'pricing_snapshot' => ['catalog_rule_version' => 'tuition:test'], 'accepted_at' => now(),
    ]);
    app(CreateFinanceChargeAction::class)->handle([
        'finance_obligation_id' => $obligation->id, 'student_id' => $student->id, 'semester_id' => $ctx['fall']->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM, 'amount' => 20_000_000, 'description' => 'Tuition',
    ]);

    $source = Semester::factory()->create(['start_date' => now()->subMonths(12)]);
    $data = new ScholarshipAdjustmentData(
        student_id: $student->id, source_semester_id: $source->id, target_semester_id: $ctx['fall']->id,
        adjusted_amount: $adjustedAmount, reason: 'Failed courses', academic_dossier_id: random_int(10000, 99999),
        maker_user_id: $ctx['maker']->id, checker_user_id: $ctx['checker']->id,
        award_fingerprint: ScholarshipAdjustmentData::fingerprint($definition->code, $definition->type, (string) $definition->amount),
    );
    $result = app(ScholarshipAdjustmentContract::class)->apply($data);
    $adjustment = ScholarshipSemesterAdjustment::query()->findOrFail($result->adjustment_id);

    return compact('student', 'adjustment');
}

it('returns an empty page for a campus with nothing carried', function () {
    $ctx = watchlistQueryContext();

    $page = app(GetScholarshipRestorationWatchlistQuery::class)->handle($ctx['campus']->id);

    expect($page->total())->toBe(0);
});

it('returns a carried row with CLEAN verdict when the target semester itself has clean finalized grades', function () {
    $ctx = watchlistQueryContext();
    ['student' => $student, 'adjustment' => $adjustment] = watchlistQueryCarriedStudent($ctx);

    $unit = Unit::factory()->create(['unit_type' => 'general']);
    $offering = CourseOffering::factory()->create([
        'semester_id' => $ctx['fall']->id, 'unit_id' => $unit->id, 'campus_id' => $ctx['campus']->id,
    ]);
    AcademicRecord::factory()->create([
        'student_id' => $student->id, 'course_offering_id' => $offering->id, 'semester_id' => $ctx['fall']->id,
        'unit_id' => $unit->id, 'campus_id' => $ctx['campus']->id,
        'is_passed' => true, 'override_pass' => false, 'grade_finalized_date' => now()->subWeek(),
    ]);

    $page = app(GetScholarshipRestorationWatchlistQuery::class)->handle($ctx['campus']->id);

    expect($page->total())->toBe(1);
    $row = $page->items()[0];
    expect($row['adjustment_id'])->toBe($adjustment->id)
        ->and($row['student']['id'])->toBe($student->id)
        ->and($row['verdict'])->toBe(ScholarshipRestorationVerdictReader::CLEAN)
        ->and($row['evaluated_semester']['id'])->toBe($ctx['fall']->id)
        ->and($row['proposal_state'])->toBe(ScholarshipRestorationWatchlistRow::STATE_NONE)
        ->and($row['effective_amount'])->toBe(15.0)
        ->and($row['attendance_min_percentage'])->toBeNull()
        ->and($row['current_registrations'])->toBe([]);
});

it('falls back to NOT_FINALIZED at the target semester when nothing qualifies', function () {
    $ctx = watchlistQueryContext();
    watchlistQueryCarriedStudent($ctx);

    $page = app(GetScholarshipRestorationWatchlistQuery::class)->handle($ctx['campus']->id);

    $row = $page->items()[0];
    expect($row['verdict'])->toBe(ScholarshipRestorationVerdictReader::NOT_FINALIZED)
        ->and($row['evaluated_semester']['id'])->toBe($ctx['fall']->id);
});

it('scans PAST an unfinalized later semester to return the latest FINALIZED one, not the target', function () {
    $ctx = watchlistQueryContext();
    ['student' => $student] = watchlistQueryCarriedStudent($ctx);

    // S2 (after target) is finalized clean. S3 (after S2, still ongoing — no
    // qualifying records at all) must NOT block resolving S2: the scan goes
    // newest-first, sees S3 as NOT_FINALIZED, and continues to S2.
    $s2 = Semester::factory()->create(['start_date' => now()->subMonths(3)]);
    Semester::factory()->create(['start_date' => now()->addMonth()]); // S3 — no records, deliberately unused

    $unit = Unit::factory()->create(['unit_type' => 'general']);
    $offering = CourseOffering::factory()->create([
        'semester_id' => $s2->id, 'unit_id' => $unit->id, 'campus_id' => $ctx['campus']->id,
    ]);
    AcademicRecord::factory()->create([
        'student_id' => $student->id, 'course_offering_id' => $offering->id, 'semester_id' => $s2->id,
        'unit_id' => $unit->id, 'campus_id' => $ctx['campus']->id,
        'is_passed' => true, 'override_pass' => false, 'grade_finalized_date' => now()->subWeek(),
    ]);

    $page = app(GetScholarshipRestorationWatchlistQuery::class)->handle($ctx['campus']->id);

    $row = $page->items()[0];
    expect($row['verdict'])->toBe(ScholarshipRestorationVerdictReader::CLEAN)
        ->and($row['evaluated_semester']['id'])->toBe($s2->id);
});

it('filters by semester_id', function () {
    $ctx = watchlistQueryContext();
    watchlistQueryCarriedStudent($ctx);

    $otherSemester = Semester::factory()->create(['start_date' => now()->addYear()]);

    $matching = app(GetScholarshipRestorationWatchlistQuery::class)
        ->handle($ctx['campus']->id, ['semester_id' => $ctx['fall']->id]);
    $nonMatching = app(GetScholarshipRestorationWatchlistQuery::class)
        ->handle($ctx['campus']->id, ['semester_id' => $otherSemester->id]);

    expect($matching->total())->toBe(1)
        ->and($nonMatching->total())->toBe(0);
});

it('shows the effective (restored) amount for an approved-partial row', function () {
    $ctx = watchlistQueryContext();
    ['adjustment' => $adjustment] = watchlistQueryCarriedStudent($ctx, 15.0);

    $proposal = app(CreateRestorationProposalAction::class)->run($adjustment->id, 'Partial', $ctx['maker']->id, 22.0);
    app(ApproveRestorationProposalAction::class)->run($proposal->id, $ctx['checker']->id);

    $page = app(GetScholarshipRestorationWatchlistQuery::class)->handle($ctx['campus']->id);

    $row = $page->items()[0];
    expect($row['proposal_state'])->toBe(ScholarshipRestorationWatchlistRow::STATE_APPROVED_PARTIAL)
        ->and($row['effective_amount'])->toBe(22.0)
        ->and($row['latest_restored_amount'])->toBe(22.0);
});

it('filters by search on student name or code', function () {
    $ctx = watchlistQueryContext();
    ['student' => $student] = watchlistQueryCarriedStudent($ctx);

    $matching = app(GetScholarshipRestorationWatchlistQuery::class)
        ->handle($ctx['campus']->id, ['search' => $student->student_id]);
    $nonMatching = app(GetScholarshipRestorationWatchlistQuery::class)
        ->handle($ctx['campus']->id, ['search' => 'no-such-student-code-xyz']);

    expect($matching->total())->toBe(1)
        ->and($nonMatching->total())->toBe(0);
});

it('filters by proposal_state', function () {
    $ctx = watchlistQueryContext();
    watchlistQueryCarriedStudent($ctx);

    $none = app(GetScholarshipRestorationWatchlistQuery::class)
        ->handle($ctx['campus']->id, ['proposal_state' => ScholarshipRestorationWatchlistRow::STATE_NONE]);
    $pending = app(GetScholarshipRestorationWatchlistQuery::class)
        ->handle($ctx['campus']->id, ['proposal_state' => ScholarshipRestorationWatchlistRow::STATE_PENDING]);

    expect($none->total())->toBe(1)
        ->and($pending->total())->toBe(0);
});

it('does not include another campus\'s carried adjustments', function () {
    $ctxA = watchlistQueryContext();
    $ctxB = watchlistQueryContext();
    watchlistQueryCarriedStudent($ctxA);
    watchlistQueryCarriedStudent($ctxB);

    $pageA = app(GetScholarshipRestorationWatchlistQuery::class)->handle($ctxA['campus']->id);

    expect($pageA->total())->toBe(1);
});

it('handleAll() ignores per_page entirely, for the export — it must never silently truncate', function () {
    $ctx = watchlistQueryContext();
    watchlistQueryCarriedStudent($ctx);
    watchlistQueryCarriedStudent($ctx);
    watchlistQueryCarriedStudent($ctx);

    // handle() would clamp this to 1 row per page; handleAll() must return
    // every matching row regardless of what per_page says.
    $rows = app(GetScholarshipRestorationWatchlistQuery::class)
        ->handleAll($ctx['campus']->id, ['per_page' => 1]);

    expect($rows)->toHaveCount(3);
});
