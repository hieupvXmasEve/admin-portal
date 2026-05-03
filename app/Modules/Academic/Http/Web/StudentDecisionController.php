<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\StudentActionLog;
use App\Models\StudentDecision;
use App\Modules\Academic\Http\Requests\StoreStudentDecisionRequest;
use App\Modules\Academic\Http\Requests\UpdateStudentDecisionRequest;
use App\Modules\Academic\Queries\GetStudentDecisionDetailQuery;
use App\Modules\Academic\Queries\ListStudentDecisionsQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentDecisionController extends Controller
{
    public function __construct(
        private readonly ListStudentDecisionsQuery $listQuery,
        private readonly GetStudentDecisionDetailQuery $detailQuery
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'issued_from' => ['nullable', 'date'],
            'issued_to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', 'max:50'],
            'direction' => ['nullable', 'string', 'max:10'],
        ]);

        $sortInput = (string) ($validated['sort'] ?? 'issued_at');
        $sort = in_array($sortInput, ['decision_number', 'decision_signer', 'issued_at', 'expires_at'], true)
            ? $sortInput
            : 'issued_at';

        $direction = strtolower((string) ($validated['direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $campusId = session('current_campus_id');
        $decisions = $this->listQuery->handle($validated, $campusId);

        return Inertia::render('Admin/Reports/StudentDecisions/Index', [
            'decisions' => $decisions,
            'filters' => [
                'search' => $validated['search'] ?? null,
                'issued_from' => $validated['issued_from'] ?? null,
                'issued_to' => $validated['issued_to'] ?? null,
                'per_page' => (int) ($validated['per_page'] ?? 15),
                'page' => (int) ($validated['page'] ?? 1),
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function store(StoreStudentDecisionRequest $request): RedirectResponse
    {
        StudentDecision::query()->create([
            ...$request->validated(),
            'changed_by_user_id' => (int) $request->user()->id,
        ]);

        return back()->with('success', 'Decision created successfully.');
    }

    public function update(UpdateStudentDecisionRequest $request, StudentDecision $studentDecision): RedirectResponse
    {
        $validated = $request->validated();

        if (($validated['upload_record_id'] ?? null) === null && $studentDecision->upload_record_id !== null) {
            unset($validated['upload_record_id']);
        }

        $studentDecision->update([
            ...$validated,
            'changed_by_user_id' => (int) $request->user()->id,
        ]);

        return back()->with('success', 'Decision updated successfully.');
    }

    public function show(Request $request, StudentDecision $studentDecision): Response
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'linked_per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'linked_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $campusId = session('current_campus_id');

        $totalLinkedActionsSub = StudentActionLog::query()
            ->selectRaw('COUNT(*)')
            ->whereColumn('decision_id', 'student_decisions.id')
            ->when($campusId, fn ($query) => $query->whereHas('student', fn ($studentQuery) => $studentQuery->where('campus_id', $campusId)));

        $totalLinkedStudentsSub = StudentActionLog::query()
            ->selectRaw('COUNT(DISTINCT student_id)')
            ->whereColumn('decision_id', 'student_decisions.id')
            ->when($campusId, fn ($query) => $query->whereHas('student', fn ($studentQuery) => $studentQuery->where('campus_id', $campusId)));

        $decision = StudentDecision::query()
            ->with(['uploadRecord:id,original_name,url,disk,path,context', 'changedBy:id,name'])
            ->select('student_decisions.*')
            ->selectSub($totalLinkedActionsSub, 'total_linked_actions')
            ->selectSub($totalLinkedStudentsSub, 'total_linked_students')
            ->findOrFail($studentDecision->id);

        $linkedPerPage = (int) ($validated['per_page'] ?? $validated['linked_per_page'] ?? 10);
        $linkedPage = (int) ($validated['page'] ?? $validated['linked_page'] ?? 1);
        $linkedActions = $this->detailQuery->linkedActions($decision, $linkedPerPage, $campusId, $linkedPage);

        return Inertia::render('Admin/Reports/StudentDecisions/Show', [
            'decision' => $decision,
            'linkedActions' => $linkedActions,
            'filters' => [
                'per_page' => $linkedPerPage,
                'page' => $linkedPage,
                'linked_per_page' => $linkedPerPage,
                'linked_page' => $linkedPage,
            ],
        ]);
    }
}
