<?php

declare(strict_types=1);

use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Support\ObligationType\ObligationTypeRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * ADR-0027 / wave-1: every finance_charges.charge_type DB enum value must have
 * a code-owned ObligationTypeRegistry entry so behaviour wiring cannot drift.
 *
 * @return array<int, string>
 */
function obligationRegistryDbEnumValues(string $table, string $column): array
{
    $row = DB::selectOne(
        'SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS '
        .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$table, $column]
    );

    preg_match_all("/'((?:[^']|'')*)'/", (string) ($row->t ?? ''), $matches);

    return array_map(static fn (string $v): string => str_replace("''", "'", $v), $matches[1]);
}

it('fails when a DB charge_type has no obligation type registry entry', function () {
    $dbValues = obligationRegistryDbEnumValues('finance_charges', 'charge_type');

    expect($dbValues)->not->toBeEmpty();

    $missing = array_values(array_filter(
        $dbValues,
        static fn (string $type): bool => ! ObligationTypeRegistry::has($type),
    ));

    expect($missing)->toBe(
        [],
        'DB charge_type values missing from ObligationTypeRegistry: '.implode(', ', $missing)
    );
});

it('keeps registry charge types a superset of FinanceCharge::CHARGE_TYPES', function () {
    foreach (FinanceCharge::CHARGE_TYPES as $chargeType) {
        expect(ObligationTypeRegistry::has($chargeType))->toBeTrue();
    }

    // Planned entitlement-only type is registry-only (not a DB charge_type).
    expect(ObligationTypeRegistry::has(ObligationTypeRegistry::TYPE_EGC_RETAKE))->toBeTrue()
        ->and(in_array(ObligationTypeRegistry::TYPE_EGC_RETAKE, FinanceCharge::CHARGE_TYPES, true))
        ->toBeFalse();
});
