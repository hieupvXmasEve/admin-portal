<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Support;

use App\Models\Answer;
use App\Models\Campus;
use App\Models\QueryTicket;
use App\Models\Student;
use App\Models\User;
use App\Modules\Engagement\Models\Form;
use App\Modules\Engagement\Models\FormResponse;
use App\Modules\Engagement\Models\FormVersion;
use App\Shared\Contracts\Upload\FileUploadGateway;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ResponseWorkflow
{
    public function __construct(private FileUploadGateway $imageUploadService) {}

    /**
     * Submit a form response.
     */
    public function submitResponse(
        Student $student,
        Form $form,
        FormVersion $version,
        Campus $campus,
        array $data
    ): FormResponse {
        return DB::transaction(function () use ($student, $form, $version, $campus, $data) {
            // Create the response
            $response = FormResponse::create([
                'form_id' => $form->id,
                'form_version_id' => $version->id,
                'campus_id' => $campus->id,
                'target_scope_type' => $data['target_scope_type'] ?? 'global',
                'target_scope_id' => $data['target_scope_id'] ?? null,
                'submitted_by_student_id' => $data['anonymized'] ?? false ? null : $student->id,
                'anonymized' => $data['anonymized'] ?? false,
                'status' => 'submitted',
                'origin' => $data['origin'] ?? 'web',
                'submitted_at' => now(),
            ]);

            // Process answers
            if (isset($data['answers']) && is_array($data['answers'])) {
                foreach ($data['answers'] as $answerData) {
                    if (isset($answerData['question_id'])) {
                        $this->saveAnswer($response, $answerData['question_id'], $answerData);
                    }
                }
            }

            // If it's a query type form, create a ticket
            if ($form->type === 'query') {
                $this->createQueryTicket($response, $data);
            }

            return $response->load(['answers.question', 'answers.selectedOptions']);
        });
    }

    /**
     * Save an answer for a question.
     */
    protected function saveAnswer(FormResponse $response, int $questionId, $answerData): Answer
    {
        $question = $response->formVersion->questions()->find($questionId);

        if (! $question) {
            throw new \InvalidArgumentException("Question {$questionId} not found in form version");
        }

        $answer = Answer::create([
            'response_id' => $response->id,
            'question_id' => $questionId,
        ]);

        // Handle different question types
        switch ($question->type) {
            case 'short_text':
            case 'long_text':
            case 'yes_no':
                $answer->answer_text = $answerData['answer_text'] ?? null;
                break;

            case 'number':
            case 'rating':
                $answer->answer_number = $answerData['answer_number'] ?? null;
                break;

            case 'date':
                $answer->answer_date = $answerData['answer_date'] ?? null;
                break;

            case 'single_choice':
            case 'multi_choice':
            case 'likert':
                if (isset($answerData['selected_options']) && is_array($answerData['selected_options'])) {
                    foreach ($answerData['selected_options'] as $optionData) {
                        if (isset($optionData['option_id'])) {
                            $answer->selectedOptions()->attach($optionData['option_id'], [
                                'free_text' => $optionData['free_text'] ?? null,
                            ]);
                        }
                    }
                }
                break;

            case 'matrix':
                // Store matrix data as JSON
                $answer->answer_text = json_encode($answerData['answer_text'] ?? []);
                break;

            case 'file':
                // Handle file uploads separately
                if (isset($answerData['file']) && $answerData['file'] instanceof UploadedFile) {
                    $this->handleFileUpload($answer, $answerData['file'], $response);
                }
                break;
        }

        // Save comment if provided
        if (isset($answerData['comment'])) {
            $answer->comment = $answerData['comment'];
        }

        $answer->save();

        return $answer;
    }

    /**
     * Handle file upload for an answer.
     */
    protected function handleFileUpload(Answer $answer, UploadedFile $file, FormResponse $response): void
    {
        $metadata = [
            'response_id' => $response->id,
            'answer_id' => $answer->id,
        ];

        if ($response->submitted_by_student_id) {
            $metadata['student_id'] = $response->submitted_by_student_id;
        }

        $this->imageUploadService->store(
            $file,
            'form_attachment',
            null,
            $response->submitted_by_student_id,
            $metadata
        );
    }

    /**
     * Create a query ticket for a query-type form response.
     */
    protected function createQueryTicket(FormResponse $response, array $data): QueryTicket
    {
        return QueryTicket::create([
            'response_id' => $response->id,
            'topic_id' => $data['topic_id'] ?? null,
            'custom_topic_text' => $data['custom_topic_text'] ?? null,
            'status' => QueryTicket::STATUS_OPEN,
            'priority' => $data['priority'] ?? 'normal',
        ]);
    }

    /**
     * Get responses for review.
     */
    public function getResponsesForReview(User $user, Campus $campus, array $filters = []): Collection
    {
        $query = FormResponse::with(['form', 'student', 'campus', 'answers.question'])
            ->forCampus($campus->id)
            ->whereHas('form', function ($q) use ($user) {
                // Only get forms where user has review permissions
                $q->whereHas('resultVisibility', function ($query) use ($user) {
                    $query->whereHas('role', function ($q) use ($user) {
                        $q->whereIn('id', $user->campusUserRoles()->pluck('role_id'));
                    })
                        ->where('visibility_level', 'full_detail');
                });
            });

        // Apply filters
        if (isset($filters['status'])) {
            $query->status($filters['status']);
        }

        if (isset($filters['form_id'])) {
            $query->where('form_id', $filters['form_id']);
        }

        if (isset($filters['pending_review']) && $filters['pending_review']) {
            $query->pendingReview();
        }

        if (isset($filters['date_from'])) {
            $query->where('submitted_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('submitted_at', '<=', $filters['date_to']);
        }

        return $query->orderBy('submitted_at', 'desc')->get();
    }

    /**
     * Review a response (approve or reject).
     */
    public function reviewResponse(
        FormResponse $response,
        User $reviewer,
        string $action,
        ?string $notes = null
    ): FormResponse {
        if (! $response->canBeReviewedBy($reviewer)) {
            throw new \Exception('You do not have permission to review this response');
        }

        if ($action === 'approve') {
            $response->approve($reviewer, $notes);
        } elseif ($action === 'reject') {
            $response->reject($reviewer, $notes);
        } else {
            throw new \InvalidArgumentException("Invalid review action: {$action}");
        }

        return $response->fresh(['reviewer']);
    }

    /**
     * Get aggregated analytics for a form.
     */
    public function getFormAnalytics(Form $form, ?Campus $campus = null, array $filters = []): array
    {
        $query = $form->responses();

        if ($campus) {
            $query->forCampus($campus->id);
        }

        if (isset($filters['date_from'])) {
            $query->where('submitted_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('submitted_at', '<=', $filters['date_to']);
        }

        $responses = $query->with(['answers.question', 'answers.selectedOptions'])->get();

        // Aggregate answers by question
        $analytics = [];
        $questions = $form->latestPublishedVersion->questions;

        foreach ($questions as $question) {
            $questionAnalytics = [
                'question_id' => $question->id,
                'question_text' => $question->text,
                'question_type' => $question->type,
                'total_responses' => 0,
                'data' => [],
            ];

            $answers = Answer::whereIn('response_id', $responses->pluck('id'))
                ->where('question_id', $question->id)
                ->get();

            $questionAnalytics['total_responses'] = $answers->count();

            switch ($question->type) {
                case 'single_choice':
                case 'multi_choice':
                case 'likert':
                    // Count option selections
                    foreach ($question->options as $option) {
                        $count = DB::table('answer_options')
                            ->whereIn('answer_id', $answers->pluck('id'))
                            ->where('option_id', $option->id)
                            ->count();

                        $questionAnalytics['data'][] = [
                            'option' => $option->label,
                            'count' => $count,
                            'percentage' => $questionAnalytics['total_responses'] > 0
                                ? round(($count / $questionAnalytics['total_responses']) * 100, 2)
                                : 0,
                        ];
                    }
                    break;

                case 'rating':
                case 'number':
                    // Calculate statistics
                    $values = $answers->pluck('answer_number')->filter();
                    if ($values->count() > 0) {
                        $questionAnalytics['data'] = [
                            'min' => $values->min(),
                            'max' => $values->max(),
                            'average' => round($values->average(), 2),
                            'median' => $values->median(),
                        ];
                    }
                    break;

                case 'yes_no':
                    $yes = $answers->where('answer_text', '1')->count();
                    $no = $answers->where('answer_text', '0')->count();
                    $questionAnalytics['data'] = [
                        'yes' => $yes,
                        'no' => $no,
                        'yes_percentage' => $questionAnalytics['total_responses'] > 0
                            ? round(($yes / $questionAnalytics['total_responses']) * 100, 2)
                            : 0,
                    ];
                    break;

                case 'short_text':
                case 'long_text':
                    // For text answers, just provide a sample or word cloud data
                    $questionAnalytics['data'] = [
                        'sample_responses' => $answers->take(5)->pluck('answer_text'),
                        'total_responses' => $answers->count(),
                    ];
                    break;
            }

            $analytics[] = $questionAnalytics;
        }

        return [
            'form' => [
                'id' => $form->id,
                'title' => $form->title,
                'type' => $form->type,
            ],
            'summary' => [
                'total_responses' => $responses->count(),
                'status_breakdown' => [
                    'submitted' => $responses->where('status', 'submitted')->count(),
                    'approved' => $responses->where('status', 'approved')->count(),
                    'rejected' => $responses->where('status', 'rejected')->count(),
                ],
            ],
            'questions' => $analytics,
        ];
    }

    /**
     * Export responses to CSV.
     */
    public function exportResponses(Form $form, ?Campus $campus = null): string
    {
        $query = $form->responses()->with(['student', 'campus', 'answers.question', 'answers.selectedOptions']);

        if ($campus) {
            $query->forCampus($campus->id);
        }

        $responses = $query->get();

        // Build CSV data
        $csv = [];
        $headers = ['Response ID', 'Campus', 'Review', 'Status', 'Submitted At'];

        // Add question headers
        $questions = $form->latestPublishedVersion->questions;
        foreach ($questions as $question) {
            $headers[] = $question->text;
        }

        $csv[] = $headers;

        // Add response rows
        foreach ($responses as $response) {
            $row = [
                $response->id,
                $response->campus->name ?? 'N/A',
                $response->anonymized ? 'Anonymous' : ($response->student->full_name ?? 'N/A'),
                $response->status,
                $response->submitted_at->format('Y-m-d H:i:s'),
            ];

            foreach ($questions as $question) {
                $answer = $response->answers->where('question_id', $question->id)->first();
                $row[] = $answer ? $answer->formatted_value : '';
            }

            $csv[] = $row;
        }

        // Convert to CSV string
        $output = '';
        foreach ($csv as $row) {
            $output .= implode(',', array_map(function ($value) {
                return '"'.str_replace('"', '""', $value).'"';
            }, $row))."\n";
        }

        return $output;
    }
}
