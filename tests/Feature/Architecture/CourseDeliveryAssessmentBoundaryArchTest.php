<?php

declare(strict_types=1);

it('routes attendance, gradebook, Canvas, and readiness operations through Delivery boundaries', function (): void {
    $files = [
        base_path('app/Http/Controllers/Api/V1/Lecturer/AttendanceController.php'),
        base_path('app/Http/Controllers/Api/V1/Lecturer/GradebookController.php'),
        base_path('app/Http/Controllers/Web/CourseOfferingController.php'),
        base_path('app/Modules/Academic/Http/Web/BulkUpdateClassSessionAttendanceController.php'),
        base_path('app/Modules/Academic/Http/Web/RecordClassSessionAttendanceController.php'),
        base_path('app/Modules/Academic/Http/Web/Canvas/PreviewCanvasGradeSyncController.php'),
        base_path('app/Modules/Academic/Http/Web/Canvas/SyncCanvasGradeController.php'),
        base_path('app/Modules/Academic/Http/Web/RecalculateApplyController.php'),
        base_path('app/Modules/Academic/Http/Web/RecalculatePreviewController.php'),
    ];
    $legacyImports = [];

    foreach ($files as $file) {
        $contents = file_get_contents($file) ?: '';
        if (preg_match('/use\s+App\\\\(?:Services\\\\(?:Canvas\\\\CanvasGradeSyncService|V1\\\\Lecturer\\\\LecturerAttendanceService)|Modules\\\\Academic\\\\(?:Actions\\\\(?:Attendance\\\\(?:RecordAttendanceAction|BulkUpdateClassSessionAttendanceAction)|SaveLecturerGradebookScoresAction)|Queries\\\\(?:GetCourseOfferingOperationalStateQuery|GetLecturerCourseGradebookQuery)))\s*;/', $contents) === 1) {
            $legacyImports[] = $file;
        }
    }

    expect($legacyImports)->toBeEmpty(
        'Routed Delivery operations must resolve Delivery-owned commands, readers, and integration services.',
    );
});

it('keeps Progression from reading Delivery attendance or component-score persistence', function (): void {
    $progressionRoot = base_path('app/Modules/Academic/Progression');
    $violations = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($progressionRoot)) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname()) ?: '';
        if (preg_match('/use\s+App\\\\Models\\\\(?:Attendance|AssessmentComponent|AssessmentComponentDetail|AssessmentComponentDetailScore)\s*;|\\b(?:Attendance|AssessmentComponent|AssessmentComponentDetail|AssessmentComponentDetailScore)::/', $contents) === 1) {
            $violations[] = $file->getPathname();
        }
    }

    expect($violations)->toBeEmpty(
        'Progression must consume finalized Course Results, never Delivery attendance or component-score persistence.',
    );
});

it('keeps Delivery implementations independent of legacy attendance, gradebook, and Canvas classes', function (): void {
    $deliveryRoot = base_path('app/Modules/Academic/Delivery');
    $violations = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($deliveryRoot)) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname()) ?: '';
        if (preg_match('/(?:App\\\\Modules\\\\Academic\\\\(?:Actions\\\\(?:Attendance\\\\(?:RecordAttendanceAction|BulkUpdateClassSessionAttendanceAction)|SaveLecturerGradebookScoresAction)|Queries\\\\(?:GetCourseOfferingOperationalStateQuery|GetLecturerCourseGradebookQuery))|App\\\\Services\\\\(?:Canvas\\\\CanvasGradeSyncService|V1\\\\Lecturer\\\\LecturerAttendanceService))/', $contents) === 1) {
            $violations[] = $file->getPathname();
        }
    }

    expect($violations)->toBeEmpty(
        'Delivery must own its attendance, gradebook, readiness, and Canvas implementations rather than delegate to legacy classes.',
    );
});
