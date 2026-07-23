<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Actions\Form\CheckPortalGateAction;
use App\Actions\Form\SubmitResponseAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Form\SubmitFormRequest;
use App\Http\Resources\FormDetailResource;
use App\Http\Resources\FormResource;
use App\Http\Resources\StudentFormAssignmentResource;
use App\Http\Responses\ApiResponse;
use App\Models\Campus;
use App\Models\Form;
use App\Services\FormService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FormController extends Controller
{
    public function __construct(
        protected FormService $formService,
        protected ResponseService $responseService,
    ) {}

    /**
     * Get available forms for authenticated student.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['required', 'string', 'in:feedback,survey,query'],
            'campus_id' => ['nullable', 'exists:campuses,id'],
        ]);

        // Get authenticated student
        $student = $request->user();

        if (! $student->isActive()) {
            return ApiResponse::authorizationError('Student account is not active. Please contact administration.');
        }

        // Get student's campus (from request or student's default campus)
        $campusId = $request->input('campus_id', $student->campus_id);
        $campus = Campus::find($campusId);

        // Get available forms filtered by type
        $forms = $this->formService->getAvailableFormsForStudent($student, $campus)
            ->filter(function ($form) use ($request) {
                return $form->type === $request->input('type');
            });

        return ApiResponse::success(
            FormResource::collection($forms),
            [],
            'Get available forms for authenticated student.'
        );
    }

    /**
     * Get query forms that have active targets (Runs) for the student.
     */
    public function queryRuns(Request $request): JsonResponse
    {
        $request->validate([
            'campus_id' => ['nullable', 'exists:campuses,id'],
        ]);

        $student = $request->user();
        if (! $student->isActive()) {
            return ApiResponse::authorizationError('Student account is not active.');
        }

        $campusId = $request->input('campus_id', $student->campus_id);

        // Get available forms of type 'query'
        // We use FormService to reuse the eligibility logic (time window, campus, etc.)
        $forms = $this->formService->getAvailableFormsForStudent($student, Campus::find($campusId))
            ->filter(function ($form) {
                return $form->type === 'query';
            })
            ->values();

        // Load detailed relationships for the UI
        $forms->each(function ($form) use ($student, $campusId) {
            $form->load([
                'latestPublishedVersion.sections.questions.options',
                'latestPublishedVersion.questions.options',
            ]);

            // Targets are already loaded and filtered by FormService::getAvailableFormsForStudent
            // but we ensure they are present just in case
            if (! $form->relationLoaded('targets')) {
                $form->setRelation('targets', $this->formService->getEligibleTargetsForForm($form, $student, $campusId));
            }
        });

        return ApiResponse::success(
            FormDetailResource::collection($forms),
            [],
            'Query forms with active runs retrieved successfully.'
        );
    }

    /**
     * Display the specified form for student.
     */
    public function show(Form $form, Request $request): JsonResponse
    {
        $request->validate([
            'campus_id' => ['nullable', 'exists:campuses,id'],
        ]);

        $student = $request->user();
        if (! $student->isActive()) {
            return ApiResponse::authorizationError('Student account is not active.');
        }

        $campusId = $request->input('campus_id', $student->campus_id);
        $campus = Campus::find($campusId);

        if (! $campus) {
            return ApiResponse::notFound('Campus not found.');
        }

        // Check if student can access this form (incorporating type-specific eligibility logic)
        $availableForms = $this->formService->getAvailableFormsForStudent($student, $campus);
        $canAccess = $availableForms->contains('id', $form->id);

        if (! $canAccess) {
            return ApiResponse::authorizationError('Form not available or access denied.');
        }

        // Load form with detailed relationships
        $form->load([
            'latestPublishedVersion.sections.questions.options',
            'latestPublishedVersion.questions.options',
        ]);

        // Targets are filtered by FormService
        $form->setRelation('targets', $this->formService->getEligibleTargetsForForm($form, $student, $campusId));

        // Check if student can still submit
        $canSubmit = $this->formService->canStudentSubmitForm($student, $form, $campus);

        return ApiResponse::success(
            new FormDetailResource($form),
        );
    }

    /**
     * Get mandatory pending forms.
     */
    public function pending(Request $request, CheckPortalGateAction $checkGateAction): JsonResponse
    {
        $student = $request->user();
        if (! $student) {
            return ApiResponse::authenticationError();
        }

        $result = $checkGateAction->execute($student);

        return ApiResponse::success(
            StudentFormAssignmentResource::collection($result['mandatory_assignments']),
            [],
            'Pending mandatory forms retrieved.'
        );
    }

    /**
     * Submit a form response.
     */
    public function submit(
        SubmitFormRequest $request,
        Form $form,
        SubmitResponseAction $action
    ): JsonResponse {
        $student = $request->user();
        if (! $student) {
            return ApiResponse::notFound('Student not found');
        }

        try {
            $response = $action->execute($student, $form, $request->validated());

            return ApiResponse::success(
                new FormDetailResource($response),
                [],
                'Form submitted successfully',
                201
            );
        } catch (ValidationException $e) {
            return ApiResponse::businessLogicError($e->getMessage());
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to submit form',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}
