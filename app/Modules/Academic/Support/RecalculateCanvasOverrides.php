<?php

declare(strict_types=1);

namespace App\Modules\Academic\Support;

/**
 * Shared helper for the Recalculate preview (issue 11): projects a Canvas
 * grade sync preview's course totals into the student_id => final_percentage
 * override map CourseCompletionService::finalizeCourse() needs to show what
 * recalculate would produce after the pull, without writing anything.
 *
 * Only the course total is used (not component-level cell changes) because a
 * Canvas mapping only reaches Recalculate once the offering is
 * `is_canvas_synced` (the completion Canvas rule in
 * MarkCourseOfferingCompletedAction), and CanvasGradeSyncService::syncCanvasCourseTotal()
 * is exactly what sets `final_percentage` for a Canvas-synced offering —
 * component-level detail scores never separately feed `final_percentage`
 * there, so the course total is already the correct projection.
 */
trait RecalculateCanvasOverrides
{
    /**
     * @param  ?array{course_totals: array<int, array{student_id: int, new_percentage: float}>}  $canvasPreview
     * @return array<int, float>
     */
    private function courseTotalOverrides(?array $canvasPreview): array
    {
        if ($canvasPreview === null) {
            return [];
        }

        $overrides = [];
        foreach ($canvasPreview['course_totals'] as $total) {
            $overrides[$total['student_id']] = $total['new_percentage'];
        }

        return $overrides;
    }
}
