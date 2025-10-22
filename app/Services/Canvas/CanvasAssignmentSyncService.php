<?php

declare(strict_types=1);

namespace App\Services\Canvas;

use App\Models\AssessmentComponent;
use App\Models\AssessmentComponentDetail;
use App\Models\CanvasCourseMapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CanvasAssignmentSyncService
{
    public function __construct(
        private CanvasApiService $apiService
    ) {}

    /**
     * Sync Canvas assignments to assessment_component_details
     *
     * @param  array  $selectedGroupIds  Array of Canvas assignment group IDs to sync
     */
    public function syncAssignments(CanvasCourseMapping $mapping, array $selectedGroupIds = []): array
    {
        return DB::transaction(function () use ($mapping, $selectedGroupIds) {
            $courseOffering = $mapping->courseOffering;

            if (! $courseOffering) {
                throw new \Exception('Course offering not found');
            }

            // Check if first time sync or update
            $isFirstSync = ! $courseOffering->is_canvas_synced;

            if ($isFirstSync) {
                // First time: Create new syllabus template
                $syllabusTemplate = $this->createCanvasSyllabusTemplate($courseOffering);

                // Update course offering
                $courseOffering->update([
                    'syllabus_template_id' => $syllabusTemplate->id,
                    'is_canvas_synced' => true,
                    'canvas_synced_at' => now(),
                ]);

                Log::info('Created new Canvas syllabus template', [
                    'course_offering_id' => $courseOffering->id,
                    'syllabus_template_id' => $syllabusTemplate->id,
                ]);
            } else {
                // Already synced: Use existing template
                if (! $courseOffering->syllabusTemplate) {
                    throw new \Exception('Course offering is marked as Canvas synced but has no syllabus template');
                }

                $syllabusTemplate = $courseOffering->syllabusTemplate;

                // Update sync timestamp
                $courseOffering->update([
                    'canvas_synced_at' => now(),
                ]);
            }

            // Validate selected groups if provided
            if (! empty($selectedGroupIds)) {
                $this->validateSelectedGroups($syllabusTemplate, $selectedGroupIds, $mapping);
            }

            // Fetch assignment groups from Canvas (include assignments)
            $canvasGroups = $this->apiService->getAssignmentGroups(
                $mapping->canvasIntegration,
                $mapping->canvas_course_id,
                ['include[]' => 'assignments'] // Canvas API expects include[]=assignments
            );

            // Filter to only selected groups if specified
            if (! empty($selectedGroupIds)) {
                $canvasGroups = array_filter($canvasGroups, function ($group) use ($selectedGroupIds) {
                    return in_array((string) $group['id'], $selectedGroupIds);
                });
            }

            Log::info('Syncing Canvas assignment groups and assignments', [
                'canvas_course_id' => $mapping->canvas_course_id,
                'total_groups' => count($canvasGroups),
                'selected_groups' => $selectedGroupIds,
            ]);

            // Remove ALL existing assessment components (including attendance) before syncing
            // Since we're now syncing 100% from Canvas
            if (! empty($selectedGroupIds)) {
                $existingComponents = AssessmentComponent::where('syllabus_template_id', $syllabusTemplate->id)->get();

                foreach ($existingComponents as $component) {
                    // Delete all assignments in this component
                    $component->details()->delete();
                    // Delete the component itself
                    $component->delete();

                    Log::info('Removed existing component before Canvas sync', [
                        'component_id' => $component->id,
                        'component_name' => $component->name,
                        'component_type' => $component->type,
                        'is_canvas_synced' => $component->is_canvas_synced,
                    ]);
                }

                $removedComponents = $existingComponents;
            }

            $synced = 0;
            $created = 0;
            $updated = 0;
            $groupsCreated = 0;
            $groupsRemoved = count($removedComponents ?? []);

            foreach ($canvasGroups as $group) {
                // Create or update AssessmentComponent for each Canvas Assignment Group
                $component = AssessmentComponent::updateOrCreate(
                    [
                        'syllabus_template_id' => $syllabusTemplate->id,
                        'canvas_assignment_group_id' => (string) $group['id'],
                    ],
                    [
                        'name' => $group['name'],
                        'type' => 'online_activity',
                        'weight' => $group['group_weight'] ?? 0,
                        'canvas_group_weight' => $group['group_weight'] ?? 0,
                        'is_canvas_synced' => true,
                        'is_published' => true,
                    ]
                );

                if ($component->wasRecentlyCreated) {
                    $groupsCreated++;
                }

                // Sync assignments in this group
                $assignments = $group['assignments'] ?? [];

                foreach ($assignments as $assignment) {
                    // Skip unpublished assignments
                    if (! ($assignment['published'] ?? true)) {
                        continue;
                    }

                    $data = [
                        'assessment_component_id' => $component->id,
                        'name' => $assignment['name'],
                        'description' => null, // Skip description (too long, not needed)
                        'max_points' => $assignment['points_possible'] ?? 0,
                        'due_date' => isset($assignment['due_at']) ? date('Y-m-d', strtotime($assignment['due_at'])) : null,
                        'grading_type' => $assignment['grading_type'] ?? 'points',
                        'submission_types' => $assignment['submission_types'] ?? [],
                        'canvas_synced_at' => now(),
                    ];

                    // Check if already exists
                    $existing = AssessmentComponentDetail::where('canvas_assignment_id', $assignment['id'])->first();

                    if ($existing) {
                        $existing->update($data);
                        $updated++;
                    } else {
                        AssessmentComponentDetail::create(array_merge($data, [
                            'canvas_assignment_id' => (string) $assignment['id'],
                        ]));
                        $created++;
                    }

                    $synced++;
                }
            }

            Log::info('Canvas assignment groups and assignments synced successfully', [
                'groups_created' => $groupsCreated,
                'groups_removed' => $groupsRemoved,
                'assignments_synced' => $synced,
                'assignments_created' => $created,
                'assignments_updated' => $updated,
            ]);

            return [
                'success' => true,
                'is_first_sync' => $isFirstSync,
                'groups_created' => $groupsCreated,
                'groups_removed' => $groupsRemoved,
                'total_synced' => $synced,
                'created' => $created,
                'updated' => $updated,
            ];
        });
    }

    /**
     * Create new Canvas syllabus template by cloning existing one
     * Adds default Attendance component
     */
    private function createCanvasSyllabusTemplate($courseOffering)
    {
        // Get existing syllabus template
        $originalSyllabus = $courseOffering->syllabusTemplate;

        if (! $originalSyllabus) {
            throw new \Exception('Course offering must have a syllabus template before Canvas sync');
        }

        // Clone the original syllabus
        $syllabusTemplate = $originalSyllabus->replicate();
        $syllabusTemplate->title = $originalSyllabus->title.' (Canvas Sync)';
        $syllabusTemplate->version = 'canvas-'.now()->format('Y-m-d');
        $syllabusTemplate->description = ($originalSyllabus->description ?? '')."\n\nSynced with Canvas LMS.";
        $syllabusTemplate->save();

        Log::info('Cloned syllabus template for Canvas sync', [
            'original_id' => $originalSyllabus->id,
            'new_id' => $syllabusTemplate->id,
            'course_offering_id' => $courseOffering->id,
        ]);

        return $syllabusTemplate;
    }

    /**
     * Validate that selected groups' total weight equals 100%
     */
    private function validateSelectedGroups($syllabusTemplate, array $selectedGroupIds, CanvasCourseMapping $mapping): void
    {
        // Fetch Canvas groups to get their weights
        $canvasGroups = $this->apiService->getAssignmentGroups(
            $mapping->canvasIntegration,
            $mapping->canvas_course_id
        );

        // Calculate total weight of selected groups
        $selectedWeight = 0;
        foreach ($canvasGroups as $group) {
            if (in_array((string) $group['id'], $selectedGroupIds)) {
                $selectedWeight += $group['group_weight'] ?? 0;
            }
        }

        // Validate - must equal 100%
        if (abs($selectedWeight - 100) > 0.01) { // Allow 0.01% tolerance for floating point
            throw new \Exception(
                "Selected assignment groups total weight ({$selectedWeight}%) must equal 100%"
            );
        }
    }

    /**
     * Get detailed sync summary for Canvas assignments with group-level details
     */
    public function getDetailedSyncSummary(CanvasCourseMapping $mapping): array
    {
        $courseOffering = $mapping->courseOffering;

        if (! $courseOffering || ! $courseOffering->syllabusTemplate) {
            return [
                'can_sync' => false,
                'error' => 'Course offering or syllabus not found',
            ];
        }

        $syllabusTemplate = $courseOffering->syllabusTemplate;

        try {
            // Fetch Canvas groups with assignments
            $canvasGroups = $this->apiService->getAssignmentGroups(
                $mapping->canvasIntegration,
                $mapping->canvas_course_id,
                ['include[]' => 'assignments']
            );

            // Find existing synced components
            $existingComponents = AssessmentComponent::where('syllabus_template_id', $syllabusTemplate->id)
                ->where('is_canvas_synced', true)
                ->get()
                ->keyBy('canvas_assignment_group_id');

            // Map groups with details
            $groups = collect($canvasGroups)->map(function ($group) use ($existingComponents) {
                $groupId = (string) $group['id'];
                $existingComponent = $existingComponents->get($groupId);

                // Get published assignments only
                $assignments = collect($group['assignments'] ?? [])
                    ->filter(fn ($a) => $a['published'] ?? true)
                    ->map(fn ($a) => [
                        'id' => $a['id'],
                        'name' => $a['name'],
                        'points_possible' => $a['points_possible'] ?? 0,
                        'due_at' => $a['due_at'] ?? null,
                    ])
                    ->values()
                    ->toArray();

                return [
                    'id' => $groupId,
                    'name' => $group['name'],
                    'weight' => $group['group_weight'] ?? 0,
                    'assignments_count' => count($assignments),
                    'assignments' => $assignments,
                    'is_currently_synced' => $existingComponent !== null,
                ];
            })->values()->toArray();

            return [
                'can_sync' => true,
                'total_weight_required' => 100,
                'groups' => $groups,
            ];
        } catch (\Exception $e) {
            return [
                'can_sync' => false,
                'error' => 'Failed to fetch Canvas assignment groups: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get sync summary for Canvas assignments (legacy method)
     */
    public function getSyncSummary(CanvasCourseMapping $mapping): array
    {
        $courseOffering = $mapping->courseOffering;

        if (! $courseOffering || ! $courseOffering->syllabusTemplate) {
            return [
                'can_sync' => false,
                'error' => 'Course offering or syllabus not found',
            ];
        }

        $syllabusTemplate = $courseOffering->syllabusTemplate;

        // Find all Canvas-synced components (Assignment Groups)
        $canvasComponents = AssessmentComponent::where('syllabus_template_id', $syllabusTemplate->id)
            ->where('is_canvas_synced', true)
            ->get();

        // Count existing assignments across all Canvas components
        $localAssignmentsCount = AssessmentComponentDetail::whereIn(
            'assessment_component_id',
            $canvasComponents->pluck('id')
        )
            ->whereNotNull('canvas_assignment_id')
            ->count();

        $lastSyncedAt = AssessmentComponentDetail::whereIn(
            'assessment_component_id',
            $canvasComponents->pluck('id')
        )
            ->whereNotNull('canvas_synced_at')
            ->max('canvas_synced_at');

        try {
            // Get Canvas assignment groups count
            $canvasGroups = $this->apiService->getAssignmentGroups(
                $mapping->canvasIntegration,
                $mapping->canvas_course_id
            );
            $canvasGroupsCount = count($canvasGroups);
        } catch (\Exception $e) {
            return [
                'can_sync' => false,
                'error' => 'Failed to fetch Canvas assignment groups: '.$e->getMessage(),
            ];
        }

        return [
            'can_sync' => true,
            'canvas_groups_count' => $canvasGroupsCount,
            'local_components_count' => $canvasComponents->count(),
            'local_assignments_count' => $localAssignmentsCount,
            'needs_sync' => $canvasComponents->count() !== $canvasGroupsCount,
            'last_synced_at' => $lastSyncedAt,
            'components' => $canvasComponents->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'weight' => $c->weight,
                'assignments_count' => $c->details()->count(),
            ]),
        ];
    }
}
