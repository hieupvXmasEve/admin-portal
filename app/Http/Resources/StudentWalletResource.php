<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentWalletResource extends JsonResource
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
            'balance' => (int) $this->balance,
            'balance_raw' => (int) $this->balance,
            'updated_at' => $this->updated_at,
            'formatted_updated_at' => $this->updated_at?->format('M j, Y g:i A'),

            // Include student data when loaded
            'student' => $this->whenLoaded('student', function () {
                return [
                    'id' => $this->student->id,
                    'student_id' => $this->student->student_id,
                    'full_name' => $this->student->full_name,
                    'email' => $this->student->email,
                ];
            }),

            // Include recent transactions when loaded
            'recent_transactions' => GoldTransactionResource::collection(
                $this->whenLoaded('transactions')
            ),
        ];
    }
}
