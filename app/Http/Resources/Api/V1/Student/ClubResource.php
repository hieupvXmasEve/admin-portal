<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'founded_date' => $this->founded_date?->format('Y-m-d'),
            'status' => $this->status,
            'status_display' => $this->getStatusDisplay(),
            'images' => [
                'avatar_url' => $this->avatar_url,
                'thumbnail_url' => $this->thumbnail_url,
                'cover_url' => $this->cover_url,
            ],
            'contact' => [
                'email' => $this->contact_email,
                'phone' => $this->contact_phone,
            ],
            'social_links' => $this->social_links ?? [],
            'achievements' => $this->achievements ?? [],
            'campus' => $this->whenLoaded('campus', function () {
                return [
                    'id' => $this->campus->id,
                    'name' => $this->campus->name,
                    'code' => $this->campus->code,
                ];
            }),
            'president' => $this->whenLoaded('president', function () {
                return $this->president ? [
                    'id' => $this->president->id,
                    'student_id' => $this->president->student_id,
                    'student' => $this->whenLoaded('president.student', function () {
                        return [
                            'id' => $this->president->student->id,
                            'student_code' => $this->president->student->student_code,
                            'full_name' => $this->president->student->full_name,
                            'avatar_url' => $this->president->student->avatar_url,
                        ];
                    }),
                ] : null;
            }),
            'member_count' => $this->whenCounted('activeMembers'),
            'my_membership' => $this->whenLoaded('members', function () use ($request) {
                $user = $request->user();
                if (!$user || !$user->student) {
                    return null;
                }

                $membership = $this->members->where('student_id', $user->student->id)->first();
                return $membership ? [
                    'id' => $membership->id,
                    'role' => $membership->role,
                    'role_display' => $this->getRoleDisplay($membership->role),
                    'status' => $membership->status,
                    'status_display' => $this->getMembershipStatusDisplay($membership->status),
                    'joined_at' => $membership->joined_at?->format('Y-m-d H:i:s'),
                    'can_apply' => false,
                ] : [
                    'id' => null,
                    'role' => null,
                    'role_display' => null,
                    'status' => null,
                    'status_display' => null,
                    'joined_at' => null,
                    'can_apply' => $this->status === 'active',
                ];
            }),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get status display name
     */
    protected function getStatusDisplay(): string
    {
        return match ($this->status) {
            'active' => 'Active',
            'inactive' => 'Inactive',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get role display name
     */
    protected function getRoleDisplay(string $role): string
    {
        return match ($role) {
            'president' => 'President',
            'vice_president' => 'Vice President',
            'secretary' => 'Secretary',
            'treasurer' => 'Treasurer',
            'member' => 'Member',
            default => ucfirst(str_replace('_', ' ', $role)),
        };
    }

    /**
     * Get membership status display name
     */
    protected function getMembershipStatusDisplay(string $status): string
    {
        return match ($status) {
            'active' => 'Active Member',
            'pending' => 'Application Pending',
            'rejected' => 'Application Rejected',
            'left' => 'Left Club',
            'banned' => 'Banned',
            default => ucfirst($status),
        };
    }
}
