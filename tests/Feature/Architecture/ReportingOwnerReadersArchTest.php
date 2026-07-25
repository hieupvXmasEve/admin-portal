<?php

declare(strict_types=1);

use App\Modules\AI\Support\EntityCatalog;
use App\Modules\AI\Support\StudentProfileSectionCatalog;
use App\Shared\Contracts\Academic\AcademicReportReader;
use App\Shared\Contracts\Academic\StudentDashboardReader;
use App\Shared\Contracts\Platform\StaffDashboardChartReader;
use App\Shared\Contracts\Platform\StaffDashboardStatsReader;

it('binds dashboard and report surfaces to readers with declared freshness and scope', function (): void {
    foreach ([
        StaffDashboardStatsReader::class,
        StaffDashboardChartReader::class,
        AcademicReportReader::class,
        StudentDashboardReader::class,
    ] as $readerContract) {
        $reader = app($readerContract);

        expect($reader->freshness())->not->toBe('')
            ->and($reader->permissionScope())->not->toBe('')
            ->and($reader->fieldOwnership())->not->toBe([]);

        expect(array_values($reader->fieldOwnership()))->each->toBeString();
    }
});

it('declares freshness and permission scope for every AI entity and profile field group', function (): void {
    foreach (app(EntityCatalog::class)->entities() as $definition) {
        expect($definition['required_permission'] ?? null)->not->toBeNull()
            ->and($definition['campus_scope_rule'] ?? null)->not->toBeNull()
            ->and($definition['freshness_rule'] ?? null)->not->toBeNull()
            ->and($definition['source_reader'] ?? null)->not->toBeNull();
    }

    foreach (app(StudentProfileSectionCatalog::class)->sections() as $definition) {
        expect($definition['required_permission'] ?? null)->not->toBeNull()
            ->and($definition['freshness_rule'] ?? null)->not->toBeNull()
            ->and($definition['source_reader'] ?? null)->not->toBeNull();
    }
});

it('keeps reporting adapters free of state-changing application calls', function (): void {
    $files = [
        base_path('app/Http/Controllers/Web/DashboardController.php'),
        base_path('app/Http/Controllers/Api/V1/Student/DashboardController.php'),
        base_path('app/Http/Controllers/Web/Admin/Academic/AcademicReportController.php'),
        base_path('app/Http/Controllers/Api/Admin/AcademicReportController.php'),
    ];

    $violations = collect($files)
        ->filter(fn (string $file): bool => preg_match('/->(?:create|update|save|delete|forceDelete)\s*\(/', file_get_contents($file) ?: '') === 1)
        ->values()
        ->all();

    expect($violations)->toBe([]);
});

it('protects report exports and fails closed for missing dashboard campus scope', function (): void {
    $reportRoute = file_get_contents(base_path('routes/api/admin/academic.php')) ?: '';
    $reportRequest = file_get_contents(base_path('app/Http/Requests/Academic/GetAcademicReportRequest.php')) ?: '';
    $dashboardController = file_get_contents(base_path('app/Http/Controllers/Web/DashboardController.php')) ?: '';
    $dashboardStats = file_get_contents(base_path('app/Services/DashboardStatsService.php')) ?: '';
    $dashboardCharts = file_get_contents(base_path('app/Services/DashboardChartsService.php')) ?: '';

    expect($reportRoute)->toContain("middleware('can:view_academic_report')")
        ->and($reportRequest)->toContain("can('view_academic_report')")
        ->and($dashboardController)->toContain('AccessDeniedHttpException')
        ->and($dashboardStats)->toContain('AccessDeniedHttpException')
        ->toContain('requireCampusId($this->getCampusId())')
        ->toContain("whereHas('student'")
        ->and($dashboardCharts)->toContain('AccessDeniedHttpException');
});
