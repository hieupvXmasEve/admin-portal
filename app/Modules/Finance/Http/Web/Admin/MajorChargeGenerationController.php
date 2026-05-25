<?php

declare(strict_types=1);

namespace App\Modules\Finance\Http\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\Semester;
use App\Modules\Finance\Actions\Major\GenerateMajorChargesAction;
use App\Modules\Finance\Queries\Major\PreviewMajorChargeGenerationQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MajorChargeGenerationController extends Controller
{
    public function index(
        Request $request,
        PreviewMajorChargeGenerationQuery $previewQuery
    ): Response {
        $validated = $request->validate([
            'semester_id' => 'nullable|integer|exists:semesters,id',
            'search' => 'nullable|string|max:100',
            'ignore_student_ids' => 'nullable|string|max:10000',
            'per_page' => 'nullable|integer|in:20,50,100',
            'page' => 'nullable|integer|min:1',
        ]);

        $currentSemester = Semester::where('is_active', true)->first();
        $currentCampusId = session('current_campus_id') ? (int) session('current_campus_id') : null;
        $semesterId = isset($validated['semester_id'])
            ? (int) $validated['semester_id']
            : $currentSemester?->id;

        $preview = $semesterId
            ? $previewQuery->handle($semesterId, [
                'search' => $validated['search'] ?? '',
                'ignore_student_ids' => $this->parseIgnoredStudentIds($validated['ignore_student_ids'] ?? null),
                'per_page' => (int) ($validated['per_page'] ?? 20),
                'page' => (int) ($validated['page'] ?? 1),
            ], $currentCampusId)
            : [
                'eligible_students' => [],
                'ineligible_students' => [],
                'warning_students' => [],
                'summary' => ['eligible_count' => 0, 'ineligible_count' => 0, 'warning_count' => 0, 'total_count' => 0],
            ];

        $semesters = Semester::orderBy('start_date', 'desc')->get();

        return Inertia::render('Finance/Major/GenerateCharges', [
            'preview' => $preview,
            'semesters' => $semesters,
            'currentSemester' => $semesterId ? Semester::find($semesterId) : $currentSemester,
            'filters' => [
                'semester_id' => $semesterId ? (string) $semesterId : null,
                'search' => $validated['search'] ?? '',
                'ignore_student_ids' => $validated['ignore_student_ids'] ?? '',
                'per_page' => (int) ($validated['per_page'] ?? 20),
                'page' => (int) ($validated['page'] ?? 1),
            ],
        ]);
    }

    public function store(
        Request $request,
        PreviewMajorChargeGenerationQuery $previewQuery
    ): RedirectResponse {
        $validated = $request->validate([
            'semester_id' => 'required|integer|exists:semesters,id',
            'due_date' => 'required|date',
            'search' => 'nullable|string|max:100',
            'ignore_student_ids' => 'nullable|string|max:10000',
        ]);

        $currentCampusId = session('current_campus_id') ? (int) session('current_campus_id') : null;
        $eligibleStudents = $previewQuery->resolveEligibleStudents((int) $validated['semester_id'], [
            'search' => $validated['search'] ?? '',
            'ignore_student_ids' => $this->parseIgnoredStudentIds($validated['ignore_student_ids'] ?? null),
        ], $currentCampusId);

        $studentIds = $eligibleStudents->pluck('student_id')->map(fn ($id) => (int) $id)->all();

        if ($studentIds === []) {
            return redirect()
                ->route('finance.major.charges.index', array_filter([
                    'semester_id' => $validated['semester_id'],
                    'search' => $validated['search'] ?? null,
                    'ignore_student_ids' => $validated['ignore_student_ids'] ?? null,
                ]))
                ->with('error', 'Không có student hợp lệ theo bộ lọc hiện tại để tạo HP.');
        }

        $results = GenerateMajorChargesAction::run([
            'semester_id' => (int) $validated['semester_id'],
            'due_date' => $validated['due_date'],
            'student_ids' => $studentIds,
        ]);

        return redirect()
            ->route('finance.major.charges.index', array_filter([
                'semester_id' => $validated['semester_id'],
                'search' => $validated['search'] ?? null,
                'ignore_student_ids' => $validated['ignore_student_ids'] ?? null,
            ]))
            ->with('success', "Generated HP charges: {$results['created']} created, {$results['skipped']} skipped, {$results['failed']} failed.");
    }

    /**
     * @return array<int, string>
     */
    private function parseIgnoredStudentIds(?string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $value) ?: [])
            ->map(fn (string $studentId): string => trim($studentId))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
