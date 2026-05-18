<?php

declare(strict_types=1);

use App\Modules\Finance\Enums\NonAcademicChargeTypeEnum;
use Illuminate\Support\Facades\DB;

/**
 * Verifies NonAcademicChargeTypeEnum::values() ⊆ DB ENUM for finance_charges.charge_type.
 *
 * Fails if a new enum case was added in code but the migration has not run.
 */
it('every NonAcademicChargeTypeEnum value exists in the db charge_type column', function () {
    $rows = DB::select("SHOW COLUMNS FROM finance_charges WHERE Field = 'charge_type'");

    expect($rows)->not->toBeEmpty('finance_charges.charge_type column not found');

    // Extract values from e.g. "enum('tuition_term','bhyt',...)"
    preg_match_all("/'([^']+)'/", $rows[0]->Type, $matches);
    $dbValues = $matches[1];

    foreach (NonAcademicChargeTypeEnum::values() as $enumValue) {
        // toContain takes only the expected value; message inlined via fail context.
        expect(in_array($enumValue, $dbValues, true))->toBeTrue(
            "NonAcademicChargeTypeEnum case '{$enumValue}' is absent from the DB ENUM — run the migration.",
        );
    }
});

it('bhyt is present in the db charge_type enum column', function () {
    $rows = DB::select("SHOW COLUMNS FROM finance_charges WHERE Field = 'charge_type'");

    expect($rows[0]->Type)->toContain("'bhyt'");
});
