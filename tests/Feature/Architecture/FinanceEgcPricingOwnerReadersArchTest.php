<?php

declare(strict_types=1);

it('keeps EGC and tuition pricing reads on Registry and Progression contracts', function (): void {
    $files = [
        base_path('app/Modules/Finance/Actions/Egc/ApplyEgcCarryForwardAction.php'),
        base_path('app/Modules/Finance/Actions/Egc/ApplyEgcMajorEntryCreditAction.php'),
        base_path('app/Modules/Finance/Actions/Egc/ApplyEgcRetakeDiscountAction.php'),
        base_path('app/Modules/Finance/Actions/Egc/BuildEgcCarryForwardPlanAction.php'),
        base_path('app/Modules/Finance/Actions/Egc/GenerateEgcChargesAction.php'),
        base_path('app/Modules/Finance/Actions/Egc/ReconcileEgcChargesAfterSyncAction.php'),
        base_path('app/Modules/Finance/Actions/Major/GenerateMajorChargesAction.php'),
        base_path('app/Modules/Finance/Actions/Major/SubmitTuitionTermDebitAction.php'),
        base_path('app/Modules/Finance/Queries/Egc/ListEgcCarryForwardCandidatesQuery.php'),
        base_path('app/Modules/Finance/Queries/Egc/ListEgcRetakeAdjustmentsQuery.php'),
        base_path('app/Modules/Finance/Queries/Egc/PreviewEgcChargeGenerationQuery.php'),
        base_path('app/Modules/Finance/Queries/Major/PreviewMajorChargeGenerationQuery.php'),
        base_path('app/Modules/Finance/Services/DeferChargeResolver.php'),
        base_path('app/Modules/Finance/Support/EgcBlockGenerationClassifier.php'),
        base_path('app/Modules/Finance/Support/StudentChargeTimingResolver.php'),
        base_path('app/Modules/Finance/Support/VoucherDiscountAmountResolver.php'),
    ];

    $violations = [];

    foreach ($files as $file) {
        $contents = file_get_contents($file) ?: '';

        if (preg_match('/use\s+App\\\\Models\\\\(?:Student|AcademicRecord|CourseResult|EgcBlock|EgcRetakeDiscountLink|Transcript)\s*;|\\b(?:Student|AcademicRecord|CourseResult|EgcBlock|Transcript)::|(?:academic_records|course_results|course_registrations)/', $contents) === 1) {
            $violations[] = $file;
        }
    }

    expect($violations)->toBeEmpty('Finance EGC and pricing reads must use owner reader DTOs.');
});
