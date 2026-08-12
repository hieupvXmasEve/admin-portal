<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Http\Web;

use App\Http\Controllers\Controller;
use App\Modules\Academic\Catalog\Queries\GetSemesterReferenceOptionsQuery;
use App\Modules\Academic\Progression\Exports\ScholarshipRestorationWatchlistExport;
use App\Modules\Academic\Progression\Queries\GetScholarshipRestorationWatchlistQuery;
use App\Services\ExcelExportService;
use App\Shared\Contracts\Academic\ScholarshipRestorationVerdictReader;
use App\Shared\Contracts\Finance\DTO\ScholarshipRestorationWatchlistRow;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Scholarship restoration watchlist report (Phase 4). Decision actions
 * (propose/approve/reject) themselves POST to the Finance module's own
 * routes — cross-module HTTP is fine, the module boundary applies to PHP
 * imports, not URLs.
 */
class ScholarshipRestorationWatchlistController extends Controller
{
    public function __construct(
        private readonly GetScholarshipRestorationWatchlistQuery $watchlistQuery,
        private readonly GetSemesterReferenceOptionsQuery $semesters,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $this->validateFilters($request);
        $campusId = (int) session('current_campus_id');

        return Inertia::render('Admin/Reports/ScholarshipRestorationWatchlist/Index', [
            'rows' => $this->watchlistQuery->handle($campusId, $validated),
            // Read access here (view_student_action) is broader than write
            // access — the actual gate for propose/approve/reject lives on
            // Finance's own routes. Mirrors ScholarshipAdjustmentDossierController's
            // 'can' payload so a read-only staffer never sees a button that
            // 403s on click.
            'can' => [
                'propose' => $request->user()->can('restore_scholarship'),
                'approve' => $request->user()->can('approve_scholarship_adjustment'),
            ],
            'filters' => [
                'search' => $validated['search'] ?? null,
                'semester_id' => $validated['semester_id'] ?? null,
                'verdict' => $validated['verdict'] ?? null,
                'proposal_state' => $validated['proposal_state'] ?? null,
                'per_page' => $validated['per_page'] ?? 25,
            ],
            'options' => [
                'semesters' => $this->semesters->auditOptions(),
                'verdicts' => [
                    ScholarshipRestorationVerdictReader::CLEAN,
                    ScholarshipRestorationVerdictReader::STILL_FAILING,
                    ScholarshipRestorationVerdictReader::NOT_FINALIZED,
                ],
                'proposal_states' => [
                    ScholarshipRestorationWatchlistRow::STATE_NONE,
                    ScholarshipRestorationWatchlistRow::STATE_PENDING,
                    ScholarshipRestorationWatchlistRow::STATE_REJECTED,
                    ScholarshipRestorationWatchlistRow::STATE_APPROVED_PARTIAL,
                ],
            ],
        ]);
    }

    public function export(Request $request, ExcelExportService $excelService): BinaryFileResponse
    {
        $validated = $this->validateFilters($request);
        $campusId = (int) session('current_campus_id');

        // handleAll() ignores pagination entirely — the export must honor the
        // active filters but never silently truncate at handle()'s page-size cap.
        $rows = $this->watchlistQuery->handleAll($campusId, $validated);

        return $excelService->download(
            new ScholarshipRestorationWatchlistExport($rows),
            'scholarship_restoration_watchlist_'.date('Y-m-d_H-i'),
        );
    }

    /**
     * @return array{search?: ?string, semester_id?: ?int, verdict?: ?string, proposal_state?: ?string, per_page?: ?int}
     */
    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'verdict' => ['nullable', 'string', 'in:clean,still_failing,not_finalized'],
            'proposal_state' => ['nullable', 'string', 'in:none,pending,rejected,approved_partial'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
    }
}
