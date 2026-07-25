<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicStanding;
use App\Models\Enrollment;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Shared\Contracts\Platform\StaffDashboardChartReader;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** Compatibility implementation of the Platform staff dashboard chart reader. */
class DashboardChartsService implements StaffDashboardChartReader
{
    private const CACHE_TTL = 600; // 10 minutes

    public function getCampusId(): ?int
    {
        // Prefer bound campus instance, fallback to session
        $campus = app()->bound('campus') ? app('campus') : null;

        return $campus->id ?? session('current_campus_id');
    }

    /**
     * Get student distribution by campus and program
     */
    public function getStudentDistributionData(): array
    {
        return $this->studentDistributionForCampus($this->getCampusId());
    }

    public function studentDistributionForCampus(?int $campusId): array
    {
        $campusId = $this->requireCampusId($campusId);

        $cacheKey = "dashboard_student_distribution_campus_{$campusId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($campusId) {
            // Get student distribution by program
            $programData = Student::query()
                ->when($campusId, fn ($q) => $q->where('campus_id', $campusId))
                ->join('programs', 'students.program_id', '=', 'programs.id')
                ->leftJoin('specializations', 'students.specialization_id', '=', 'specializations.id')
                ->select([
                    'programs.id as program_id',
                    'programs.name as program_name',
                    'programs.code as program_code',
                    'specializations.id as specialization_id',
                    'specializations.name as specialization_name',
                    DB::raw('COUNT(students.id) as student_count'),
                    DB::raw('COUNT(CASE WHEN students.status = "active" THEN 1 END) as active_count'),
                    DB::raw('COUNT(CASE WHEN students.status = "graduated" THEN 1 END) as graduated_count'),
                ])
                ->groupBy('programs.id', 'programs.name', 'programs.code', 'specializations.id', 'specializations.name')
                ->orderBy('student_count', 'desc')
                ->get();

            // Organize data by program with specializations
            $programs = [];
            $totalStudents = 0;

            foreach ($programData as $row) {
                if (! isset($programs[$row->program_id])) {
                    $programs[$row->program_id] = [
                        'id' => $row->program_id,
                        'name' => $row->program_name,
                        'code' => $row->program_code,
                        'student_count' => 0,
                        'active_count' => 0,
                        'graduated_count' => 0,
                        'specializations' => [],
                    ];
                }

                $programs[$row->program_id]['student_count'] += $row->student_count;
                $programs[$row->program_id]['active_count'] += $row->active_count;
                $programs[$row->program_id]['graduated_count'] += $row->graduated_count;
                $totalStudents += $row->student_count;

                if ($row->specialization_id) {
                    $programs[$row->program_id]['specializations'][] = [
                        'id' => $row->specialization_id,
                        'name' => $row->specialization_name,
                        'student_count' => $row->student_count,
                        'active_count' => $row->active_count,
                        'graduated_count' => $row->graduated_count,
                    ];
                }
            }

            return [
                'programs' => array_values($programs),
                'total_students' => $totalStudents,
                'chart_data' => array_map(fn ($program) => [
                    'name' => $program['name'],
                    'value' => $program['student_count'],
                    // 'fill' => $this->getChartColor($program['id'])
                    'predicted' => 12,
                ], array_values($programs)),
            ];
        });
    }

    /**
     * Get enrollment growth by term/semester
     */
    public function getEnrollmentGrowthData(): array
    {
        return $this->enrollmentGrowthForCampus($this->getCampusId());
    }

    public function enrollmentGrowthForCampus(?int $campusId): array
    {
        $campusId = $this->requireCampusId($campusId);

        $cacheKey = "dashboard_enrollment_growth_campus_{$campusId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($campusId) {
            // Get enrollment data by semester and admission date
            $enrollmentData = Student::query()
                ->when($campusId, fn ($q) => $q->where('campus_id', $campusId))
                ->select([
                    DB::raw('YEAR(admission_date) as year'),
                    DB::raw('MONTH(admission_date) as month'),
                    DB::raw('COUNT(*) as new_students'),
                ])
                ->where('admission_date', '>=', now()->subYears(3))
                ->groupBy('year', 'month')
                ->orderBy('year', 'asc')
                ->orderBy('month', 'asc')
                ->get();

            // Get current enrollments by semester
            $currentEnrollments = Enrollment::query()
                ->whereHas('student', function ($q) use ($campusId) {
                    if ($campusId) {
                        $q->where('campus_id', $campusId);
                    }
                })
                ->join('semesters', 'enrollments.semester_id', '=', 'semesters.id')
                ->select([
                    'semesters.id as semester_id',
                    'semesters.name as semester_name',
                    'semesters.code as semester_code',
                    'semesters.start_date',
                    DB::raw('COUNT(enrollments.id) as enrollment_count'),
                ])
                ->where('semesters.start_date', '>=', now()->subYears(2))
                ->groupBy('semesters.id', 'semesters.name', 'semesters.code', 'semesters.start_date')
                ->orderBy('semesters.start_date', 'asc')
                ->get();

            // Format data for charts
            $admissionTrend = $enrollmentData->map(fn ($item) => [
                'date' => "{$item->year}-".str_pad((string) $item->month, 2, '0', STR_PAD_LEFT),
                'new_students' => $item->new_students,
            ])->toArray();

            $enrollmentTrend = $currentEnrollments->map(fn ($item) => [
                'semester' => $item->semester_name,
                'date' => Carbon::parse($item->start_date)->format('Y-m-d'),
                'enrollments' => $item->enrollment_count,
            ])->toArray();

            return [
                'admission_trend' => $admissionTrend,
                'enrollment_trend' => $enrollmentTrend,
            ];
        });
    }

    /**
     * Get academic standing distribution
     */
    public function getAcademicStandingData(): array
    {
        return $this->academicStandingForCampus($this->getCampusId());
    }

    public function academicStandingForCampus(?int $campusId): array
    {
        $campusId = $this->requireCampusId($campusId);

        $cacheKey = "dashboard_academic_standing_campus_{$campusId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($campusId) {
            $standingData = AcademicStanding::query()
                ->whereHas('student', function ($q) use ($campusId) {
                    if ($campusId) {
                        $q->where('campus_id', $campusId);
                    }
                })
                ->where('is_active', true)
                ->select([
                    'standing',
                    DB::raw('COUNT(*) as count'),
                    DB::raw('AVG(gpa) as avg_gpa'),
                    DB::raw('AVG(cumulative_gpa) as avg_cumulative_gpa'),
                ])
                ->groupBy('standing')
                ->get();

            $totalStudents = $standingData->sum('count');

            $chartData = $standingData->map(fn ($item) => [
                'standing' => $item->standing,
                'label' => $this->getStandingLabel($item->standing),
                'count' => $item->count,
                'percentage' => $totalStudents > 0 ? round(($item->count / $totalStudents) * 100, 1) : 0,
                'avg_gpa' => round($item->avg_gpa, 2),
                'avg_cumulative_gpa' => round($item->avg_cumulative_gpa, 2),
                'fill' => $this->getStandingColor($item->standing),
            ])->toArray();

            return [
                'total_students' => $totalStudents,
                'standings' => $chartData,
            ];
        });
    }

    /**
     * Get graduation rate data
     */
    public function getGraduationRateData(): array
    {
        return $this->graduationRateForCampus($this->getCampusId());
    }

    public function graduationRateForCampus(?int $campusId): array
    {
        $campusId = $this->requireCampusId($campusId);

        $cacheKey = "dashboard_graduation_rate_campus_{$campusId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($campusId) {
            // Get graduation data by year
            $graduationData = Student::query()
                ->when($campusId, fn ($q) => $q->where('campus_id', $campusId))
                ->select([
                    DB::raw('YEAR(admission_date) as admission_year'),
                    DB::raw('COUNT(*) as total_admitted'),
                    DB::raw('COUNT(CASE WHEN status = "graduated" THEN 1 END) as total_graduated'),
                    DB::raw('ROUND((COUNT(CASE WHEN status = "graduated" THEN 1 END) / COUNT(*)) * 100, 2) as graduation_rate'),
                ])
                ->where('admission_date', '>=', now()->subYears(8)) // Look at last 8 years
                ->groupBy('admission_year')
                ->orderBy('admission_year', 'asc')
                ->get();

            // Get program-specific graduation rates
            $programGraduationData = Student::query()
                ->when($campusId, fn ($q) => $q->where('campus_id', $campusId))
                ->join('programs', 'students.program_id', '=', 'programs.id')
                ->select([
                    'programs.id as program_id',
                    'programs.name as program_name',
                    'programs.code as program_code',
                    DB::raw('COUNT(*) as total_students'),
                    DB::raw('COUNT(CASE WHEN students.status = "graduated" THEN 1 END) as graduated_students'),
                    DB::raw('ROUND((COUNT(CASE WHEN students.status = "graduated" THEN 1 END) / COUNT(*)) * 100, 2) as graduation_rate'),
                ])
                ->where('admission_date', '<=', now()->subYears(4)) // Only consider students who had time to graduate
                ->groupBy('programs.id', 'programs.name', 'programs.code')
                ->orderBy('graduation_rate', 'desc')
                ->get();

            return [
                'yearly_rates' => $graduationData->map(fn ($item) => [
                    'year' => $item->admission_year,
                    'total_admitted' => $item->total_admitted,
                    'total_graduated' => $item->total_graduated,
                    'graduation_rate' => $item->graduation_rate,
                ])->toArray(),
                'program_rates' => $programGraduationData->map(fn ($item) => [
                    'program_id' => $item->program_id,
                    'program_name' => $item->program_name,
                    'program_code' => $item->program_code,
                    'total_students' => $item->total_students,
                    'graduated_students' => $item->graduated_students,
                    'graduation_rate' => $item->graduation_rate,
                    'fill' => $this->getChartColor($item->program_id),
                ])->toArray(),
            ];
        });
    }

    /**
     * Clear all chart caches
     */
    public function clearAllCaches(): void
    {
        $campusId = $this->getCampusId();
        $cacheKeys = [
            "dashboard_student_distribution_campus_{$campusId}",
            "dashboard_enrollment_growth_campus_{$campusId}",
            "dashboard_academic_standing_campus_{$campusId}",
            "dashboard_graduation_rate_campus_{$campusId}",
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Get chart color based on ID
     */
    private function getChartColor(int $id): string
    {
        $colors = [
            'hsl(var(--chart-1))',
            'hsl(var(--chart-2))',
            'hsl(var(--chart-3))',
            'hsl(var(--chart-4))',
            'hsl(var(--chart-5))',
        ];

        return $colors[$id % count($colors)];
    }

    /**
     * Get academic standing label
     */
    private function getStandingLabel(string $standing): string
    {
        return match ($standing) {
            'good' => 'Good Standing',
            'probation' => 'Academic Probation',
            'suspension' => 'Academic Suspension',
            'honors' => 'Honors',
            default => ucfirst($standing)
        };
    }

    /**
     * Get academic standing color
     */
    private function getStandingColor(string $standing): string
    {
        return match ($standing) {
            'good' => 'hsl(var(--chart-2))', // Green
            'probation' => 'hsl(var(--chart-3))', // Yellow/Orange
            'suspension' => 'hsl(var(--destructive))', // Red
            'honors' => 'hsl(var(--chart-1))', // Blue
            default => 'hsl(var(--chart-5))'
        };
    }

    public function freshness(): string
    {
        return 'cache_ttl_600_seconds';
    }

    public function permissionScope(): string
    {
        return 'authenticated_staff_current_campus';
    }

    public function fieldOwnership(): array
    {
        return [
            'student_distribution' => StaffDashboardChartReader::class,
            'enrollment_growth' => StaffDashboardChartReader::class,
            'academic_standing' => StaffDashboardChartReader::class,
            'graduation_rate' => StaffDashboardChartReader::class,
        ];
    }

    private function requireCampusId(?int $campusId): int
    {
        if ($campusId === null) {
            throw new AccessDeniedHttpException('A campus must be selected before reading dashboard data.');
        }

        return $campusId;
    }
}
