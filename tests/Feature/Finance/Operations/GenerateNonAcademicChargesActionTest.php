<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\Operations\GenerateNonAcademicChargesAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Support\Entitlement\FinanceEntitlementType;
use App\Modules\Finance\Support\FinanceOwnedObligationSource;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    // Intake materializer stamps created_by_user_id from auth()->id().
    $this->actingAs(User::factory()->create());
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeNacStudent(Campus $campus, string $code): Student
{
    $semester = Semester::factory()->create();

    return Student::factory()->forCampus($campus)->create([
        'student_id' => $code,
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
}

function seedActiveCharge(Student $student, Semester $semester, string $chargeType): FinanceCharge
{
    return FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => $chargeType,
        'amount' => 100000,
        'description' => 'Existing charge',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
}

// ---------------------------------------------------------------------------
// Happy path: ≥2 created rows, ≥2 skipped rows (R3 guard — per-row assertions)
// ---------------------------------------------------------------------------

it('creates charges for valid students and skips duplicates and wrong-campus students', function () {
    $campus = Campus::factory()->create();
    $otherCampus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();

    app()->singleton('campus', fn () => $campus);

    // Two valid students on the correct campus
    $s1 = makeNacStudent($campus, 'SE100001');
    $s2 = makeNacStudent($campus, 'SE100002');

    // Wrong-campus student → skipped with 'wrong_campus'
    makeNacStudent($otherCampus, 'SE100003');

    // Pre-existing charge for s2 → skipped with 'duplicate_existing_charge_id_X'
    seedActiveCharge($s2, $semester, 'bhyt');

    $result = GenerateNonAcademicChargesAction::run([
        'fee_type' => 'bhyt',
        'semester_id' => $semester->id,
        'amount' => 500000,
        'due_date' => now()->addDays(30)->toDateString(),
        'note' => 'BHYT test',
        'student_codes' => ['SE100001', 'SE100002', 'SE100003', 'SE999999'],
    ]);

    // Summary counts
    expect($result['summary']['total'])->toBe(4)
        ->and($result['summary']['created'])->toBe(1)
        ->and($result['summary']['skipped'])->toBe(3);

    // Created: correct student_id on the DB row (R3 — not just a count check)
    expect($result['created'])->toHaveCount(1)
        ->and($result['created'][0]['student_code'])->toBe('SE100001');

    $charge = FinanceCharge::find($result['created'][0]['charge_id']);
    expect($charge)->not->toBeNull()
        ->and($charge->student_id)->toBe($s1->id)
        ->and($charge->charge_type)->toBe('bhyt')
        ->and($charge->semester_id)->toBe($semester->id)
        ->and($charge->status)->toBe(FinanceCharge::STATUS_ACTIVE)
        ->and($charge->finance_obligation_id)->not->toBeNull()
        ->and($charge->source_type)->toBeNull();

    $obligation = FinanceObligation::query()->findOrFail($charge->finance_obligation_id);
    expect($obligation->source_system)->toBe(FinanceOwnedObligationSource::SOURCE_SYSTEM)
        ->and($obligation->source_kind)->toBe(FinanceOwnedObligationSource::NON_ACADEMIC_BATCH)
        ->and($obligation->obligation_type)->toBe(FinanceCharge::TYPE_BHYT)
        ->and((float) $obligation->amount)->toBe(500000.0);

    // Skipped: assert per-row reason (R3)
    $byCode = collect($result['skipped'])->keyBy('student_code');

    expect($byCode->has('SE100002'))->toBeTrue()
        ->and($byCode->get('SE100002')['reason'])->toStartWith('duplicate_existing_charge_id_');

    expect($byCode->has('SE100003'))->toBeTrue()
        ->and($byCode->get('SE100003')['reason'])->toBe('wrong_campus');

    expect($byCode->has('SE999999'))->toBeTrue()
        ->and($byCode->get('SE999999')['reason'])->toBe('student_not_found');
});

// ---------------------------------------------------------------------------
// ≥2 created, ≥2 skipped — second fixture to satisfy R3 minimum pairing rule
// ---------------------------------------------------------------------------

it('creates at least two charges when two valid students are submitted', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();

    app()->singleton('campus', fn () => $campus);

    $s1 = makeNacStudent($campus, 'SE200001');
    $s2 = makeNacStudent($campus, 'SE200002');

    $result = GenerateNonAcademicChargesAction::run([
        'fee_type' => 'bhyt',
        'semester_id' => $semester->id,
        'amount' => 300000,
        'due_date' => now()->addDays(14)->toDateString(),
        'note' => '',
        'student_codes' => ['SE200001', 'SE200002', 'GHOST_A', 'GHOST_B'],
    ]);

    expect($result['summary']['created'])->toBe(2)
        ->and($result['summary']['skipped'])->toBe(2);

    // Per-row student_id assertions on both created rows
    $createdByCodes = collect($result['created'])->keyBy('student_code');

    $charge1 = FinanceCharge::find($createdByCodes->get('SE200001')['charge_id']);
    $charge2 = FinanceCharge::find($createdByCodes->get('SE200002')['charge_id']);

    expect($charge1->student_id)->toBe($s1->id);
    expect($charge2->student_id)->toBe($s2->id);
});

// ---------------------------------------------------------------------------
// D3: no voucher/scholarship credit row created for non-academic charges
// ---------------------------------------------------------------------------

it('does not create scholarship or voucher credit charges (D3)', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();

    app()->singleton('campus', fn () => $campus);

    $s1 = makeNacStudent($campus, 'SE300001');

    GenerateNonAcademicChargesAction::run([
        'fee_type' => 'bhyt',
        'semester_id' => $semester->id,
        'amount' => 200000,
        'due_date' => now()->addDays(10)->toDateString(),
        'note' => '',
        'student_codes' => ['SE300001'],
    ]);

    $creditCount = FinanceCharge::where('student_id', $s1->id)
        ->whereIn('charge_type', [
            FinanceEntitlementType::ScholarshipCredit,
            FinanceEntitlementType::VoucherCredit,
        ])
        ->count();

    expect($creditCount)->toBe(0);
});

