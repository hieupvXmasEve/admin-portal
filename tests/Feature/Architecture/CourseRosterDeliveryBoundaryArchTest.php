<?php

declare(strict_types=1);

it('keeps new routed staff roster writes inside Delivery commands', function (): void {
    $files = [
        base_path('app/Modules/Academic/Delivery/Http/Web/Admin/CourseRegistrationController.php'),
        base_path('app/Modules/Academic/Delivery/Http/Web/Admin/CourseOfferingRegistrationController.php'),
        base_path('app/Modules/Academic/Delivery/Http/Web/Admin/CourseOfferingRosterController.php'),
        base_path('app/Modules/Academic/Progression/Http/Web/SemesterEnrollmentController.php'),
    ];
    $violations = [];

    foreach ($files as $file) {
        $contents = file_get_contents($file) ?: '';
        if (preg_match('/CourseRegistration::(?:create|query\(\)->create)\(|new\s+CourseRegistration\b|courseRegistrations\(\)->delete\(\)|->update\(\[\s*[\'\"]registration_status[\'\"]/', $contents) === 1) {
            $violations[] = $file;
        }
    }

    expect($violations)->toBeEmpty(
        'Routed staff roster writes must delegate to Delivery commands, not create CourseRegistration records directly.',
    );
});

it('keeps Delivery roster commands off Student and Catalog persistence', function (): void {
    $deliveryRoot = base_path('app/Modules/Academic/Delivery');
    $violations = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($deliveryRoot)) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname()) ?: '';
        if (preg_match('/use\s+App\\\\Models\\\\(?:Student|Unit|Semester)\s*;|\b(?:Student|Unit|Semester)::/', $contents) === 1) {
            $violations[] = $file->getPathname();
        }
    }

    expect($violations)->toBeEmpty(
        'Delivery must obtain Student identity and Catalog facts through owner contracts, not their persistence models.',
    );
});

it('keeps Delivery roster moves off Progression attempt persistence', function (): void {
    $action = base_path('app/Modules/Academic/Delivery/Actions/MoveStudentBetweenCourseOfferingSectionsAction.php');
    $contents = file_get_contents($action) ?: '';

    expect($contents)
        ->not->toContain('App\\Models\\AcademicRecord')
        ->toContain('CourseOfferingAttemptWriter');
});

it('presents staff registrations through owner contracts instead of hydrated foreign relations', function (): void {
    $files = [
        base_path('app/Modules/Academic/Delivery/Queries/ListCourseRegistrationsQuery.php'),
        base_path('app/Modules/Academic/Delivery/Queries/GetCourseRegistrationFormOptionsQuery.php'),
        base_path('app/Modules/Academic/Delivery/Queries/GetAvailableCourseRegistrationsQuery.php'),
        base_path('app/Modules/Academic/Delivery/Queries/ListStudentCourseRegistrationsQuery.php'),
        base_path('app/Modules/Academic/Delivery/Http/Web/Admin/CourseRegistrationController.php'),
    ];

    foreach ($files as $file) {
        $contents = file_get_contents($file) ?: '';

        expect($contents)
            ->not->toMatch('/->(?:with|load)\([^;]*(?:student|unit|lecture|semester)/')
            ->not->toMatch('/join\([\'\"](?:students|units|lectures|semesters)/');
    }

    $presenter = file_get_contents(base_path('app/Modules/Academic/Delivery/Support/CourseRegistrationPresenter.php')) ?: '';

    expect($presenter)
        ->toContain('StudentSerializedReferenceReader')
        ->toContain('CourseOfferingCatalogReader')
        ->toContain('LecturerReferenceReader')
        ->not->toMatch('/use\s+App\\\\Models\\\\(?:Student|Unit|Semester|Lecture)\s*;/');
});
