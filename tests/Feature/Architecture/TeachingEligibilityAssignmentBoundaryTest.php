<?php

declare(strict_types=1);

it('keeps Delivery assignment decisions off Faculty Workforce persistence', function (): void {
    $deliveryRoot = base_path('app/Modules/Academic/Delivery');
    $violations = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($deliveryRoot)) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname()) ?: '';
        if (preg_match('/use\s+App\\\\Models\\\\Lecture\s*;|\bLecture::|\b(employment_status|contract_(start|end)_date|is_available_for_assignment)\b/', $contents) === 1) {
            $violations[] = $file->getPathname();
        }
    }

    expect($violations)->toBeEmpty(
        'Course Delivery must receive Teaching Eligibility through its shared contract, not Faculty Workforce persistence.',
    );
});

it('keeps Workforce eligibility calculation off Delivery persistence', function (): void {
    $workforceRoot = base_path('app/Modules/Academic/FacultyWorkforce');
    $violations = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($workforceRoot)) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname()) ?: '';
        if (preg_match('/use\s+App\\\\Models\\\\CourseOffering\s*;|\bCourseOffering::/', $contents) === 1) {
            $violations[] = $file->getPathname();
        }
    }

    expect($violations)->toBeEmpty(
        'Faculty Workforce supplies eligibility only; Delivery owns instructor workload and timetable state.',
    );
});

it('keeps routed offering creation from writing instructor assignments directly', function (): void {
    $files = [
        base_path('app/Modules/Academic/Delivery/Http/Web/Admin/CourseOfferingCatalogFormController.php'),
        base_path('app/Modules/Academic/Progression/Http/Web/SemesterEnrollmentController.php'),
    ];
    $violations = [];

    foreach ($files as $file) {
        $contents = file_get_contents($file) ?: '';
        if (preg_match('/CourseOffering::create\(\[(?:(?!\]\);).)*[\'"]lecture_id[\'"]/s', $contents) === 1) {
            $violations[] = $file;
        }
    }

    expect($violations)->toBeEmpty(
        'Routed offering creation must create an unassigned offering before Delivery validates and writes an instructor assignment.',
    );
});
