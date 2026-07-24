<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormResponseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'form' => new FormResource($this->whenLoaded('form')),
            'form_version' => new FormVersionResource($this->whenLoaded('formVersion')),
            'campus' => [
                'id' => $this->campus_id,
                'name' => $this->whenLoaded('campus', fn () => $this->campus->name),
            ],
            'target_scope' => [
                'type' => $this->target_scope_type,
                'id' => $this->target_scope_id,
            ],
            'submitter' => $this->when(! $this->anonymized, [
                'id' => $this->submitted_by_student_id,
                'name' => $this->whenLoaded('student', fn () => $this->student->full_name),
                'student_id' => $this->whenLoaded('student', fn () => $this->student->student_id),
            ]),
            'anonymized' => $this->anonymized,
            'status' => $this->status,
            'review' => $this->when($this->reviewed_by_user_id, [
                'reviewer_id' => $this->reviewed_by_user_id,
                'reviewer_name' => $this->whenLoaded('reviewer', fn () => $this->reviewer->name),
                'reviewed_at' => $this->reviewed_at?->toISOString(),
                'notes' => $this->review_notes,
            ]),
            'origin' => $this->origin,
            'submitted_at' => $this->submitted_at->toISOString(),
            'answers' => AnswerResource::collection($this->whenLoaded('answers')),
            'query_ticket' => new QueryTicketResource($this->whenLoaded('queryTicket')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
