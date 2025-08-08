<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Program;
use App\Models\ProgramChangeRequest;
use App\Models\Specialization;
use App\Models\Student;
use App\Services\ProgramChangeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProgramChangeController extends Controller
{
    public function __construct(
        private ProgramChangeService $programChangeService
    ) {}

    /**
     * Display program change requests
     */
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'status' => 'nullable|string|in:pending,approved,rejected',
            'student_code' => 'nullable|exists:students,id',
            'from_program_id' => 'nullable|exists:programs,id',
            'to_program_id' => 'nullable|exists:programs,id',
        ]);

        $campusId = session()->get('current_campus_id');
        $filters = array_merge($validated, ['campus_id' => $campusId]);

        $requests = $this->programChangeService->getChangeRequests($filters);
        $statistics = $this->programChangeService->getChangeStatistics($filters);

        return Inertia::render('students/ProgramChanges/Index', [
            'requests' => $requests,
            'statistics' => $statistics,
            'filters' => $validated,
        ]);
    }

    /**
     * Show program change request details
     */
    public function show(ProgramChangeRequest $programChangeRequest): Response
    {
        $programChangeRequest->load([
            'student.campus',
            'student.program',
            'student.specialization',
            'fromProgram',
            'toProgram',
            'fromSpecialization',
            'toSpecialization',
            'approvedBy',
        ]);

        return Inertia::render('students/ProgramChanges/Show', [
            'request' => $programChangeRequest,
        ]);
    }

    /**
     * Show create form for student program change
     */
    public function create(Student $student): Response
    {
        $this->authorize('update', $student);

        $programs = Program::where('is_active', true)->get();
        $specializations = Specialization::where('is_active', true)->get();

        return Inertia::render('students/ProgramChanges/Create', [
            'student' => $student->load(['program', 'specialization']),
            'programs' => $programs,
            'specializations' => $specializations,
        ]);
    }

    /**
     * Store new program change request
     */
    public function store(Student $student, Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $student);

        $validated = $request->validate(ProgramChangeRequest::validationRules(), ProgramChangeRequest::validationMessages());

        try {
            $changeRequest = $this->programChangeService->createChangeRequest($student, $validated);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Program change request created successfully',
                    'request' => $changeRequest,
                ]);
            }

            return redirect()
                ->route('students.program-changes.show', $changeRequest)
                ->with('success', 'Program change request submitted successfully');
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Failed to create program change request',
                    'error' => $e->getMessage(),
                ], 422);
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to create program change request: '.$e->getMessage());
        }
    }

    /**
     * Approve program change request
     */
    public function approve(ProgramChangeRequest $programChangeRequest, Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('approve', $programChangeRequest);

        $validated = $request->validate([
            'approval_notes' => 'nullable|string',
        ]);

        try {
            $updatedRequest = $this->programChangeService->approveChangeRequest(
                $programChangeRequest,
                $request->user(),
                $validated
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Program change request approved successfully',
                    'request' => $updatedRequest,
                ]);
            }

            return redirect()
                ->route('program-changes.show', $programChangeRequest)
                ->with('success', 'Program change request approved successfully');
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Failed to approve program change request',
                    'error' => $e->getMessage(),
                ], 422);
            }

            return redirect()
                ->back()
                ->with('error', 'Failed to approve request: '.$e->getMessage());
        }
    }

    /**
     * Reject program change request
     */
    public function reject(ProgramChangeRequest $programChangeRequest, Request $request): RedirectResponse|JsonResponse
    {
        $this->authorize('approve', $programChangeRequest);

        $validated = $request->validate([
            'rejection_reason' => 'required|string|min:10',
        ]);

        try {
            $updatedRequest = $this->programChangeService->rejectChangeRequest(
                $programChangeRequest,
                $request->user(),
                $validated['rejection_reason']
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Program change request rejected',
                    'request' => $updatedRequest,
                ]);
            }

            return redirect()
                ->route('program-changes.show', $programChangeRequest)
                ->with('success', 'Program change request rejected');
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Failed to reject program change request',
                    'error' => $e->getMessage(),
                ], 422);
            }

            return redirect()
                ->back()
                ->with('error', 'Failed to reject request: '.$e->getMessage());
        }
    }

    /**
     * Evaluate credit transfer for program change
     */
    public function evaluateCredits(Student $student, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to_program_id' => 'required|exists:programs,id',
            'to_specialization_id' => 'nullable|exists:specializations,id',
        ]);

        try {
            $evaluation = $this->programChangeService->evaluateAffectedCredits($student, $validated);

            return response()->json([
                'evaluation' => $evaluation,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to evaluate credits',
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
