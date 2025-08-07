<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Support\CampusLogContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Spatie\Activitylog\Models\Activity;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->only(['search', 'subject_type', 'event', 'campus_id', 'per_page']);

        // Determine campus filter context
        $isSystemAdmin = CampusLogContext::isSystemAdmin();
        Log::info('ActivityLogController: isSystemAdmin', ['isSystemAdmin' => $isSystemAdmin]);
        $accessibleCampusIds = CampusLogContext::getAccessibleCampusIds();
        $currentCampusId = CampusLogContext::getCurrentCampusId();

        // Build base query with campus filtering
        $query = Activity::with(['subject', 'causer'])
            ->latest();

        // Apply campus filtering based on user permissions
        if (!$isSystemAdmin) {
            // Non-system admins see only their accessible campuses
            if (!empty($accessibleCampusIds)) {
                $campusLogNames = [];
                foreach ($accessibleCampusIds as $campusId) {
                    $campusLogNames[] = '%_campus_' . $campusId;
                }

                $query->where(function ($q) use ($campusLogNames) {
                    foreach ($campusLogNames as $pattern) {
                        $q->orWhere('log_name', 'like', $pattern);
                    }
                });
            }
        } else {
            // System admins can optionally filter by specific campus
            if (!empty($filters['campus_id']) && $filters['campus_id'] !== 'all') {
                $query->where('log_name', 'like', '%_campus_' . $filters['campus_id']);
            }
        }

        // Apply search filter
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('description', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('log_name', 'like', '%' . $filters['search'] . '%')
                  ->orWhereJsonContains('properties->campus_name', $filters['search'])
                  ->orWhereJsonContains('properties->user_name', $filters['search'])
                  ->orWhereHas('causer', function ($q) use ($filters) {
                      $q->where('name', 'like', '%' . $filters['search'] . '%');
                  });
            });
        }

        // Apply subject type filter
        if (!empty($filters['subject_type'])) {
            $query->where('subject_type', $filters['subject_type']);
        }

        // Apply event filter
        if (!empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        $activities = $query->paginate($filters['per_page'] ?? 15);

        // Get available filter options based on current campus context
        $queryForOptions = Activity::query();
        if (!$isSystemAdmin && !empty($accessibleCampusIds)) {
            $campusLogNames = [];
            foreach ($accessibleCampusIds as $campusId) {
                $campusLogNames[] = '%_campus_' . $campusId;
            }

            $queryForOptions->where(function ($q) use ($campusLogNames) {
                foreach ($campusLogNames as $pattern) {
                    $q->orWhere('log_name', 'like', $pattern);
                }
            });
        }

        $subjectTypes = (clone $queryForOptions)
            ->select('subject_type')
            ->whereNotNull('subject_type')
            ->distinct()
            ->pluck('subject_type')
            ->sort()
            ->values();

        $events = (clone $queryForOptions)
            ->select('event')
            ->whereNotNull('event')
            ->distinct()
            ->pluck('event')
            ->sort()
            ->values();

        // Get campus options for system admins
        $campusOptions = [];
        if ($isSystemAdmin) {
            $campuses = Campus::select('id', 'name', 'code')->orderBy('name')->get();
            $campusOptions = $campuses->map(function ($campus) {
                return [
                    'id' => $campus->id,
                    'name' => $campus->name,
                    'code' => $campus->code,
                ];
            });
        }

        return Inertia::render('systems/ActivityLogs', [
            'activities' => $activities,
            'filters' => $filters,
            'subject_types' => $subjectTypes,
            'events' => $events,
            'campus_options' => $campusOptions,
            'is_system_admin' => $isSystemAdmin,
            'current_campus' => $currentCampusId ? [
                'id' => $currentCampusId,
                'name' => Cache::remember("campus_name_{$currentCampusId}", 3600, function () use ($currentCampusId) {
                    return Campus::find($currentCampusId)?->name ?? 'Unknown Campus';
                }),
            ] : null,
        ]);
    }
}
