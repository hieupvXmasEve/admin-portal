<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\SystemConfigService;
use App\Models\Student;
use App\Models\StudentFormSurvey;
use App\Models\FormSurvey;
use App\Models\FormResponse;
use App\Services\ResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SurveyController extends Controller
{
    public function __construct(
        protected ResponseService $responseService,
        protected SystemConfigService $systemConfigService
    ) {}

    /**
     * Get pending surveys for authenticated student
     */
    public function pending(Request $request): JsonResponse
    {
        // Check if survey feature is enabled
        $config = $this->systemConfigService->getConfig();
        if (empty($config['survey_enabled'])) {
             return ApiResponse::success(
                [],
                [],
                'Survey feature is disabled.'
            );
        }

        // Get authenticated student
        $student = Auth::guard('student')->user();

        // If not authenticated as student, check if parent user is accessing
        if (!$student) {
            /** @var \App\Models\User|null $user */
            $user = Auth::user();
            if ($user && $request->has('campus_id')) {
                $student = $user->children()->where('campus_id', $request->input('campus_id'))->first();
            }
        }

        if (!$student) {
            return ApiResponse::authenticationError('Student not found');
        }

        // Get all pending surveys for this student
        $pendingSurveys = StudentFormSurvey::where('student_id', $student->id)
            ->where('status', '!=', 'completed')
            ->with([
                'formSurvey.form',
                'formSurvey.formVersion',
                'formSurvey.courseOffering.unit',
                'formSurvey.courseOffering.semester',
            ])
            ->get()
            ->map(function ($studentSurvey) {
                $formSurvey = $studentSurvey->formSurvey;
                return [
                    'id' => $studentSurvey->id,
                    'form_survey_id' => $formSurvey->id,
                    'form_id' => $formSurvey->form_id,
                    'form_title' => $formSurvey->form->title,
                    'course_code' => $formSurvey->courseOffering->course_code,
                    'course_title' => $formSurvey->courseOffering->course_title,
                    'semester' => $formSurvey->courseOffering->semester->code ?? null,
                    'status' => $studentSurvey->status,
                    'created_at' => $studentSurvey->created_at->toISOString(),
                ];
            });

        return ApiResponse::success(
            $pendingSurveys,
            [],
            'Pending surveys retrieved successfully.'
        );
    }

    /**
     * Get completed surveys for authenticated student
     */
    public function completed(Request $request): JsonResponse
    {
        // Get authenticated student
        $student = Auth::guard('student')->user();

        // If not authenticated as student, check if parent user is accessing
        if (!$student) {
            /** @var \App\Models\User|null $user */
            $user = Auth::user();
            if ($user && $request->has('campus_id')) {
                $student = $user->children()->where('campus_id', $request->input('campus_id'))->first();
            }
        }

        if (!$student) {
            return ApiResponse::authenticationError('Student not found');
        }

        // Get all completed surveys for this student
        $completedSurveys = StudentFormSurvey::where('student_id', $student->id)
            ->where('status', 'completed')
            ->with([
                'formSurvey.form',
                'formSurvey.courseOffering.unit',
                'formSurvey.courseOffering.semester',
            ])
            ->orderBy('completed_at', 'desc')
            ->get()
            ->map(function ($studentSurvey) {
                $formSurvey = $studentSurvey->formSurvey;
                return [
                    'id' => $studentSurvey->id,
                    'form_survey_id' => $formSurvey->id,
                    'form_id' => $formSurvey->form_id,
                    'form_title' => $formSurvey->form->title,
                    'course_code' => $formSurvey->courseOffering->course_code,
                    'course_title' => $formSurvey->courseOffering->course_title,
                    'semester' => $formSurvey->courseOffering->semester->code ?? null,
                    'completed_at' => $studentSurvey->completed_at->toISOString(),
                ];
            });

        return ApiResponse::success(
            $completedSurveys,
            [],
            'Completed surveys retrieved successfully.'
        );
    }

    /**
     * Show survey details
     */
    public function show(StudentFormSurvey $survey, Request $request): JsonResponse
    {
        // Get authenticated student
        $student = Auth::guard('student')->user();

        // If not authenticated as student, check if parent user is accessing
        if (!$student) {
            /** @var \App\Models\User|null $user */
            $user = Auth::user();
            if ($user && $request->has('campus_id')) {
                $student = $user->children()->where('campus_id', $request->input('campus_id'))->first();
            }
        }

        if (!$student) {
            return ApiResponse::authenticationError('Student not found');
        }

        // Verify this survey belongs to the student
        if ($survey->student_id !== $student->id) {
            return ApiResponse::authorizationError('Access denied. This survey does not belong to you.');
        }

        // Load survey with all necessary relationships
        $survey->load([
            'formSurvey.form',
            'formSurvey.formVersion.sections.questions.options',
            'formSurvey.formVersion.questions.options',
            'formSurvey.courseOffering.unit',
            'formSurvey.courseOffering.semester',
        ]);

        $formSurvey = $survey->formSurvey;
        $formVersion = $formSurvey->formVersion;

        return ApiResponse::success([
            'id' => $survey->id,
            'form_survey_id' => $formSurvey->id,
            'form_id' => $formSurvey->form_id,
            'form_version_id' => $formVersion->id,
            'form_title' => $formSurvey->form->title,
            'form_description' => $formSurvey->form->description,
            'course_code' => $formSurvey->courseOffering->course_code,
            'course_title' => $formSurvey->courseOffering->course_title,
            'semester' => $formSurvey->courseOffering->semester->code ?? null,
            'status' => $survey->status,
            'form_version' => [
                'id' => $formVersion->id,
                'version_no' => $formVersion->version_no,
                'sections' => $formVersion->sections->map(function ($section) {
                    return [
                        'id' => $section->id,
                        'title' => $section->title,
                        'order_index' => $section->order_index,
                        'questions' => $section->questions->map(function ($question) {
                            return [
                                'id' => $question->id,
                                'code' => $question->code,
                                'text' => $question->text,
                                'type' => $question->type,
                                'is_required' => $question->is_required,
                                'help_text' => $question->help_text,
                                'options' => $question->options->map(function ($option) {
                                    return [
                                        'id' => $option->id,
                                        'text' => $option->text,
                                        'value' => $option->value,
                                    ];
                                }),
                            ];
                        }),
                    ];
                }),
                'questions' => $formVersion->questions->whereNull('section_id')->map(function ($question) {
                    return [
                        'id' => $question->id,
                        'code' => $question->code,
                        'text' => $question->text,
                        'type' => $question->type,
                        'is_required' => $question->is_required,
                        'help_text' => $question->help_text,
                        'options' => $question->options->map(function ($option) {
                            return [
                                'id' => $option->id,
                                'text' => $option->label,
                                'value' => $option->value,
                            ];
                        }),
                    ];
                }),
            ],
        ]);
    }

    /**
     * Submit survey response
     */
    public function submit(Request $request, StudentFormSurvey $survey): JsonResponse
    {
        // Get authenticated student
        $student = Auth::guard('student')->user();

        // If not authenticated as student, check if parent user is accessing
        if (!$student) {
            /** @var \App\Models\User|null $user */
            $user = Auth::user();
            if ($user && $request->has('campus_id')) {
                $student = $user->children()->where('campus_id', $request->input('campus_id'))->first();
            }
        }

        if (!$student) {
            return ApiResponse::authenticationError('Student not found');
        }

        // Verify this survey belongs to the student
        if ($survey->student_id !== $student->id) {
            return ApiResponse::authorizationError('Access denied. This survey does not belong to you.');
        }

        // Check if already completed
        if ($survey->status === 'completed') {
            return ApiResponse::validationError(['survey' => ['This survey has already been completed.']]);
        }

        $request->validate([
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'integer', 'exists:questions,id'],
        ]);

        return DB::transaction(function () use ($request, $survey, $student) {
            $formSurvey = $survey->formSurvey;
            $form = $formSurvey->form;
            $formVersion = $formSurvey->formVersion;
            $campus = $student->campus;

            // Create form response using ResponseService
            $response = $this->responseService->submitResponse(
                $student,
                $form,
                $formVersion,
                $campus,
                [
                    'target_scope_type' => 'course',
                    'target_scope_id' => $formSurvey->course_offering_id,
                    'answers' => $request->input('answers'),
                    'anonymized' => false,
                    'origin' => 'web',
                ]
            );

            // Mark survey as completed
            $survey->markAsCompleted($response->id);

            return ApiResponse::success(
                ['response_id' => $response->id],
                [],
                'Survey submitted successfully.'
            );
        });
    }

    /**
     * Get survey responses (view own responses)
     */
    public function responses(StudentFormSurvey $survey, Request $request): JsonResponse
    {
        // Get authenticated student
        $student = Auth::guard('student')->user();

        // If not authenticated as student, check if parent user is accessing
        if (!$student) {
            /** @var \App\Models\User|null $user */
            $user = Auth::user();
            if ($user && $request->has('campus_id')) {
                $student = $user->children()->where('campus_id', $request->input('campus_id'))->first();
            }
        }

        if (!$student) {
            return ApiResponse::authenticationError('Student not found');
        }

        // Verify this survey belongs to the student
        if ($survey->student_id !== $student->id) {
            return ApiResponse::authorizationError('Access denied. This survey does not belong to you.');
        }

        // Check if survey is completed
        if ($survey->status !== 'completed' || !$survey->response_id) {
            return ApiResponse::validationError(['survey' => ['Survey has not been completed yet.']]);
        }

        // Load response with answers
        $response = FormResponse::with([
            'answers.question',
            'answers.selectedOptions',
            'answers.attachments',
        ])->find($survey->response_id);

        if (!$response) {
            return ApiResponse::notFound('Response not found');
        }

        return ApiResponse::success([
            'response' => [
                'id' => $response->id,
                'submitted_at' => $response->submitted_at->toISOString(),
                'answers' => $response->answers->map(function ($answer) {
                    return [
                        'question_id' => $answer->question_id,
                        'question_text' => $answer->question->text,
                        'question_type' => $answer->question->type,
                        'answer_text' => $answer->answer_text,
                        'answer_number' => $answer->answer_number,
                        'answer_date' => $answer->answer_date?->toDateString(),
                        'selected_options' => $answer->selectedOptions->map(function ($option) {
                            return [
                                'id' => $option->id,
                                'text' => $option->text,
                                'value' => $option->value,
                            ];
                        }),
                        'attachments' => $answer->attachments->map(function ($attachment) {
                            return [
                                'id' => $attachment->id,
                                'file_name' => $attachment->file_name,
                                'file_path' => $attachment->file_path,
                                'file_size' => $attachment->file_size,
                            ];
                        }),
                    ];
                }),
            ],
        ]);
    }
}
