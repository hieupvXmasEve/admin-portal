<?php

declare(strict_types=1);

it('routes Student Hub registrations through Progression and Delivery evidence seams', function (): void {
    $controller = file_get_contents(base_path('app/Modules/Academic/Http/Web/StudentAcademicSummaryController.php')) ?: '';
    $registrationsQuery = file_get_contents(base_path('app/Modules/Academic/Progression/Queries/GetStudentRegistrationsQuery.php')) ?: '';
    $graduationQuery = file_get_contents(base_path('app/Modules/Academic/Progression/Queries/GetStudentGraduationProgressQuery.php')) ?: '';

    expect($controller)
        ->toContain('GetStudentRegistrationsQuery')
        ->toContain('GetStudentGraduationProgressQuery')
        ->not->toContain('GetStudentRegistrationsAction')
        ->and($registrationsQuery)
        ->toContain('StudentHubRegistrationEvidenceReader')
        ->toContain('StudentHubCourseOutcomeEvidenceReader')
        ->not->toContain('StudentAcademicSummaryService')
        ->and($graduationQuery)
        ->toContain('StudentHubCourseOutcomeEvidenceReader')
        ->not->toContain('AcademicRecord')
        ->not->toContain('StudentAcademicSummaryService');
});
