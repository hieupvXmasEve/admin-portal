<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Web;

use App\Enums\AcademicProgressionEventType;
use App\Enums\ProgressionTriggerSource;
use App\Http\Controllers\Controller;
use App\Modules\Academic\Catalog\Queries\GetSemesterReferenceOptionsQuery;
use App\Modules\Academic\Progression\Queries\GetMissingDecisionReportQuery;
use App\Modules\Academic\Progression\Queries\Placement\GetAcademicProgressionAuditQuery;
use App\Shared\Contracts\Institution\CampusReferenceReader;
use App\Shared\Contracts\Institution\DTO\CampusReference;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcademicProgressionAuditController extends Controller
{
    public function __construct(
        private readonly GetAcademicProgressionAuditQuery $auditQuery,
        private readonly CampusReferenceReader $campuses,
        private readonly GetSemesterReferenceOptionsQuery $semesters,
    ) {}

    /**
     * Show the academic progression audit report.
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'event_type' => ['nullable', 'string'],
            'trigger_source' => ['nullable', 'string'],
            'from_course_stage' => ['nullable', 'string'],
            'to_course_stage' => ['nullable', 'string'],
            'ielts_score_min' => ['nullable', 'numeric', 'min:0', 'max:9'],
            'ielts_score_max' => ['nullable', 'numeric', 'min:0', 'max:9'],
            'missing_documents' => ['nullable', 'boolean'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $progressionEvents = $this->auditQuery->handle($validated);

        // Get statistics if semester is selected
        $statistics = null;
        if (! empty($validated['semester_id'])) {
            $statistics = $this->auditQuery->getStatistics((int) $validated['semester_id']);
        }

        return Inertia::render('Admin/Reports/AcademicProgressionAudit/Index', [
            'progressionEvents' => $progressionEvents,
            'statistics' => $statistics,
            'filters' => [
                'semester_id' => $validated['semester_id'] ?? null,
                'event_type' => $validated['event_type'] ?? null,
                'trigger_source' => $validated['trigger_source'] ?? null,
                'from_course_stage' => $validated['from_course_stage'] ?? null,
                'to_course_stage' => $validated['to_course_stage'] ?? null,
                'ielts_score_min' => $validated['ielts_score_min'] ?? null,
                'ielts_score_max' => $validated['ielts_score_max'] ?? null,
                'missing_documents' => $validated['missing_documents'] ?? null,
                'campus_id' => $validated['campus_id'] ?? null,
                'from_date' => $validated['from_date'] ?? null,
                'to_date' => $validated['to_date'] ?? null,
                'per_page' => $validated['per_page'] ?? 25,
            ],
            'options' => $this->getFilterOptions(),
        ]);
    }

    /**
     * Show missing IELTS documents report.
     */
    public function missingDocuments(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $missingDocs = $this->auditQuery->getMissingIeltsDocuments($validated);

        return Inertia::render('Admin/Reports/AcademicProgressionAudit/MissingDocuments', [
            'certificates' => $missingDocs,
            'filters' => [
                'search' => $validated['search'] ?? null,
                'per_page' => $validated['per_page'] ?? 25,
            ],
        ]);
    }

    /**
     * Show the missing-decision report (ADR-0048).
     *
     * Requires-decision transitions from both lifecycle streams that still lack
     * an authorizing Decision; attaching one removes the row.
     */
    public function missingDecisions(Request $request, GetMissingDecisionReportQuery $query): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $missingDecisions = $query->handle($validated, session('current_campus_id'));

        return Inertia::render('Admin/Reports/AcademicProgressionAudit/MissingDecisions', [
            'transitions' => $missingDecisions,
            'filters' => [
                'search' => $validated['search'] ?? null,
                'per_page' => $validated['per_page'] ?? 25,
            ],
        ]);
    }

    /**
     * Export audit data to CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'event_type' => ['nullable', 'string'],
            'trigger_source' => ['nullable', 'string'],
            'campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
        ]);

        $filters = array_merge($validated, ['per_page' => 10000]);
        $events = $this->auditQuery->handle($filters);

        $semesterCode = $this->semesters->codeOrFail((int) $validated['semester_id']);
        $filename = sprintf('academic_progression_audit_%s_%s.csv', $semesterCode, now()->format('Y-m-d'));

        return response()->streamDownload(function () use ($events) {
            $handle = fopen('php://output', 'w');

            // Header row
            fputcsv($handle, [
                'Student ID',
                'Student Name',
                'Campus',
                'Event Type',
                'Trigger Source',
                'From Stage',
                'To Stage',
                'From Level',
                'To Level',
                'IELTS Score',
                'Effective Date',
                'Changed By',
                'Notes',
            ]);

            // Data rows
            foreach ($events as $event) {
                fputcsv($handle, [
                    $event->student->student_id ?? '',
                    $event->student->full_name ?? '',
                    $event->student->campus->name ?? '',
                    $event->event_type->labelEn(),
                    $event->trigger_source->label(),
                    $event->from_course_stage ?? '',
                    $event->to_course_stage ?? '',
                    $event->from_english_level ?? '',
                    $event->to_english_level ?? '',
                    $event->ieltsCertificate?->overall_score ?? '',
                    $event->effective_at->format('Y-m-d H:i'),
                    $event->createdBy?->name ?? 'System',
                    $event->notes ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Get filter options.
     */
    protected function getFilterOptions(): array
    {
        return [
            'eventTypes' => AcademicProgressionEventType::options(),
            'triggerSources' => ProgressionTriggerSource::options(),
            'semesters' => $this->semesters->auditOptions(),
            'campuses' => array_map(
                static fn (CampusReference $campus): array => $campus->toArray(),
                $this->campuses->all(),
            ),
            'courseStages' => [
                ['value' => 'intake_pre_uni_gc', 'label' => 'Intake Pre-Uni GC'],
                ['value' => 'intake_course', 'label' => 'Intake Course'],
            ],
        ];
    }
}
