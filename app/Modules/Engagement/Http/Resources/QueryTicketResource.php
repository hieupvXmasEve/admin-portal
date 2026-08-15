<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Resources;

use App\Http\Resources\AnswerResource;
use App\Modules\Engagement\Models\QueryTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class QueryTicketResource extends JsonResource
{
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'topic' => $this->when(
                $this->relationLoaded('topic') && $this->topic,
                fn () => [
                    'id' => $this->topic->id,
                    'title' => $this->topic->title,
                ]
            ),
            'custom_topic_text' => $this->custom_topic_text,
            'status' => $this->status,
            'priority' => $this->priority,
            'assigned_to' => $this->when(
                $this->relationLoaded('assignedTo') && $this->assignedTo,
                fn () => [
                    'id' => $this->assignedTo->id,
                    'name' => $this->assignedTo->name,
                ]
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'closed_at' => $this->closed_at,
            'can_reply' => $this->status !== QueryTicket::STATUS_CLOSED,
            'response' => $this->when(
                $this->relationLoaded('response') && $this->response,
                function () {
                    $response = $this->response;
                    $firstTextAnswer = null;

                    if ($response->relationLoaded('answers')) {
                        $firstTextAnswer = optional(
                            $response->answers->first(fn ($answer) => filled($answer->answer_text))
                        )->answer_text;
                    }

                    return [
                        'id' => $response->id,
                        'submitted_at' => $response->submitted_at?->toISOString(),
                        'content_preview' => $firstTextAnswer ? Str::limit($firstTextAnswer, 160) : null,
                        'form' => $this->when(
                            $response->relationLoaded('form') && $response->form,
                            fn () => [
                                'id' => $response->form->id,
                                'title' => $response->form->title,
                                'type' => $response->form->type,
                            ]
                        ),
                        'campus' => $this->when(
                            $response->relationLoaded('campus') && $response->campus,
                            fn () => [
                                'id' => $response->campus->id,
                                'name' => $response->campus->name,
                            ]
                        ),
                        'student' => $this->when(
                            $response->relationLoaded('student') && $response->student,
                            fn () => [
                                'id' => $response->student->id,
                                'full_name' => $response->student->full_name,
                                'student_id' => $response->student->student_id,
                            ]
                        ),
                        'answers' => AnswerResource::collection($this->whenLoadedResponseAnswers($response)),
                        'attachments' => $this->responseAttachmentSummaries($response),
                    ];
                }
            ),
            'replies' => QueryReplyResource::collection($this->whenLoaded('replies')),
        ];
    }

    protected function whenLoadedResponseAnswers($response)
    {
        return $response->relationLoaded('answers')
            ? $response->answers
            : [];
    }

    /**
     * `attachments` is never a real Eloquent relation on FormResponse — it is
     * a list<UploadRecordSummary> the caller injects via setRelation() from
     * App\Shared\Contracts\Upload\UploadRecordReader::byResponseIds(). Each
     * summary already carries the same fields AttachmentResource used to
     * shape (file_name, size, mime_type, download_url, ...), so mapping
     * ->toArray() directly reproduces the prior JSON shape without routing
     * through AttachmentResource — that resource's download_url calculation
     * requires a real App\Modules\Upload\Models\UploadRecord instance, which
     * a DTO deliberately is not.
     *
     * @return list<array<string, mixed>>
     */
    protected function responseAttachmentSummaries($response): array
    {
        $summaries = $response->attachments ?? [];

        return collect($summaries)->map(fn ($summary) => $summary->toArray())->all();
    }
}
