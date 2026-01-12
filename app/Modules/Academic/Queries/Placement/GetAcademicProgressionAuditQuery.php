<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries\Placement;

use App\Enums\AcademicProgressionEventType;
use App\Models\AcademicProgressionEvent;
use App\Models\IeltsCertificate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class GetAcademicProgressionAuditQuery
{
    /**
     * Get academic progression audit data for reports.
     *
     * @param array $filters {
     *     semester_id: ?int (required for reports),
     *     event_type: ?string,
     *     from_course_stage: ?string,
     *     to_course_stage: ?string,
     *     ielts_score_min: ?float,
     *     ielts_score_max: ?float,
     *     missing_documents: ?bool,
     *     trigger_source: ?string,
     *     campus_id: ?int,
     *     per_page: ?int,
     * }
     */
    public function handle(array $filters = []): LengthAwarePaginator
    {
        $query = AcademicProgressionEvent::query()
            ->with(['student.campus', 'semester', 'createdBy', 'ieltsCertificate'])
            ->latestFirst();

        // Filter by semester (required for reports)
        if (! empty($filters['semester_id'])) {
            $query->bySemester($filters['semester_id']);
        }

        // Filter by event type
        if (! empty($filters['event_type'])) {
            $query->byEventType($filters['event_type']);
        }

        // Filter by trigger source
        if (! empty($filters['trigger_source'])) {
            $query->byTriggerSource($filters['trigger_source']);
        }

        // Filter stage changes
        if (! empty($filters['from_course_stage'])) {
            $query->where('from_course_stage', $filters['from_course_stage']);
        }

        if (! empty($filters['to_course_stage'])) {
            $query->where('to_course_stage', $filters['to_course_stage']);
        }

        // Filter by campus through student relationship
        if (! empty($filters['campus_id'])) {
            $query->whereHas('student', function ($q) use ($filters) {
                $q->where('campus_id', $filters['campus_id']);
            });
        }

        // Filter by IELTS score range (through ielts_certificate)
        if (! empty($filters['ielts_score_min']) || ! empty($filters['ielts_score_max'])) {
            $query->whereHas('ieltsCertificate', function ($q) use ($filters) {
                if (! empty($filters['ielts_score_min'])) {
                    $q->where('overall_score', '>=', $filters['ielts_score_min']);
                }
                if (! empty($filters['ielts_score_max'])) {
                    $q->where('overall_score', '<=', $filters['ielts_score_max']);
                }
            });
        }

        // Filter by missing documents (through ielts_certificate)
        if (isset($filters['missing_documents'])) {
            $query->whereHas('ieltsCertificate', function ($q) use ($filters) {
                $q->where('missing_documents', $filters['missing_documents']);
            });
        }

        // Date range filter
        if (! empty($filters['from_date']) || ! empty($filters['to_date'])) {
            $query->byDateRange($filters['from_date'] ?? null, $filters['to_date'] ?? null);
        }

        $perPage = $filters['per_page'] ?? 25;

        return $query->paginate($perPage);
    }

    /**
     * Get statistics for academic progression by semester.
     */
    public function getStatistics(int $semesterId): array
    {
        // Placement distribution
        $placementStats = AcademicProgressionEvent::query()
            ->bySemester($semesterId)
            ->placementEvents()
            ->select('to_course_stage', DB::raw('COUNT(*) as count'))
            ->groupBy('to_course_stage')
            ->get()
            ->pluck('count', 'to_course_stage')
            ->toArray();

        // Stage changes
        $stageChangeCount = AcademicProgressionEvent::query()
            ->bySemester($semesterId)
            ->stageChanges()
            ->count();

        // Level changes
        $levelChangeStats = AcademicProgressionEvent::query()
            ->bySemester($semesterId)
            ->levelChanges()
            ->select('to_english_level', DB::raw('COUNT(*) as count'))
            ->groupBy('to_english_level')
            ->get()
            ->pluck('count', 'to_english_level')
            ->toArray();

        // IELTS recorded count
        $ieltsRecordedCount = AcademicProgressionEvent::query()
            ->bySemester($semesterId)
            ->ieltsEvents()
            ->count();

        // Missing IELTS documents (students in intake_course with missing docs)
        $missingIeltsDocsCount = IeltsCertificate::query()
            ->missingDocuments()
            ->whereHas('student', function ($q) {
                $q->where('status', 'intake_course');
            })
            ->count();

        return [
            'placement_distribution' => [
                'intake_pre_uni_gc' => $placementStats['intake_pre_uni_gc'] ?? 0,
                'intake_course' => $placementStats['intake_course'] ?? 0,
            ],
            'stage_changes' => $stageChangeCount,
            'level_changes' => $levelChangeStats,
            'ielts_recorded' => $ieltsRecordedCount,
            'missing_ielts_docs' => $missingIeltsDocsCount,
        ];
    }

    /**
     * Get students with missing IELTS documents.
     */
    public function getMissingIeltsDocuments(array $filters = []): LengthAwarePaginator
    {
        $query = IeltsCertificate::query()
            ->with(['student.campus', 'uploadRecord'])
            ->missingDocuments()
            ->whereHas('student', function ($q) {
                $q->where('status', 'intake_course');
            })
            ->latestFirst();

        if (! empty($filters['campus_id'])) {
            $query->whereHas('student', function ($q) use ($filters) {
                $q->where('campus_id', $filters['campus_id']);
            });
        }

        $perPage = $filters['per_page'] ?? 25;

        return $query->paginate($perPage);
    }
}
