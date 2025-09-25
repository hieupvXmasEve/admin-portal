<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubManagementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'club' => new ClubResource($this->resource['club']),
            'members' => ClubMemberResource::collection($this->resource['members']),
            'pending_applications' => ClubMemberResource::collection($this->resource['pending_applications']),
            'statistics' => [
                'total_members' => $this->resource['statistics']['total_members'],
                'pending_applications' => $this->resource['statistics']['pending_applications'],
                'officers' => $this->resource['statistics']['officers'],
            ],
            'recent_activities' => $this->formatRecentActivities($this->resource['recent_activities']),
        ];
    }

    /**
     * Format recent activities
     */
    protected function formatRecentActivities($activities): array
    {
        return $activities->map(function ($activity) {
            return [
                'id' => $activity->id,
                'old_role' => $activity->old_role,
                'old_role_display' => $this->getRoleDisplay($activity->old_role),
                'new_role' => $activity->new_role,
                'new_role_display' => $this->getRoleDisplay($activity->new_role),
                'change_reason' => $activity->change_reason,
                'started_at' => $activity->started_at->format('Y-m-d H:i:s'),
                'ended_at' => $activity->ended_at?->format('Y-m-d H:i:s'),
                'member' => [
                    'id' => $activity->clubMember->id,
                    'student' => [
                        'id' => $activity->clubMember->student->id,
                        'student_code' => $activity->clubMember->student->student_code,
                        'full_name' => $activity->clubMember->student->full_name,
                    ],
                ],
                'changed_by' => $activity->changedBy ? [
                    'id' => $activity->changedBy->id,
                    'student_code' => $activity->changedBy->student_code,
                    'full_name' => $activity->changedBy->full_name,
                ] : null,
            ];
        })->toArray();
    }

    /**
     * Get role display name
     */
    protected function getRoleDisplay(?string $role): ?string
    {
        if (!$role) {
            return null;
        }

        return match ($role) {
            'president' => 'President',
            'vice_president' => 'Vice President',
            'secretary' => 'Secretary',
            'treasurer' => 'Treasurer',
            'member' => 'Member',
            default => ucfirst(str_replace('_', ' ', $role)),
        };
    }
}
