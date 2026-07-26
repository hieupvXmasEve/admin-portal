<?php

declare(strict_types=1);

namespace App\Modules\Academic\Delivery\Http\Web;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ClassSession;
use App\Models\CourseOffering;
use App\Modules\Academic\Delivery\Actions\BulkDeleteWebClassSessionsAction;
use App\Modules\Academic\Delivery\Actions\CreateClassSessionAction;
use App\Modules\Academic\Delivery\Actions\DeleteClassSessionAction;
use App\Modules\Academic\Delivery\Actions\GenerateClassSessionAttendanceAction;
use App\Modules\Academic\Delivery\Actions\GenerateCourseOfferingClassSessionsAction;
use App\Modules\Academic\Delivery\Actions\UpdateClassSessionAction;
use App\Modules\Academic\Delivery\Queries\ExportClassSessionAttendanceQuery;
use App\Modules\Academic\Delivery\Queries\GetClassSessionAttendanceDetailsQuery;
use App\Modules\Academic\Delivery\Queries\GetClassSessionFormOptionsQuery;
use App\Modules\Academic\Delivery\Queries\ListClassSessionsQuery;
use App\Modules\Academic\Http\Requests\Delivery\BulkDeleteWebClassSessionsRequest;
use App\Modules\Academic\Http\Requests\Delivery\BulkEditClassSessionsRequest;
use App\Modules\Academic\Http\Requests\Delivery\ClassSessionAttendanceFilterRequest;
use App\Modules\Academic\Http\Requests\Delivery\ClassSessionCampusRequest;
use App\Modules\Academic\Http\Requests\Delivery\CourseOfferingCampusRequest;
use App\Modules\Academic\Http\Requests\Delivery\GenerateClassSessionsRequest;
use App\Modules\Academic\Http\Requests\Delivery\ListClassSessionsRequest;
use App\Modules\Academic\Http\Requests\Delivery\SaveClassSessionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

final class ClassSessionController extends Controller
{
    public function index(ListClassSessionsRequest $request, ListClassSessionsQuery $query): Response
    {
        return Inertia::render('ClassSessions/Index', $query->handle($request->validated()));
    }

    public function create(GetClassSessionFormOptionsQuery $query): Response
    {
        return Inertia::render('ClassSessions/Create', $query->handle('create'));
    }

    public function store(SaveClassSessionRequest $request): JsonResponse|RedirectResponse
    {
        $classSession = CreateClassSessionAction::run($request->validated());

        if ($request->expectsJson()) {
            return ApiResponse::success($classSession, [], 'Class session created successfully');
        }

        Inertia::flash('message', 'Class session created successfully.');

        return redirect()->back();
    }

    public function show(ClassSessionAttendanceFilterRequest $request, ClassSession $classSession, GetClassSessionAttendanceDetailsQuery $query): JsonResponse|Response
    {
        $data = $query->handle($classSession, $request->validated());

        if ($request->expectsJson()) {
            return ApiResponse::compatible($data);
        }

        return Inertia::render('ClassSessions/Show', $data);
    }

    public function edit(ClassSession $classSession, GetClassSessionFormOptionsQuery $query): Response
    {
        return Inertia::render('ClassSessions/Edit', $query->handle('edit', $classSession));
    }

    public function update(SaveClassSessionRequest $request, ClassSession $classSession): JsonResponse|RedirectResponse
    {
        $updated = UpdateClassSessionAction::run([
            ...$request->validated(),
            'class_session_id' => $classSession->id,
        ]);

        if ($request->expectsJson()) {
            return ApiResponse::success($updated, [], 'Class session updated successfully');
        }

        Inertia::flash('message', 'Class session updated successfully.');

        return redirect()->back();
    }

    public function generateAttendance(ClassSession $classSession, ClassSessionAttendanceFilterRequest $request): JsonResponse|RedirectResponse
    {
        $result = GenerateClassSessionAttendanceAction::run(['class_session_id' => $classSession->id]);

        if ($request->expectsJson()) {
            return ApiResponse::compatible($result);
        }

        Inertia::flash($result['success'] ? 'success' : 'error', $result['message']);

        return redirect()->back();
    }

    public function exportAttendance(ClassSessionAttendanceFilterRequest $request, ClassSession $classSession, ExportClassSessionAttendanceQuery $query): HttpResponse
    {
        $export = $query->handle($classSession, $request->validated());

        return response($export['content'], 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$export['filename'].'"',
        ]);
    }

    public function destroy(ClassSessionAttendanceFilterRequest $request, ClassSession $classSession): JsonResponse|RedirectResponse
    {
        DeleteClassSessionAction::run(['class_session_id' => $classSession->id]);

        if ($request->expectsJson()) {
            return ApiResponse::success(null, [], 'Class session deleted successfully');
        }

        Inertia::flash('message', 'Class session deleted successfully.');

        return redirect()->back();
    }

    public function createForOffering(CourseOfferingCampusRequest $request, CourseOffering $courseOffering, GetClassSessionFormOptionsQuery $query): Response
    {
        return Inertia::render('ClassSessions/modals/Add', $query->handle('createForOffering', $courseOffering));
    }

    public function editModal(ClassSessionCampusRequest $request, ClassSession $classSession, GetClassSessionFormOptionsQuery $query): Response
    {
        return Inertia::render('ClassSessions/modals/QuickEdit', $query->handle('editModal', $classSession));
    }

    public function bulkEditModal(BulkEditClassSessionsRequest $request, CourseOfferingCampusRequest $campusRequest, CourseOffering $courseOffering, GetClassSessionFormOptionsQuery $query): Response
    {
        $sessionIds = array_filter(explode(',', $request->validated('ids', '')));

        return Inertia::render('ClassSessions/modals/BulkEdit', $query->handle('bulkEdit', $courseOffering, $sessionIds));
    }

    public function bulkDestroy(BulkDeleteWebClassSessionsRequest $request): RedirectResponse
    {
        $count = BulkDeleteWebClassSessionsAction::run($request->validated());
        Inertia::flash('message', "{$count} session(s) deleted successfully.");

        return redirect()->back();
    }

    public function generate(GenerateClassSessionsRequest $request, CourseOffering $courseOffering): RedirectResponse
    {
        $sessions = GenerateCourseOfferingClassSessionsAction::run([
            ...$request->validated(),
            'course_offering_id' => $courseOffering->id,
        ]);
        Inertia::flash('message', "{$sessions->count()} class sessions generated successfully.");

        return redirect()->back();
    }
}
