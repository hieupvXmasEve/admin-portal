<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources\Identity;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $full_name
 * @property string|null $email_snapshot
 * @property string|null $phone
 * @property string $status
 * @property \App\Models\User $user
 */
class ParentResource extends JsonResource
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
            'full_name' => $this->full_name,
            'email' => $this->email_snapshot ?? $this->user->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'user_id' => $this->user_id,
            'children' => $this->whenLoaded('students', function () {
                return $this->students->map(fn($student) => [
                    'id' => $student->id,
                    'student_id' => $student->student_id,
                    'full_name' => $student->full_name,
                    'campus' => $this->when($student->relationLoaded('campus'), fn() => $student->campus->name),
                    'program' => $this->when($student->relationLoaded('program'), fn() => $student->program?->name),
                    'status' => $student->status,
                ]);
            }),
        ];
    }
}
