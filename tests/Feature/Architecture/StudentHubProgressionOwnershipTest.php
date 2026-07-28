<?php

declare(strict_types=1);

it('routes Student Hub registrations through Progression and Delivery evidence seams', function (): void {
    $controller = file_get_contents(base_path('app/Modules/Academic/Progression/Http/Web/StudentAcademicSummaryController.php')) ?: '';
    $registrationsQuery = file_get_contents(base_path('app/Modules/Academic/Progression/Queries/GetStudentRegistrationsQuery.php')) ?: '';
    $graduationQuery = file_get_contents(base_path('app/Modules/Academic/Progression/Queries/GetStudentGraduationProgressQuery.php')) ?: '';
    $exportQuery = file_get_contents(base_path('app/Modules/Academic/Progression/Queries/GetStudentAcademicSummaryExportQuery.php')) ?: '';
    $overviewQuery = file_get_contents(base_path('app/Modules/Academic/Progression/Queries/GetStudentHubOverviewQuery.php')) ?: '';
    $scoresQuery = file_get_contents(base_path('app/Modules/Academic/Progression/Queries/GetStudentHubScoresQuery.php')) ?: '';
    $lifecycleQuery = file_get_contents(base_path('app/Modules/Academic/Progression/Queries/GetStudentLifecycleTimelineQuery.php')) ?: '';
    $decisionController = file_get_contents(base_path('app/Modules/Academic/Progression/Http/Web/StudentDecisionController.php')) ?: '';

    expect($controller)
        ->toContain('GetStudentRegistrationsQuery')
        ->toContain('GetStudentGraduationProgressQuery')
        ->toContain('GetStudentAcademicSummaryExportQuery')
        ->toContain('GetStudentHubOverviewQuery')
        ->toContain('GetStudentHubScoresQuery')
        ->toContain('GetStudentLifecycleTimelineQuery')
        ->toContain('GetStudentHubScoreDetailsQuery')
        ->toContain('GetStudentHubCourseScoresQuery')
        ->not->toContain('GetStudentRegistrationsAction')
        ->not->toContain('StudentAcademicSummaryService')
        ->and($registrationsQuery)
        ->toContain('StudentHubRegistrationEvidenceReader')
        ->toContain('StudentHubCourseOutcomeEvidenceReader')
        ->not->toContain('StudentAcademicSummaryService')
        ->and($graduationQuery)
        ->toContain('StudentHubCourseOutcomeEvidenceReader')
        ->not->toContain('AcademicRecord')
        ->not->toContain('StudentAcademicSummaryService')
        ->and($exportQuery)
        ->toContain('StudentHubCourseOutcomeEvidenceReader')
        ->toContain('GetStudentGraduationProgressQuery')
        ->not->toContain('StudentAcademicSummaryService');

    expect($overviewQuery)
        ->toContain('StudentProfileReader')
        ->toContain('StudentHubRegistrationEvidenceReader')
        ->toContain('StudentHubCourseOutcomeEvidenceReader')
        ->not->toContain('StudentAcademicSummaryService');

    expect($scoresQuery)
        ->toContain('StudentHubAssessmentEvidenceReader')
        ->toContain('StudentHubCourseOutcomeEvidenceReader')
        ->toContain('CurriculumModuleCompositionReader')
        ->not->toContain('StudentAcademicSummaryService');

    expect($lifecycleQuery)
        ->toContain('ProgramEnrollmentReader')
        ->toContain('AcademicProgressionEvent')
        ->toContain('StudentDecision')
        ->and($decisionController)
        ->toContain('CreateStudentDecisionAction')
        ->toContain('UpdateStudentDecisionAction')
        ->not->toContain('StudentDecision::query()->create')
        ->not->toContain('$studentDecision->update(');
});
