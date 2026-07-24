<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Actions\Forms;

use App\Models\Answer;
use App\Models\AnswerOption;
use App\Models\Form;
use App\Models\FormResponse;
use App\Models\FormTarget;
use App\Models\QueryTicket;
use App\Models\Student;
use App\Models\StudentFormAssignment;
use App\Modules\Engagement\Support\FormWorkflow;
use App\Shared\Contracts\DomainEvents\DomainEvent;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;
use App\Shared\Contracts\Institution\CampusReferenceReader;
use App\Shared\Contracts\Institution\DepartmentReferenceReader;
use App\Shared\Contracts\Notification\NotificationPayloadFactory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubmitResponseAction
{
    public function __construct(
        private FormWorkflow $formService,
        private DomainEventPublisher $domainEventPublisher,
        private NotificationPayloadFactory $notificationPayloadFactory,
        private CampusReferenceReader $campuses,
        private DepartmentReferenceReader $departments,
    ) {}

    public function execute(Student $student, Form $form, array $data): FormResponse
    {
        $campus = $this->campuses->find((int) ($data['campus_id'] ?? $student->campus_id));
        if (! $campus) {
            throw ValidationException::withMessages(['campus_id' => 'Campus not found.']);
        }

        $target = $this->findEligibleTarget($form, $student, $campus->id, $data);
        if (! $target) {
            throw ValidationException::withMessages(['form' => 'No active target found for this form on your campus.']);
        }

        if (! $this->formService->canStudentSubmitForm($student, $form, $campus->id)) {
            throw ValidationException::withMessages(['form' => 'Submission limit exceeded or form not available.']);
        }

        $processedAnswers = $this->validateAnswers($target, $data);

        return DB::transaction(function () use ($student, $target, $processedAnswers, $data) {
            $response = $this->createResponse($student, $target);
            $this->saveAnswers($response, $processedAnswers);
            $this->updateAssignments($student, $target, $response);

            $queryTicket = null;
            if ($target->form && $target->form->type === 'query') {
                $queryTicket = $this->createQueryTicket($response, $data);
                $this->dispatchQuerySubmittedNotification($queryTicket, $target, $student);
            }

            return $response;
        });
    }

    private function findEligibleTarget(Form $form, Student $student, int $campusId, array $data): ?FormTarget
    {
        $targetQuery = $this->formService->getEligibleTargetsQueryForForm($form, $student, $campusId);

        $scopeType = $data['target_scope_type'] ?? null;
        $scopeId = $data['target_scope_id'] ?? null;

        if ($scopeType) {
            $targetQuery->where('scope_type', $scopeType);
            if ($scopeId) {
                $targetQuery->where('scope_id', $scopeId);
            }
        }

        return $targetQuery->first();
    }

    private function validateAnswers(FormTarget $target, array $data): array
    {
        if (! $target->isActive()) {
            throw ValidationException::withMessages(['form' => 'This form is no longer active.']);
        }

        $formVersion = $target->formVersion;
        $questions = $formVersion->questions;

        $answersData = collect($data['answers'] ?? [])->keyBy('question_id');
        $processedAnswers = [];

        foreach ($questions as $question) {
            $answerItem = $answersData->get($question->id);

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

        return $processedAnswers;
    }

    private function createResponse(Student $student, FormTarget $target): FormResponse
    {
        return FormResponse::create([
            'form_id' => $target->form_id,
            'form_version_id' => $target->form_version_id,
            'form_target_id' => $target->id,
            'campus_id' => $student->campus_id,
            'submitted_by_student_id' => $student->id,
            'target_scope_type' => $target->scope_type,
            'target_scope_id' => $target->scope_id,
            'status' => 'submitted',
            'query_status' => $target->form->type === 'query' ? 'open' : null,
            'submitted_at' => now(),
        ]);
    }

    private function saveAnswers(FormResponse $response, array $processedAnswers): void
    {
        foreach ($processedAnswers as $questionId => $answerItem) {
            $answer = Answer::create([
                'response_id' => $response->id,
                'question_id' => $questionId,
                'answer_text' => $answerItem['answer_text'] ?? null,
                'answer_number' => $answerItem['answer_number'] ?? null,
                'answer_date' => $answerItem['answer_date'] ?? null,
                'comment' => $answerItem['comment'] ?? null,
            ]);

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
    }

    private function updateAssignments(Student $student, FormTarget $target, FormResponse $response): void
    {
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
            StudentFormAssignment::create([
                'student_id' => $student->id,
                'form_target_id' => $target->id,
                'status' => 'completed',
                'response_id' => $response->id,
                'completed_at' => now(),
            ]);
        }

        StudentFormAssignment::where('student_id', $student->id)
            ->where('status', 'not_started')
            ->where('form_target_id', '!=', $target->id)
            ->whereHas('formTarget', function ($q) use ($target) {
                $q->where('form_id', $target->form_id)
                    ->where('scope_type', $target->scope_type)
                    ->where('scope_id', $target->scope_id);
            })
            ->update([
                'status' => 'completed',
                'response_id' => $response->id,
                'completed_at' => now(),
            ]);
    }

    private function createQueryTicket(FormResponse $response, array $data): QueryTicket
    {
        $queryData = $data['query'] ?? [];

        return QueryTicket::create([
            'response_id' => $response->id,
            'topic_id' => $queryData['topic_id'] ?? null,
            'custom_topic_text' => $queryData['custom_topic_text'] ?? null,
            'status' => 'open',
            'priority' => $queryData['priority'] ?? 'normal',
        ]);
    }

    private function dispatchQuerySubmittedNotification(QueryTicket $ticket, FormTarget $target, Student $student): void
    {
        if (! (bool) config('notification.v2_enabled', false)) {
            return;
        }

        $writeMode = (string) config('notification.write_mode', 'off');
        if (! in_array($writeMode, ['dual', 'v2', 'v2_only'], true)) {
            return;
        }

        if ($target->scope_type !== 'department') {
            return;
        }

        $departmentId = (int) $target->scope_id;
        $userIds = $this->departments->activeMemberUserIds($departmentId);
        if (empty($userIds)) {
            return;
        }

        $recipientTargets = array_map(
            fn (int $id) => ['type' => 'user', 'id' => $id],
            $userIds
        );

        $ticket->load(['response.form', 'topic']);

        $topicTitle = $ticket->topic?->title ?? $ticket->custom_topic_text ?? 'General';

        $payload = $this->notificationPayloadFactory->build('query_submitted', [
            'title' => 'New Query: '.$topicTitle,
            'body' => $student->full_name.' ('.$student->student_id.') submitted a new query.',
            'action_type' => 'query.admin_inbox',
            'action_params' => ['id' => $ticket->response_id],
        ]);

        $event = new DomainEvent(
            name: 'query.ticket_submitted',
            deduplicationKey: 'query.ticket_submitted:'.Str::uuid(),
            occurredAt: CarbonImmutable::now(),
            aggregateType: 'query_ticket',
            aggregateId: (string) $ticket->id,
            campusId: $student->campus_id,
            actorUserId: null,
            payload: [
                'type_key' => 'query_submitted',
                'recipient_targets' => $recipientTargets,
                'channels' => ['realtime'],
                'data' => $payload,
            ],
        );

        $this->domainEventPublisher->publishAfterCommit($event);
    }
}
