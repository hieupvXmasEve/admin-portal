<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
            'role_display' => $this->getRoleDisplay(),
            'status' => $this->status,
            'status_display' => $this->getStatusDisplay(),
            'application_notes' => $this->application_notes,
            'responsibilities' => $this->responsibilities ?? [],
            'participation_score' => $this->participation_score,
            'joined_at' => $this->joined_at?->format('Y-m-d H:i:s'),
            'left_at' => $this->left_at?->format('Y-m-d H:i:s'),
            'last_active_at' => $this->last_active_at?->format('Y-m-d H:i:s'),
            'student' => $this->whenLoaded('student', function () {
                return [
                    'id' => $this->student->id,
                    'student_code' => $this->student->student_id,
                    'full_name' => $this->student->full_name,
                    'first_name' => $this->student->first_name,
                    'last_name' => $this->student->last_name,
                    'email' => $this->student->email,
                    'avatar_url' => $this->student->avatar_url,
                ];
            }),
            'club' => $this->whenLoaded('club', function () {
                return [
                    'id' => $this->club->id,
                    'name' => $this->club->name,
                    'avatar_url' => $this->club->avatar_url,
                ];
            }),
            'approver' => $this->whenLoaded('approver', function () {
                return $this->approver ? [
                    'id' => $this->approver->id,
                    'student_code' => $this->approver->student_id,
                    'full_name' => $this->approver->full_name,
                ] : null;
            }),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get role display name
     */
    protected function getRoleDisplay(): string
    {
        return match ($this->role) {
            'president' => 'President',
            'vice_president' => 'Vice President',
            'secretary' => 'Secretary',
            'treasurer' => 'Treasurer',
            'member' => 'Member',
            default => ucfirst(str_replace('_', ' ', $this->role)),
        };
    }

    /**
     * Get status display name
     */
    protected function getStatusDisplay(): string
    {
        return match ($this->status) {
            'active' => 'Active',
            'pending' => 'Pending',
            'rejected' => 'Rejected',
            'left' => 'Left',
            'banned' => 'Banned',
            default => ucfirst($this->status),
        };
    }
}
