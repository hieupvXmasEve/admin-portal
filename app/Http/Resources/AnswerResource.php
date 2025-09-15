<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnswerResource extends JsonResource
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
            'question_id' => $this->question_id,
            'question' => new QuestionResource($this->whenLoaded('question')),
            'answer_text' => $this->answer_text,
            'answer_number' => $this->answer_number,
            'answer_date' => $this->answer_date?->format('Y-m-d'),
            'comment' => $this->comment,
            'selected_options' => OptionResource::collection($this->whenLoaded('selectedOptions')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'formatted_value' => $this->when(isset($this->formatted_value), $this->formatted_value),
        ];
    }
}