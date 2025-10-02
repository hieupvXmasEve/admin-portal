<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoldTransactionResource extends JsonResource
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
            'student_id' => $this->student_id,
            'amount' => number_format($this->amount, 2),
            'amount_raw' => $this->amount,
            'absolute_amount' => $this->absolute_amount,
            'type' => $this->type,
            'type_label' => ucfirst($this->type),
            'source_type' => $this->source_type,
            'source_type_label' => ucfirst(str_replace('_', ' ', $this->source_type)),
            'source_id' => $this->source_id,
            'notes' => $this->notes,
            'is_positive' => $this->isPositive(),
            'is_negative' => $this->isNegative(),
            'created_at' => $this->created_at,
            'formatted_created_at' => $this->created_at?->format('M j, Y g:i A'),
            'time_ago' => $this->created_at?->diffForHumans(),

            // Include student data when loaded
            'student' => $this->whenLoaded('student', function () {
                return [
                    'id' => $this->student->id,
                    'student_id' => $this->student->student_id,
                    'full_name' => $this->student->full_name,
                    'email' => $this->student->email,
                ];
            }),

            // Transaction display helpers
            'display_amount' => $this->isPositive() ?
                '+' . number_format($this->amount, 2) :
                number_format($this->amount, 2),
            'amount_class' => $this->isPositive() ? 'text-green-600' : 'text-red-600',
            'type_color' => match ($this->type) {
                'earn' => 'green',
                'spend' => 'red',
                'adjust' => 'blue',
                default => 'gray',
            },
        ];
    }
}
