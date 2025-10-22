<?php

declare(strict_types=1);

namespace App\Services\Canvas;

use App\Models\CanvasIntegration;
use App\Models\CanvasCourseMapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CanvasSyncService
{
    public function __construct(
        private CanvasApiService $apiService
    ) {}

    /**
     * Sync courses from Canvas to local database
     */
    public function syncCoursesFromCanvas(CanvasIntegration $integration): array
    {
        $integration->markSyncInProgress();

        try {
            // Get ALL courses first (don't filter by enrollment or published status)
            // Canvas API by default returns courses the authenticated user can view
            $courses = $this->apiService->getCourses($integration, [
                // Remove filters to get all accessible courses
            ]);

            $synced = 0;
            $updated = 0;
            $skipped = 0;

            DB::beginTransaction();

            foreach ($courses as $canvasCourse) {
                $result = $this->syncCourse($integration, $canvasCourse);
                
                match ($result) {
                    'created' => $synced++,
                    'updated' => $updated++,
                    'skipped' => $skipped++,
                    default => null,
                };
            }

            DB::commit();

            $integration->markSyncCompleted();

            $stats = [
                'total' => count($courses),
                'synced' => $synced,
                'updated' => $updated,
                'skipped' => $skipped,
            ];

            Log::info('Canvas course sync completed', [
                'integration_id' => $integration->id,
                'stats' => $stats,
            ]);

            return $stats;
        } catch (\Exception $e) {
            DB::rollBack();
            $integration->markSyncFailed($e->getMessage());

            Log::error('Canvas course sync failed', [
                'integration_id' => $integration->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Sync a single course from Canvas
     */
    private function syncCourse(CanvasIntegration $integration, array $canvasCourse): string
    {
        // Skip courses without ID or name
        if (empty($canvasCourse['id']) || empty($canvasCourse['name'])) {
            return 'skipped';
        }

        $mapping = CanvasCourseMapping::firstOrNew([
            'canvas_integration_id' => $integration->id,
            'canvas_course_id' => (string) $canvasCourse['id'],
        ]);

        $isNew = ! $mapping->exists;

        $mapping->fill([
            'canvas_course_code' => $canvasCourse['course_code'] ?? null,
            'canvas_course_name' => $canvasCourse['name'] ?? null,
            'canvas_data' => $canvasCourse,
            'last_synced_at' => now(),
        ]);

        // Don't change sync_status if already mapped or ignored
        if ($isNew) {
            $mapping->sync_status = 'pending';
        }

        $mapping->save();

        return $isNew ? 'created' : 'updated';
    }

    /**
     * Map a Canvas course to a local CourseOffering
     */
    public function mapCanvasCourse(CanvasCourseMapping $mapping, int $courseOfferingId): void
    {
        DB::beginTransaction();

        try {
            $mapping->mapTo($courseOfferingId);

            Log::info('Canvas course mapped to local course offering', [
                'mapping_id' => $mapping->id,
                'canvas_course_id' => $mapping->canvas_course_id,
                'course_offering_id' => $courseOfferingId,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to map Canvas course', [
                'mapping_id' => $mapping->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Unmap a Canvas course from local CourseOffering
     */
    public function unmapCanvasCourse(CanvasCourseMapping $mapping): void
    {
        DB::beginTransaction();

        try {
            $courseOfferingId = $mapping->course_offering_id;
            $mapping->unmap();

            Log::info('Canvas course unmapped', [
                'mapping_id' => $mapping->id,
                'canvas_course_id' => $mapping->canvas_course_id,
                'previous_course_offering_id' => $courseOfferingId,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to unmap Canvas course', [
                'mapping_id' => $mapping->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Mark a Canvas course as ignored
     */
    public function ignoreCanvasCourse(CanvasCourseMapping $mapping): void
    {
        try {
            $mapping->ignore();

            Log::info('Canvas course marked as ignored', [
                'mapping_id' => $mapping->id,
                'canvas_course_id' => $mapping->canvas_course_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to ignore Canvas course', [
                'mapping_id' => $mapping->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Refresh a single course from Canvas
     */
    public function refreshCourse(CanvasCourseMapping $mapping): void
    {
        $integration = $mapping->canvasIntegration;

        try {
            $canvasCourse = $this->apiService->getCourse($integration, $mapping->canvas_course_id);

            if (! $canvasCourse) {
                throw new \Exception('Course not found in Canvas');
            }

            $mapping->update([
                'canvas_course_code' => $canvasCourse['course_code'] ?? null,
                'canvas_course_name' => $canvasCourse['name'] ?? null,
                'canvas_data' => $canvasCourse,
                'last_synced_at' => now(),
            ]);

            Log::info('Canvas course refreshed', [
                'mapping_id' => $mapping->id,
                'canvas_course_id' => $mapping->canvas_course_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to refresh Canvas course', [
                'mapping_id' => $mapping->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
