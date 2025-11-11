<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Form\SubmitFormRequest;
use App\Http\Resources\FormResource;
use App\Http\Resources\FormDetailResource;
use App\Http\Resources\FormResponseResource;
use App\Http\Responses\ApiResponse;
use App\Models\Campus;
use App\Models\Form;
use App\Models\FormVersion;
use App\Models\Student;
use App\Models\User;
use App\Services\FormService;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FormController extends Controller
{
    public function __construct(
        protected FormService $formService,
        protected ResponseService $responseService
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

        if (!$student->isActive()) {
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
            "Get available forms for authenticated student."
        );
    }

    /**
     * Get the last available query form for authenticated student.
     */
    public function last(Request $request): JsonResponse
    {
        $request->validate([
            'campus_id' => ['nullable', 'exists:campuses,id'],
        ]);

        // Get authenticated student
        $student = $request->user();

        if (!$student->isActive()) {
            return ApiResponse::authorizationError('Student account is not active. Please contact administration.');
        }

        // Get student's campus (from request or student's default campus)
        $campusId = $request->input('campus_id', $student->campus_id);
        $campus = Campus::find($campusId);

        if (!$campus) {
            return ApiResponse::notFound('Campus not found');
        }

        // Get available forms filtered by type 'query' (default)
        $forms = $this->formService->getAvailableFormsForStudent($student, $campus)
            ->filter(function ($form) {
                return $form->type === 'query';
            });

        // Sort by created_at descending and get the first one (latest)
        $form = $forms->sortByDesc('created_at')->first();

        if (!$form) {
            return ApiResponse::success(
                null,
                [],
                'No form found.'
            );
        }

        // Load form with necessary relationships (same as show method)
        $form->load([
            'latestPublishedVersion.sections.questions.options',
            'latestPublishedVersion.questions.options',
            'targets' => function ($query) use ($campus) {
                $query->where(function ($q) use ($campus) {
                    $q->whereNull('campus_id')
                        ->orWhere('campus_id', $campus->id);
                });
            },
        ]);

        // Check if student can still submit
        $canSubmit = $this->formService->canStudentSubmitForm($student, $form, $campus);

        return ApiResponse::success(
            new FormDetailResource($form),
            [],
            'Last form retrieved successfully.'
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

        // Get authenticated student
        $student = Auth::guard('student')->user();

        // If not authenticated as student, check if parent user is accessing
        if (!$student) {
            /** @var User|null $user */
            $user = Auth::user();
            if ($user && $request->has('campus_id')) {
                $student = $user->children()->where('campus_id', $request->input('campus_id'))->first();
            }
        }

        if (!$student) {
            return response()->json([
                'message' => 'Student not found',
            ], 404);
        }

        // Get student's campus
        $campusId = $request->input('campus_id', $student->campus_id);
        $campus = Campus::find($campusId);

        if (!$campus) {
            return response()->json([
                'message' => 'Campus not found',
            ], 404);
        }

        // Check if student can access this form
        $availableForms = $this->formService->getAvailableFormsForStudent($student, $campus);
        $canAccess = $availableForms->contains('id', $form->id);

        if (!$canAccess) {
            return response()->json([
                'message' => 'Form not available or access denied',
            ], 403);
        }

        // Load form with necessary relationships
        $form->load([
            'latestPublishedVersion.sections.questions.options',
            'latestPublishedVersion.questions.options',
            'targets' => function ($query) use ($campus) {
                $query->where(function ($q) use ($campus) {
                    $q->whereNull('campus_id')
                        ->orWhere('campus_id', $campus->id);
                });
            },
        ]);

        // Check if student can still submit
        $canSubmit = $this->formService->canStudentSubmitForm($student, $form, $campus);

        return ApiResponse::success(
            new FormDetailResource($form),
        );
    }

    /**
     * Submit a form response.
     */
    public function submit(SubmitFormRequest $request, Form $form): JsonResponse
    {
        // Get authenticated student
        $student = Auth::guard('student')->user();

        // If not authenticated as student, check if parent user is accessing
        if (!$student) {
            /** @var User|null $user */
            $user = Auth::user();
            if ($user && $request->has('campus_id')) {
                $student = $user->children()->where('campus_id', $request->input('campus_id'))->first();
            }
        }

        if (!$student) {
            return ApiResponse::notFound('Student not found');
        }

        // Get campus
        $campus = Campus::find($request->input('campus_id', $student->campus_id));
        if (!$campus) {
            return ApiResponse::notFound('Campus not found');
        }

        // Check if student can submit this form
        if (!$this->formService->canStudentSubmitForm($student, $form, $campus)) {
            return ApiResponse::businessLogicError('You cannot submit this form. Either submission limit exceeded or form not available.');
        }

        // Get latest published version
        $version = $form->latestPublishedVersion;
        if (!$version) {
            return ApiResponse::businessLogicError('No published version available for this form');
        }

        try {
            $response = $this->responseService->submitResponse(
                $student,
                $form,
                $version,
                $campus,
                $request->validated()
            );
            return ApiResponse::success(
                new FormDetailResource($response),
                [],
                "Form submitted successfully",
                201
            );
        } catch (\Exception $e) {
            return ApiResponse::error(
                'Failed to submit form',
                [

                    'error' => $e->getMessage(),
                ],
                500
            );
        }
    }
}
