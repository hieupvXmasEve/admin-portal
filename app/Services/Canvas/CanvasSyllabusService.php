<?php

declare(strict_types=1);

namespace App\Services\Canvas;

use App\Models\CanvasCourseMapping;

/**
 * Canvas Syllabus Service
 * 
 * NOTE: Syllabus sync functionality has been removed.
 * Canvas Assignment Groups are now synced directly via CanvasAssignmentSyncService.
 * Each Canvas Assignment Group becomes an AssessmentComponent with proper weights.
 */
class CanvasSyllabusService
{
    public function __construct(
        private CanvasApiService $apiService
    ) {}

    /**
     * Get sync summary for a mapped course
     */
    public function getSyncSummary(CanvasCourseMapping $mapping): array
    {
        $courseOffering = $mapping->courseOffering()->with('syllabusTemplate.assessmentComponents')->first();

        if (! $courseOffering) {
            return [
                'can_sync' => false,
                'reason' => 'Course offering not found',
            ];
        }

        $syllabusTemplate = $courseOffering->syllabusTemplate;

        if (! $syllabusTemplate) {
            return [
                'can_sync' => false,
                'reason' => 'No syllabus template assigned',
            ];
        }

        return [
            'can_sync' => true,
            'is_synced' => $courseOffering->is_canvas_synced,
            'syllabus_id' => $syllabusTemplate->id,
            'syllabus_title' => $syllabusTemplate->title,
            'syllabus_version' => $syllabusTemplate->version,
            'last_synced_at' => $courseOffering->canvas_synced_at,
            'assessment_components' => $syllabusTemplate->assessmentComponents->map(fn ($c) => [
                'name' => $c->name,
                'type' => $c->type,
                'weight' => $c->weight,
                'is_canvas_synced' => $c->is_canvas_synced ?? false,
            ]),
        ];
    }
}
