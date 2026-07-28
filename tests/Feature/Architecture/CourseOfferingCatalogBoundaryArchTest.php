<?php

declare(strict_types=1);

it('keeps course offering creation and editing catalog persistence behind the catalog contract', function (): void {
    $workspace = dirname(__DIR__, 3);
    $files = [
        $workspace.'/app/Modules/Academic/Delivery/Http/Web/Admin/CourseOfferingCatalogFormController.php',
        $workspace.'/app/Modules/Academic/Delivery/Http/Requests/CourseDelivery/StoreCourseOfferingRequest.php',
        $workspace.'/app/Modules/Academic/Delivery/Http/Requests/CourseDelivery/UpdateCourseOfferingRequest.php',
        $workspace.'/app/Modules/Academic/Delivery/Queries/GetCourseOfferingCatalogFormQuery.php',
    ];
    $forbiddenReferences = [
        'App\\Models\\Program',
        'App\\Models\\CurriculumVersion',
        'App\\Models\\CurriculumModule',
        'App\\Models\\Unit',
        'App\\Models\\SyllabusTemplate',
        'App\\Models\\Semester',
    ];
    $violations = [];

    foreach ($files as $file) {
        $contents = file_get_contents($file) ?: '';

        foreach ($forbiddenReferences as $reference) {
            if (str_contains($contents, $reference)) {
                $violations[] = $file.' references '.$reference;
            }
        }
    }

    expect($violations)->toBeEmpty(
        'Course Delivery must resolve catalog references through CourseOfferingCatalogReader, not Catalog persistence models: '.implode(', ', $violations),
    );
});
