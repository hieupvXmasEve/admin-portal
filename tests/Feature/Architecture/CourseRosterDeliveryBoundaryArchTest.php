<?php

declare(strict_types=1);

it('keeps new routed staff roster writes inside Delivery commands', function (): void {
    $files = [
        base_path('app/Http/Controllers/Web/CourseRegistrationController.php'),
        base_path('app/Http/Controllers/Web/CourseOfferingController.php'),
        base_path('app/Http/Controllers/Web/SemesterEnrollmentController.php'),
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
