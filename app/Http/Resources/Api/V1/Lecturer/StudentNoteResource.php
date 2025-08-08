<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Lecturer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentNoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_code' => $this->student_code,
            'course_offering_id' => $this->course_offering_id,
            'note_type' => $this->note_type,
            'title' => $this->title,
            'content' => $this->content,
            'is_private' => $this->is_private,
            'is_alert' => $this->is_alert,
            'priority' => $this->priority,

            // Display Information
            'display_info' => [
                'type_label' => $this->getTypeLabel(),
                'priority_label' => $this->getPriorityLabel(),
                'priority_color' => $this->getPriorityColor(),
                'visibility_label' => $this->is_private ? 'Private' : 'Shared',
                'alert_indicator' => $this->is_alert,
            ],

            // Student Information (when loaded)
            'student' => $this->whenLoaded('student', function () {
                return [
                    'id' => $this->student->id,
                    'student_number' => $this->student->student_number,
                    'full_name' => $this->student->full_name,
                    'email' => $this->student->email,
                ];
            }),

            // Course Information (when loaded)
            'course' => $this->when($this->course_offering_id, function () {
                return $this->whenLoaded('courseOffering', function () {
                    return [
                        'id' => $this->courseOffering->id,
                        'unit_code' => $this->courseOffering->curriculumUnit->unit_code,
                        'unit_name' => $this->courseOffering->curriculumUnit->unit_name,
                        'section_code' => $this->courseOffering->section_code,
                    ];
                });
            }),

            // Lecturer Information (when loaded)
            'lecturer' => $this->whenLoaded('lecture', function () {
                return [
                    'id' => $this->lecture->id,
                    'full_name' => $this->lecture->full_name,
                    'title' => $this->lecture->title,
                ];
            }),

            // Metadata
            'metadata' => [
                'word_count' => str_word_count($this->content),
                'character_count' => strlen($this->content),
                'is_recent' => $this->created_at->gt(now()->subDays(7)),
                'age_in_days' => $this->created_at->diffInDays(now()),
                'last_modified' => $this->updated_at->diffForHumans(),
            ],

            // Actions Available
            'available_actions' => [
                'can_edit' => $this->canEdit(),
                'can_delete' => $this->canDelete(),
                'can_share' => $this->canShare(),
                'can_convert_to_alert' => ! $this->is_alert,
                'can_change_priority' => true,
            ],

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Get note type label
     */
    protected function getTypeLabel(): string
    {
        return match ($this->note_type) {
            'general' => 'General Note',
            'academic' => 'Academic Note',
            'behavioral' => 'Behavioral Note',
            'attendance' => 'Attendance Note',
            'performance' => 'Performance Note',
            'personal' => 'Personal Note',
            'alert' => 'Alert Note',
            default => 'Note',
        };
    }

    /**
     * Get priority label
     */
    protected function getPriorityLabel(): string
    {
        return match ($this->priority) {
            'low' => 'Low Priority',
            'medium' => 'Medium Priority',
            'high' => 'High Priority',
            'urgent' => 'Urgent',
            default => 'Unknown Priority',
        };
    }

    /**
     * Get priority color for UI
     */
    protected function getPriorityColor(): string
    {
        return match ($this->priority) {
            'low' => 'green',
            'medium' => 'yellow',
            'high' => 'orange',
            'urgent' => 'red',
            default => 'gray',
        };
    }

    /**
     * Check if note can be edited
     */
    protected function canEdit(): bool
    {
        // Notes can be edited by the lecturer who created them
        // Additional business rules can be added here
        return true;
    }

    /**
     * Check if note can be deleted
     */
    protected function canDelete(): bool
    {
        // Notes can be deleted by the lecturer who created them
        // May want to restrict deletion of certain types or old notes
        return ! $this->is_alert || $this->created_at->gt(now()->subDays(30));
    }

    /**
     * Check if note can be shared
     */
    protected function canShare(): bool
    {
        // Private notes can potentially be made public
        // Alert notes might have different sharing rules
        return $this->is_private && ! $this->is_alert;
    }
}
