<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\Web\DashboardStatsResource;
use App\Http\Responses\ApiResponse;
use App\Shared\Contracts\Platform\StaffDashboardChartReader;
use App\Shared\Contracts\Platform\StaffDashboardStatsReader;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DashboardController extends Controller
{
    public function __construct(
        private readonly StaffDashboardStatsReader $statsReader,
        private readonly StaffDashboardChartReader $chartReader
    ) {}

    public function index()
    {
        $campusId = $this->campusId();
        $stats = $this->statsReader->statsForCampus($campusId);
        $alerts = $this->statsReader->alertsForCampus($campusId);

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
        $data = $this->chartReader->studentDistributionForCampus($this->campusId());

        return ApiResponse::success($data);
    }

    public function enrollmentGrowth(): JsonResponse
    {
        $data = $this->chartReader->enrollmentGrowthForCampus($this->campusId());

        return ApiResponse::success($data);
    }

    public function academicStanding(): JsonResponse
    {
        $data = $this->chartReader->academicStandingForCampus($this->campusId());

        return ApiResponse::success($data);
    }

    public function graduationRate(): JsonResponse
    {
        $data = $this->chartReader->graduationRateForCampus($this->campusId());

        return ApiResponse::success($data);
    }

    private function campusId(): ?int
    {
        $campus = app()->bound('campus') ? app('campus') : null;
        $campusId = $campus?->id ?? session('current_campus_id');

        if ($campusId === null) {
            throw new AccessDeniedHttpException('A campus must be selected before reading dashboard data.');
        }

        return (int) $campusId;
    }
}
