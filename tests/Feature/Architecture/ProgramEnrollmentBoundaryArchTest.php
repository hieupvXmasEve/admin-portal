<?php

declare(strict_types=1);

it('keeps the migrated Student Hub overview on the Program Enrollment reader', function (): void {
    $service = file_get_contents(base_path('app/Services/StudentAcademicSummaryService.php')) ?: '';
    $controller = file_get_contents(base_path('app/Modules/Academic/Progression/Http/Web/StudentAcademicSummaryController.php')) ?: '';

    preg_match('/private function getStudentOverview\(.*?^    }\n\n    \/\*\*/ms', $service, $overviewMethod);
    preg_match('/private function hubStudentContext\(.*?^    }\n\n    \/\*\*/ms', $controller, $hubContextMethod);

    expect($overviewMethod[0] ?? '')->toContain('programEnrollmentReader')
        ->and($overviewMethod[0] ?? '')->not->toMatch('/\$student->(?:status|academic_status|program|specialization|curriculumVersion|intakeSemester|intakeMajorSemester)\b/')
        ->and($hubContextMethod[0] ?? '')->toContain('programEnrollmentReader')
        ->and($hubContextMethod[0] ?? '')->not->toMatch('/\$student->(?:status|program|specialization)\b/');
});

it('keeps the Program Enrollment contract scalar and its actions conventionally shaped', function (): void {
    $contract = file_get_contents(base_path('app/Shared/Contracts/Academic/ProgramEnrollmentReader.php')) ?: '';
    $materializer = file_get_contents(base_path('app/Modules/Academic/Progression/Actions/MaterializeProgramEnrollmentAction.php')) ?: '';

    expect($contract)->toContain('forStudentId(int $studentId)')
        ->and($contract)->not->toContain('use App\\Models\\Student')
        ->and($materializer)->toContain('public static function run(array $data)')
        ->and($materializer)->not->toContain('public function run(');
});
