<?php

declare(strict_types=1);

use App\Modules\Finance\Support\Reporting\FeeMonitorAcadRetGate;

it('keeps retake and resit missing inference disabled until ACAD-RET-001', function () {
    expect(FeeMonitorAcadRetGate::missingInferenceEnabled())->toBeFalse()
        ->and(FeeMonitorAcadRetGate::excludedMissingSources())->toBe(['course_retake', 'exam_resit']);
});
