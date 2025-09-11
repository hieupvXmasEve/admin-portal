<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AcademicHold;
use App\Models\CurriculumVersion;
use App\Models\Lecture;
use App\Models\Program;
use App\Models\ProgramChangeRequest;
use App\Models\Room;
use App\Models\Semester;
use App\Models\Specialization;
use App\Models\Student;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardStatsService
{
    private const CACHE_TTL = 300; // 5 minutes
    
    public function getCampusId(): ?int
    {
        // Prefer bound campus instance, fallback to session
        $campus = app()->bound('campus') ? app('campus') : null;
        return $campus->id ?? session('current_campus_id');
    }

    public function getStats(): array
    {
        $campusId = $this->getCampusId();
        $cacheKey = "dashboard_stats_campus_{$campusId}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($campusId) {
            return [
                'students' => $this->getStudentStats($campusId),
                'lecturers' => $this->getLecturerStats($campusId),
                'academics' => $this->getAcademicStats(),
                'semester' => $this->getCurrentSemesterInfo(),
                'rooms' => $this->getRoomStats($campusId),
            ];
        });
    }
    
    private function getStudentStats(?int $campusId): array
    {
        $baseQuery = Student::query();
        if ($campusId) {
            $baseQuery->where('campus_id', $campusId);
        }
        
        // Use single query with aggregation for better performance
        $statusCounts = (clone $baseQuery)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
            
        $total = array_sum($statusCounts);
        
        // Ensure all expected statuses are present
        $expectedStatuses = ['active', 'inactive', 'suspended', 'graduated'];
        $byStatus = [];
        foreach ($expectedStatuses as $status) {
            $byStatus[$status] = $statusCounts[$status] ?? 0;
        }
        
        return [
            'total' => $total,
            'by_status' => $byStatus,
        ];
    }
    
    private function getLecturerStats(?int $campusId): array
    {
        $baseQuery = Lecture::query();
        if ($campusId) {
            $baseQuery->where('campus_id', $campusId);
        }
        
        // Use single query with aggregation
        $employmentCounts = (clone $baseQuery)
            ->select('employment_type', DB::raw('COUNT(*) as count'))
            ->groupBy('employment_type')
            ->pluck('count', 'employment_type')
            ->toArray();
            
        $total = array_sum($employmentCounts);
        
        // Ensure all expected employment types are present
        $expectedTypes = ['full_time', 'part_time', 'visiting', 'contract'];
        $byEmploymentType = [];
        foreach ($expectedTypes as $type) {
            $byEmploymentType[$type] = $employmentCounts[$type] ?? 0;
        }
        
        return [
            'total' => $total,
            'by_employment_type' => $byEmploymentType,
        ];
    }
    
    private function getAcademicStats(): array
    {
        return [
            'programs' => Program::count(),
            'specializations' => Specialization::count(),
            'active_curriculum_versions' => CurriculumVersion::query()
                ->whereHas('effectiveFromSemester', function ($q) {
                    $q->where('is_active', true);
                })
                ->count(),
        ];
    }
    
    private function getCurrentSemesterInfo(): ?array
    {
        $currentSemester = Semester::getActiveSemester();
        
        if (!$currentSemester) {
            return null;
        }
        
        return [
            'id' => $currentSemester->id,
            'code' => $currentSemester->code,
            'name' => $currentSemester->name,
            'start_date' => $currentSemester->start_date?->toDateString(),
            'end_date' => $currentSemester->end_date?->toDateString(),
            'enrollment_start_date' => $currentSemester->enrollment_start_date?->toDateString(),
            'enrollment_end_date' => $currentSemester->enrollment_end_date?->toDateString(),
            'is_registration_open' => $currentSemester->isRegistrationOpen(),
        ];
    }
    
    private function getRoomStats(?int $campusId): array
    {
        $baseQuery = Room::query();
        if ($campusId) {
            $baseQuery->where('campus_id', $campusId);
        }
        
        // Use single query with aggregation
        $statusCounts = (clone $baseQuery)
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();
            
        $total = array_sum($statusCounts);
        
        return [
            'total' => $total,
            'available' => $statusCounts[Room::STATUS_AVAILABLE] ?? 0,
            'occupied' => $statusCounts[Room::STATUS_OCCUPIED] ?? 0,
            'maintenance' => $statusCounts[Room::STATUS_MAINTENANCE] ?? 0,
        ];
    }
    
    /**
     * Clear the cache for dashboard stats
     */
    public function clearCache(?int $campusId = null): void
    {
        $campusId = $campusId ?? $this->getCampusId();
        $cacheKey = "dashboard_stats_campus_{$campusId}";
        Cache::forget($cacheKey);
    }
    
    /**
     * Get quick stats only (for API endpoints that don't need full data)
     */
    public function getQuickStats(): array
    {
        $campusId = $this->getCampusId();
        $cacheKey = "dashboard_quick_stats_campus_{$campusId}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($campusId) {
            return [
                'students' => $this->getStudentStats($campusId),
                'lecturers' => $this->getLecturerStats($campusId),
                'academics' => $this->getAcademicStats(),
                'rooms' => $this->getRoomStats($campusId),
            ];
        });
    }
    
    /**
     * Get alerts and notifications
     */
    public function getAlerts(): array
    {
        $campusId = $this->getCampusId();
        
        $alerts = [];
        
        // Academic Holds - high priority issues
        $activeHolds = AcademicHold::active()
            ->whereHas('student', function ($q) use ($campusId) {
                if ($campusId) {
                    $q->where('campus_id', $campusId);
                }
            })
            ->where('priority', 'high')
            ->count();
            
        if ($activeHolds > 0) {
            $alerts[] = [
                'id' => 1,
                'type' => 'academic_hold',
                'severity' => 'high',
                'title' => 'Academic Holds',
                'message' => 'Students with high-priority academic holds requiring immediate attention',
                'count' => $activeHolds,
                'created_at' => now()->format('M j, Y'),
            ];
        }
        
        // Program Change Requests
        $pendingProgramChanges = ProgramChangeRequest::where('status', 'pending')->count();
        if ($pendingProgramChanges > 0) {
            $alerts[] = [
                'id' => 2,
                'type' => 'program_change',
                'severity' => 'medium',
                'title' => 'Program Change Requests',
                'message' => 'Pending program change requests awaiting approval',
                'count' => $pendingProgramChanges,
                'created_at' => now()->format('M j, Y'),
            ];
        }
        
        return $alerts;
    }
}
