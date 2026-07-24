<?php

namespace App\Http\Resources;

use App\Modules\Engagement\Http\Resources\FormSectionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
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
            'code' => $this->code,
            'text' => $this->text,
            'type' => $this->type,
            'is_required' => $this->is_required,
            'help_text' => $this->help_text,
            'order_index' => $this->order_index,
            'validation_rules' => $this->validation_json,
            'visibility_conditions' => $this->visibility_condition_json,
            'options' => OptionResource::collection($this->whenLoaded('options')),
            'section_id' => $this->section_id,
            'section' => new FormSectionResource($this->whenLoaded('section')),
        ];
    }
}
