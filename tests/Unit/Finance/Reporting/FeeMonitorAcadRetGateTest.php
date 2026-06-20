<?php

declare(strict_types=1);

use App\Modules\Finance\Support\Reporting\FeeMonitorAcadRetGate;

it('enables retake and resit missing inference now that ACAD-RET-001 is in place', function () {
    expect(FeeMonitorAcadRetGate::missingInferenceEnabled())->toBeTrue()
        ->and(FeeMonitorAcadRetGate::excludedMissingSources())->toBe([]);
});
