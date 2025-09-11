<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\Web\DashboardStatsResource;
use App\Http\Responses\ApiResponse;
use App\Services\DashboardChartsService;
use App\Services\DashboardStatsService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardStatsService $statsService,
        private readonly DashboardChartsService $chartsService
    ) {}

    public function index()
    {
        $stats = $this->statsService->getStats();
        $alerts = $this->statsService->getAlerts();

        return Inertia::render('dashboard/Dashboard', [
            'stats' => (new DashboardStatsResource($stats))->toArray(request()),
            'alerts' => $alerts,
        ]);
    }

    /**
     * Get student distribution chart data
     */
    public function studentDistribution(): JsonResponse
    {
        $data = $this->chartsService->getStudentDistributionData();
        return ApiResponse::success($data);
    }

    public function enrollmentGrowth(): JsonResponse
    {
        $data = $this->chartsService->getEnrollmentGrowthData();
        return ApiResponse::success($data);
    }

    public function academicStanding(): JsonResponse
    {
        $data = $this->chartsService->getAcademicStandingData();
        return ApiResponse::success($data);
    }

    public function graduationRate(): JsonResponse
    {
        $data = $this->chartsService->getGraduationRateData();
        return ApiResponse::success($data);
    }
}