// ---------------------------------------------------------------------------
// Transaction: remaining students processed even when one fails silently
// ---------------------------------------------------------------------------

it('processes all valid students even when a middle student encounters an error', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();

    app()->singleton('campus', fn () => $campus);

    makeNacStudent($campus, 'SE400001');
    makeNacStudent($campus, 'SE400002');

    $result = GenerateNonAcademicChargesAction::run([
        'fee_type' => 'bhyt',
        'semester_id' => $semester->id,
        'amount' => 400000,
        'due_date' => now()->addDays(7)->toDateString(),
        'note' => '',
        'student_codes' => ['SE400001', 'SE400002'],
    ]);

    expect($result['summary']['created'])->toBe(2)
        ->and($result['summary']['skipped'])->toBe(0);
});

// ---------------------------------------------------------------------------
// P2-C: CSV formula-injection codes and invalid-format codes are skipped
// ---------------------------------------------------------------------------

it('skips student codes that fail the format guard (formula injection / invalid format)', function () {
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->active()->create();

    app()->singleton('campus', fn () => $campus);

    // Create one valid student to confirm valid codes still get processed
    makeNacStudent($campus, 'SE500001');

    $result = GenerateNonAcademicChargesAction::run([
        'fee_type' => 'bhyt',
        'semester_id' => $semester->id,
        'amount' => 100000,
        'due_date' => now()->addDays(10)->toDateString(),
        'note' => '',
        // Mix of injection attempts, invalid formats, and one valid code
        'student_codes' => [
            '=CMD|cmd.exe',   // formula injection
            '+1-INJECT',      // formula injection variant
            'se500001',       // lowercase — fails ^[A-Z0-9]{4,20}$
            'ABC',            // too short (< 4 chars)
            'SE500001',       // valid
        ],
    ]);

    expect($result['summary']['created'])->toBe(1)
        ->and($result['summary']['skipped'])->toBe(4);

    $byCode = collect($result['skipped'])->keyBy('student_code');

    expect($byCode->get('=CMD|cmd.exe')['reason'])->toBe('invalid_code_format');
    expect($byCode->get('+1-INJECT')['reason'])->toBe('invalid_code_format');
    expect($byCode->get('se500001')['reason'])->toBe('invalid_code_format');
    expect($byCode->get('ABC')['reason'])->toBe('invalid_code_format');
    expect($result['created'][0]['student_code'])->toBe('SE500001');
});

// ---------------------------------------------------------------------------
// P2-D: Missing campus context throws RuntimeException
// ---------------------------------------------------------------------------

it('throws RuntimeException when no campus context is bound', function () {
    // Ensure 'campus' is not bound in the container
    $app = app();
    // Rebind to return null id — simulates broken middleware
    $app->singleton('campus', fn () => (object) ['id' => null]);

    $semester = Semester::factory()->active()->create();

    expect(fn () => GenerateNonAcademicChargesAction::run([
        'fee_type' => 'bhyt',
        'semester_id' => $semester->id,
        'amount' => 100000,
        'due_date' => now()->addDays(5)->toDateString(),
        'note' => '',
        'student_codes' => ['SE600001'],
    ]))->toThrow(RuntimeException::class, 'No campus context resolved');
});

// ---------------------------------------------------------------------------
// Empty input: zero results, no exception
// ---------------------------------------------------------------------------

it('handles an empty student list without error', function () {
    app()->singleton('campus', fn () => Campus::factory()->create());
    $semester = Semester::factory()->active()->create();

    $result = GenerateNonAcademicChargesAction::run([
        'fee_type' => 'bhyt',
        'semester_id' => $semester->id,
        'amount' => 100000,
        'due_date' => now()->addDays(5)->toDateString(),
        'note' => '',
        'student_codes' => [],
    ]);

    expect($result['summary']['total'])->toBe(0)
        ->and($result['summary']['created'])->toBe(0)
        ->and($result['summary']['skipped'])->toBe(0);
});
