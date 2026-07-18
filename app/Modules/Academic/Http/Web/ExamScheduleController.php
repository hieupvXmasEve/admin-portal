<?php

declare(strict_types=1);

namespace App\Modules\Academic\Http\Web;

use App\Http\Controllers\Controller;
use App\Models\Lecture;
use App\Models\Semester;
use App\Models\Unit;
use App\Modules\Academic\Actions\AssignExamResitInvigilatorAction;
use App\Modules\Academic\Actions\CreateExamResitSessionAction;
use App\Modules\Academic\Actions\CreateExamRoomSlotAction;
use App\Modules\Academic\Http\Requests\ExamResit\AssignInvigilatorRequest;
use App\Modules\Academic\Http\Requests\ExamResit\StoreExamResitSessionRequest;
use App\Modules\Academic\Http\Requests\ExamResit\StoreExamRoomSlotRequest;
use App\Modules\Academic\Queries\ListExamRoomSlotsQuery;
use App\Shared\Contracts\Facilities\SpaceReferenceReader;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Exam-resit scheduling authoring area (ACAD-RET-001 slice 7): room slots,
 * unit-scoped sessions, and shared invigilator assignment. All conflict/capacity
 * guards live in the Academic actions; this controller is transport only.
 */
class ExamScheduleController extends Controller
{
    public function __construct(
        private readonly ListExamRoomSlotsQuery $slotsQuery,
        private readonly SpaceReferenceReader $spaceReferences,
    ) {}

    public function index(): Response
    {
        $campusId = session('current_campus_id');

        $result = $this->slotsQuery->handle(['per_page' => 20], $campusId);

        return Inertia::render('Academic/ExamResit/Schedule/Index', [
            'campus_id' => $campusId,
            'slots' => $result['slots'],
            'rooms' => collect($this->spaceReferences->forCampus($campusId ? (int) $campusId : null))
                ->map(fn ($room) => $room->toArray())
                ->all(),
            'units' => Unit::orderBy('code')->get(['id', 'code', 'name']),
            'lecturers' => Lecture::query()
                ->when($campusId, fn ($q) => $q->where('campus_id', $campusId))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'first_name', 'last_name']),
            'semesters' => Semester::orderByDesc('start_date')->get(['id', 'name', 'code']),
        ]);
    }

    public function storeRoomSlot(StoreExamRoomSlotRequest $request, CreateExamRoomSlotAction $action): RedirectResponse
    {
        $action->run($request->validated());

        Inertia::flash('success', 'Đã tạo ca phòng thi.');

        return back();
    }

    public function storeSession(StoreExamResitSessionRequest $request, CreateExamResitSessionAction $action): RedirectResponse
    {
        $action->run($request->validated());

        Inertia::flash('success', 'Đã tạo ca thi cho môn học.');

        return back();
    }

    public function assignInvigilator(AssignInvigilatorRequest $request, AssignExamResitInvigilatorAction $action): RedirectResponse
    {
        $action->run($request->validated());

        Inertia::flash('success', 'Đã phân công cán bộ coi thi.');

        return back();
    }
}
