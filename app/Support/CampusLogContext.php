<?php

namespace App\Support;

use App\Models\Campus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class CampusLogContext
{
    /**
     * Get comprehensive campus context for activity logging
     */
    public static function getCampusContext(?int $campusId = null): array
    {
        $campusId = $campusId ?? static::getCurrentCampusId();
        
        if (!$campusId) {
            return static::getSystemContext();
        }

        $campus = static::getCampusData($campusId);
        
        if (!$campus) {
            return static::getUnknownCampusContext($campusId);
        }

        return [
            'campus_id' => $campus->id,
            'campus_code' => $campus->code,
            'campus_name' => $campus->name,
            'campus_address' => $campus->address ?? null,
            'acting_user_campus' => static::getActingUserCampusId(),
            'session_id' => session()->getId(),
            'academic_year' => static::getCurrentAcademicYear(),
            'local_timestamp' => Carbon::now()->toISOString(),
        ];
    }

    /**
     * Get current campus ID from session or model context
     */
    public static function getCurrentCampusId(): ?int
    {
        // Try session first (most common case)
        if (session('current_campus_id')) {
            return (int) session('current_campus_id');
        }

        // Try authenticated user's default campus
        if (auth()->check() && method_exists(auth()->user(), 'getDefaultCampusId')) {
            return auth()->user()->getDefaultCampusId();
        }

        return null;
    }

    /**
     * Get campus context for the acting user (may differ from target campus)
     */
    public static function getActingUserCampusId(): ?int
    {
        if (auth()->check()) {
            // Check if user has a current campus context
            if (session('current_campus_id')) {
                return (int) session('current_campus_id');
            }

            // Try to get user's primary campus
            if (method_exists(auth()->user(), 'getPrimaryCampusId')) {
                return auth()->user()->getPrimaryCampusId();
            }
        }

        return null;
    }

    /**
     * Get campus-specific log name
     */
    public static function getLogName(string $modelName, ?int $campusId = null): string
    {
        $campusId = $campusId ?? static::getCurrentCampusId();
        $baseName = strtolower($modelName);

        if ($campusId) {
            return "{$baseName}_campus_{$campusId}";
        }

        return "{$baseName}_system";
    }

    /**
     * Get cached campus data to avoid repeated DB queries
     */
    protected static function getCampusData(int $campusId): ?Campus
    {
        return Cache::remember("campus_log_context_{$campusId}", 3600, function () use ($campusId) {
            return Campus::select('id', 'code', 'name', 'address')->find($campusId);
        });
    }

    /**
     * Get system context for non-campus operations
     */
    protected static function getSystemContext(): array
    {
        return [
            'campus_id' => null,
            'campus_code' => 'SYSTEM',
            'campus_name' => 'System Operation',
            'campus_address' => null,
            'acting_user_campus' => static::getActingUserCampusId(),
            'session_id' => session()->getId(),
            'academic_year' => static::getCurrentAcademicYear(),
            'local_timestamp' => Carbon::now()->toISOString(),
        ];
    }

    /**
     * Get context for unknown campus (fallback)
     */
    protected static function getUnknownCampusContext(int $campusId): array
    {
        return [
            'campus_id' => $campusId,
            'campus_code' => "UNKNOWN_{$campusId}",
            'campus_name' => "Unknown Campus ({$campusId})",
            'campus_address' => null,
            'acting_user_campus' => static::getActingUserCampusId(),
            'session_id' => session()->getId(),
            'academic_year' => static::getCurrentAcademicYear(),
            'local_timestamp' => Carbon::now()->toISOString(),
        ];
    }

    /**
     * Get current academic year
     */
    protected static function getCurrentAcademicYear(): ?string
    {
        // Try to get from active semester
        $activeSemester = Cache::remember('active_semester_log_context', 900, function () {
            if (class_exists(\App\Models\Semester::class)) {
                return \App\Models\Semester::where('is_active', true)
                    ->select('code', 'name')
                    ->first();
            }
            return null;
        });

        if ($activeSemester) {
            return $activeSemester->code ?? $activeSemester->name;
        }

        // Fallback to calendar year calculation
        $year = Carbon::now()->year;
        $month = Carbon::now()->month;
        
        // Academic year typically starts in August/September
        if ($month >= 8) {
            return "{$year}-" . ($year + 1);
        } else {
            return ($year - 1) . "-{$year}";
        }
    }

    /**
     * Check if current user is a system administrator
     */
    public static function isSystemAdmin(): bool
    {
        if (!auth()->check()) {
            return false;
        }

        // Check if user has system-wide permissions
        if (method_exists(auth()->user(), 'hasSystemRole')) {
            return auth()->user()->hasSystemRole('super_admin');
        }

        return false;
    }

    /**
     * Get all accessible campus IDs for current user
     */
    public static function getAccessibleCampusIds(): array
    {
        if (!auth()->check()) {
            return [];
        }

        // System admin can access all campuses
        if (static::isSystemAdmin()) {
            return Campus::pluck('id')->toArray();
        }

        // Get campus IDs from user's campus roles
        if (method_exists(auth()->user(), 'getAccessibleCampuses')) {
            return auth()->user()->getAccessibleCampuses()->pluck('id')->toArray();
        }

        // Fallback to current campus
        $currentCampus = static::getCurrentCampusId();
        return $currentCampus ? [$currentCampus] : [];
    }

    /**
     * Create enhanced log properties for activity logging
     */
    public static function enhanceLogProperties(array $existingProperties = [], ?int $targetCampusId = null): array
    {
        $campusContext = static::getCampusContext($targetCampusId);
        
        // Add user context
        $userContext = [];
        if (auth()->check()) {
            $userContext = [
                'user_id' => auth()->id(),
                'user_email' => auth()->user()->email ?? null,
                'user_name' => auth()->user()->name ?? null,
            ];
        }

        // Add additional context
        $additionalContext = [
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'url' => request()->fullUrl(),
        ];

        return array_merge($existingProperties, $campusContext, $userContext, $additionalContext);
    }

    /**
     * Clear campus context cache (useful after campus changes)
     */
    public static function clearCache(?int $campusId = null): void
    {
        if ($campusId) {
            Cache::forget("campus_log_context_{$campusId}");
        } else {
            // Clear all campus context cache
            $campusIds = Campus::pluck('id');
            foreach ($campusIds as $id) {
                Cache::forget("campus_log_context_{$id}");
            }
        }

        Cache::forget('active_semester_log_context');
    }
}