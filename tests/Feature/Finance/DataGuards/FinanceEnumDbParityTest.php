<?php

declare(strict_types=1);

use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\Entitlement\FinanceEntitlementType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * FIN-25: keep model value lists in lockstep with the DB enum columns so drift
 * (e.g. a charge type added to the DB but missing from CHARGE_TYPES) fails CI.
 *
 * @return array<int, string>
 */
function dbEnumValues(string $table, string $column): array
{
    $row = DB::selectOne(
        'SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS '
        .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column]
    );

    preg_match_all("/'((?:[^']|'')*)'/", (string) ($row->t ?? ''), $matches);

    return array_map(static fn (string $v): string => str_replace("''", "'", $v), $matches[1]);
}

it('keeps active and historical charge type registries in sync with the charge_type DB enum', function () {
    $dbValues = dbEnumValues('finance_charges', 'charge_type');

    sort($dbValues);
    $modelValues = [
        ...FinanceCharge::CHARGE_TYPES,
        ...FinanceEntitlementType::HISTORICAL_CHARGE_TYPES,
    ];
    sort($modelValues);

    expect($modelValues)->toBe($dbValues);
});

it('includes bhyt but excludes the retired course_fee charge type', function () {
    expect(FinanceCharge::CHARGE_TYPES)
        ->toContain(FinanceCharge::TYPE_BHYT)
        ->not->toContain('course_fee');
});

it('only marks invoice statuses that exist in the DB enum as non-reusable', function () {
    $dbValues = dbEnumValues('student_invoices', 'status');

    expect(StudentInvoice::NON_REUSABLE_FOR_CHARGE_GENERATION_STATUSES)
        ->each->toBeIn($dbValues);
});

it('keeps historical credit identifiers outside the active charge registry', function () {
    expect(FinanceEntitlementType::HISTORICAL_CHARGE_TYPES)
        ->each->not->toBeIn(FinanceCharge::CHARGE_TYPES);
});
