<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Api\Student;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Form;
use App\Modules\Engagement\Actions\Forms\CheckPortalGateAction;
use App\Modules\Engagement\Actions\Forms\SubmitResponseAction;
use App\Modules\Engagement\Http\Requests\Forms\ListStudentFormsRequest;
use App\Modules\Engagement\Http\Requests\Forms\StudentFormCampusRequest;
use App\Modules\Engagement\Http\Requests\Forms\SubmitFormRequest;
use App\Modules\Engagement\Http\Resources\FormDetailResource;
use App\Modules\Engagement\Http\Resources\FormResource;
use App\Modules\Engagement\Http\Resources\StudentFormAssignmentResource;
use App\Modules\Engagement\Support\FormWorkflow;
use App\Modules\Engagement\Support\ResponseWorkflow;
use App\Shared\Contracts\Institution\CampusReferenceReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FormController extends Controller
{
    public function __construct(
        protected FormWorkflow $formService,
        protected ResponseWorkflow $responseService,
        private CampusReferenceReader $campuses,
    ) {}

    /**
     * Get available forms for authenticated student.
     */
    public function index(ListStudentFormsRequest $request): JsonResponse
    {
        // Get authenticated student
        $student = $request->user();

        if (! $this->isStudentActor($student) || ! $student->isActive()) {
            return ApiResponse::authorizationError('Student account is not active. Please contact administration.');
        }

        $campusId = $this->resolveCampusId($request, $student);
        if ($campusId === null) {
            return ApiResponse::authorizationError('Campus access denied.');
        }

        $campus = $this->campuses->find((int) $campusId);

        // Get available forms filtered by type
        $forms = $this->formService->getAvailableFormsForStudent($student, $campus?->id)
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
    public function queryRuns(StudentFormCampusRequest $request): JsonResponse
    {
        $student = $request->user();
        if (! $this->isStudentActor($student) || ! $student->isActive()) {
            return ApiResponse::authorizationError('Student account is not active.');
        }

        $campusId = $this->resolveCampusId($request, $student);
        if ($campusId === null) {
            return ApiResponse::authorizationError('Campus access denied.');
        }

        // Get available forms of type 'query'
        // We use FormService to reuse the eligibility logic (time window, campus, etc.)
        $forms = $this->formService->getAvailableFormsForStudent($student, $this->campuses->find((int) $campusId)?->id)
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
    public function show(Form $form, StudentFormCampusRequest $request): JsonResponse
    {
        $student = $request->user();
        if (! $this->isStudentActor($student) || ! $student->isActive()) {
            return ApiResponse::authorizationError('Student account is not active.');
        }

        $campusId = $this->resolveCampusId($request, $student);
        if ($campusId === null) {
            return ApiResponse::authorizationError('Campus access denied.');
        }

        $campus = $this->campuses->find((int) $campusId);

        if (! $campus) {
            return ApiResponse::notFound('Campus not found.');
        }

        // Check if student can access this form (incorporating type-specific eligibility logic)
        $availableForms = $this->formService->getAvailableFormsForStudent($student, $campus->id);
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
        $canSubmit = $this->formService->canStudentSubmitForm($student, $form, $campus->id);

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

        $result = $checkGateAction->execute((int) $student->id);

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
        if (! $this->isStudentActor($student)) {
            return ApiResponse::notFound('Student not found');
        }

        if ($this->resolveCampusId($request, $student) === null) {
            return ApiResponse::authorizationError('Campus access denied.');
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

    private function resolveCampusId(Request $request, object $student): ?int
    {
        $requestedCampusId = $request->input('campus_id');
        if ($requestedCampusId !== null && (int) $requestedCampusId !== (int) $student->campus_id) {
            return null;
        }

        return (int) ($requestedCampusId ?? $student->campus_id);
    }

    private function isStudentActor(mixed $actor): bool
    {
        return is_object($actor)
            && filled($actor->student_id)
            && filled($actor->campus_id);
    }
}
