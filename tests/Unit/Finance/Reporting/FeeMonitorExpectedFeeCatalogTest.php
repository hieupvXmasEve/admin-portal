<?php

declare(strict_types=1);

use App\Modules\Finance\Support\Reporting\FeeMonitorExpectedFeeCatalog as Catalog;

it('treats only tuition, EGC, retake and resit as mandatory fees', function () {
    expect(Catalog::isMandatory(Catalog::SOURCE_TUITION_PLAN))->toBeTrue()
        ->and(Catalog::isMandatory(Catalog::SOURCE_EGC_TUITION))->toBeTrue()
        ->and(Catalog::isMandatory(Catalog::SOURCE_COURSE_RETAKE))->toBeTrue()
        ->and(Catalog::isMandatory(Catalog::SOURCE_EXAM_RESIT))->toBeTrue()
        ->and(Catalog::isMandatory(Catalog::SOURCE_ADMISSION_ENROLLMENT))->toBeFalse()
        ->and(Catalog::isMandatory(Catalog::SOURCE_BHYT))->toBeFalse();
});

it('never infers missing fees for non-mandatory sources (BHYT, admission)', function () {
    $missingInferenceSources = Catalog::firstPassMissingInferenceSources();

    expect($missingInferenceSources)
        ->not->toContain(Catalog::SOURCE_BHYT)
        ->not->toContain(Catalog::SOURCE_ADMISSION_ENROLLMENT)
        ->toContain(Catalog::SOURCE_TUITION_PLAN)
        ->toContain(Catalog::SOURCE_EGC_TUITION);
});
