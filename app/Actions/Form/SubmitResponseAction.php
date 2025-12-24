<?php

namespace App\Actions\Form;

use App\Models\Answer;
use App\Models\AnswerOption;
use App\Models\FormResponse;
use App\Models\FormTarget;
use App\Models\Student;
use App\Models\StudentFormAssignment;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitResponseAction
{
    public function execute(Student $student, FormTarget $target, array $data): FormResponse
    {
        // 1. Check eligibility (Double check)
        // Ideally this logic should be in a separate checking action or reused
        // For now trusting the controller determines target validity or adding basic check
        if (! $target->isActive()) {
            throw ValidationException::withMessages(['form' => 'This form is no longer active.']);
        }

        // 2. Validate Answers
        $formVersion = $target->formVersion;
        $questions = $formVersion->questions;

        $answersData = collect($data['answers'] ?? [])->keyBy('question_id');
        $processedAnswers = [];

        foreach ($questions as $question) {
            $answerItem = $answersData->get($question->id);

            // Determine if the answer is effectively empty
            $hasText = ! empty($answerItem['answer_text']);
            $hasNumber = isset($answerItem['answer_number']) && $answerItem['answer_number'] !== '';
            $hasDate = ! empty($answerItem['answer_date']);
            $hasOptions = ! empty($answerItem['selected_options']);

            $isEmpty = ! $hasText && ! $hasNumber && ! $hasDate && ! $hasOptions;

            if ($question->is_required && $isEmpty) {
                throw ValidationException::withMessages(["question_{$question->id}" => "Question '{$question->text}' is required."]);
            }

            if (! $isEmpty) {
                $processedAnswers[$question->id] = $answerItem;
            }
        }

        return DB::transaction(function () use ($student, $target, $processedAnswers) {
            // 3. Create Response
            $response = FormResponse::create([
                'form_id' => $target->form_id,
                'form_version_id' => $target->form_version_id,
                'campus_id' => $student->campus_id,
                'submitted_by_student_id' => $student->id,
                'target_scope_type' => $target->scope_type,
                'target_scope_id' => $target->scope_id,
                'status' => 'submitted',
                'submitted_at' => now(),
            ]);

            // 4. Save Answers
            foreach ($processedAnswers as $questionId => $answerItem) {
                $answer = Answer::create([
                    'response_id' => $response->id,
                    'question_id' => $questionId,
                    'answer_text' => $answerItem['answer_text'] ?? null,
                    'answer_number' => $answerItem['answer_number'] ?? null,
                    'answer_date' => $answerItem['answer_date'] ?? null,
                    'comment' => $answerItem['comment'] ?? null,
                ]);

                // Handle Options
                if (! empty($answerItem['selected_options'])) {
                    foreach ($answerItem['selected_options'] as $optionData) {
                        AnswerOption::create([
                            'answer_id' => $answer->id,
                            'option_id' => $optionData['option_id'],
                            'free_text' => $optionData['free_text'] ?? null,
                        ]);
                    }
                }
            }

            // 5. Update Assignment Status
            $assignment = StudentFormAssignment::where('student_id', $student->id)
                ->where('form_target_id', $target->id)
                ->first();

            if ($assignment) {
                $assignment->update([
                    'status' => 'completed',
                    'response_id' => $response->id,
                    'completed_at' => now(),
                ]);
            } else {
                // If no assignment existed (voluntary form), we create one to track
                StudentFormAssignment::create([
                    'student_id' => $student->id,
                    'form_target_id' => $target->id,
                    'status' => 'completed',
                    'response_id' => $response->id,
                    'completed_at' => now(),
                ]);
            }

            if ($target->form && $target->form->type === 'query') {
                \App\Models\QueryTicket::create([
                    'response_id' => $response->id,
                    'status' => 'open',
                    'priority' => 'normal',
                ]);
            }

            return $response;
        });
    }
}
