<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\ModuleProgressResource;
use App\Models\Module;
use App\Models\Student;
use App\Services\ModuleProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ModuleController extends Controller
{
    public function __construct(
        private ModuleProgressService $moduleProgressService
    ) {}

    /**
     * Get all modules for authenticated student's curriculum.
     * 
     * GET /api/v1/student/modules
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        if (! $student->curriculum_version_id) {
            return response()->json([
                'success' => false,
                'message' => 'Student has no assigned curriculum',
                'data' => [],
            ], 404);
        }

        // Get curriculum version with modules
        $curriculumVersion = $student->curriculumVersion()
            ->with([
                'curriculumModules.module.units',
                'program',
                'specialization',
            ])
            ->first();

        if (! $curriculumVersion || ! $curriculumVersion->curriculumModules) {
            return response()->json([
                'success' => true,
                'message' => 'No modules in curriculum',
                'data' => [
                    'curriculum_version' => [
                        'id' => $curriculumVersion?->id,
                        'version_code' => $curriculumVersion?->version_code,
                        'program' => $curriculumVersion?->program,
                    ],
                    'modules' => [],
                    'statistics' => [
                        'total_modules' => 0,
                        'completed_modules' => 0,
                        'in_progress_modules' => 0,
                        'not_started_modules' => 0,
                        'total_credits' => 0,
                        'completed_credits' => 0,
                        'module_gpa' => null,
                    ],
                ],
            ]);
        }

        // Calculate progress for each module
        $modulesWithProgress = $curriculumVersion->curriculumModules->map(function ($cm) use ($student) {
            $progress = $this->moduleProgressService->calculateModuleProgress($cm->module, $student);

            return [
                'curriculum_module_id' => $cm->id,
                'module_id' => $cm->module_id,
                'module' => [
                    'id' => $cm->module->id,
                    'code' => $cm->module->code,
                    'name' => $cm->module->name,
                    'total_credits' => (float) $cm->module->total_credits,
                    'grading_type' => $cm->module->grading_type,
                ],
                'year_level' => $cm->year_level,
                'semester_number' => $cm->semester_number,
                'is_required' => $cm->is_required,
                'group_name' => $cm->group_name,
                'order' => $cm->order,
                'progress' => [
                    'grade' => $progress['grade'],
                    'status' => $progress['status'],
                    'completion' => $progress['completion'],
                    'completed_count' => $progress['completed_count'],
                    'total_count' => $progress['total_count'],
                ],
                'prerequisites_met' => $this->checkPrerequisites($cm->module, $student),
            ];
        })->sortBy('order')->values();

        // Calculate overall statistics
        $statistics = $this->calculateStatistics($modulesWithProgress, $student);

        return response()->json([
            'success' => true,
            'data' => [
                'curriculum_version' => [
                    'id' => $curriculumVersion->id,
                    'version_code' => $curriculumVersion->version_code,
                    'program' => $curriculumVersion->program,
                    'specialization' => $curriculumVersion->specialization,
                ],
                'modules' => $modulesWithProgress,
                'statistics' => $statistics,
            ],
        ]);
    }

    /**
     * Get detailed progress for a specific module.
     * 
     * GET /api/v1/student/modules/{module}
     */
    public function show(Request $request, Module $module): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        // Check if module is in student's curriculum
        $curriculumModule = $student->curriculumVersion
            ?->curriculumModules()
            ->where('module_id', $module->id)
            ->first();

        if (! $curriculumModule) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found in your curriculum',
            ], 404);
        }

        // Calculate detailed progress
        $progress = $this->moduleProgressService->calculateModuleProgress($module, $student);

        return response()->json([
            'success' => true,
            'data' => [
                'module' => [
                    'id' => $module->id,
                    'code' => $module->code,
                    'name' => $module->name,
                    'description' => $module->description,
                    'total_credits' => (float) $module->total_credits,
                    'grading_type' => $module->grading_type,
                ],
                'curriculum_info' => [
                    'year_level' => $curriculumModule->year_level,
                    'semester_number' => $curriculumModule->semester_number,
                    'is_required' => $curriculumModule->is_required,
                    'group_name' => $curriculumModule->group_name,
                    'note' => $curriculumModule->note,
                ],
                'progress' => [
                    'grade' => $progress['grade'],
                    'status' => $progress['status'],
                    'completion' => $progress['completion'],
                    'completed_count' => $progress['completed_count'],
                    'total_count' => $progress['total_count'],
                    'sub_units' => $progress['sub_units'],
                ],
                'prerequisites' => [
                    'module' => $module->prerequisiteModule,
                    'met' => $this->checkPrerequisites($module, $student),
                ],
                'required_for' => $module->dependentModules->map(fn ($m) => [
                    'id' => $m->id,
                    'code' => $m->code,
                    'name' => $m->name,
                ]),
            ],
        ]);
    }

    /**
     * Get module roadmap with prerequisites visualization.
     * 
     * GET /api/v1/student/modules/roadmap
     */
    public function roadmap(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        if (! $student->curriculum_version_id) {
            return response()->json([
                'success' => false,
                'message' => 'Student has no assigned curriculum',
            ], 404);
        }

        $curriculumVersion = $student->curriculumVersion()
            ->with([
                'curriculumModules.module.prerequisiteModule',
                'curriculumModules.module.dependentModules',
                'curriculumModules.module.units',
            ])
            ->first();

        if (! $curriculumVersion) {
            return response()->json([
                'success' => false,
                'message' => 'Curriculum version not found',
            ], 404);
        }

        // Group modules by year and semester
        $roadmap = $curriculumVersion->curriculumModules
            ->groupBy(fn ($cm) => "Y{$cm->year_level}S{$cm->semester_number}")
            ->map(function ($modules, $period) use ($student) {
                return [
                    'period' => $period,
                    'year_level' => $modules->first()->year_level,
                    'semester_number' => $modules->first()->semester_number,
                    'modules' => $modules->map(function ($cm) use ($student) {
                        $progress = $this->moduleProgressService->calculateModuleProgress($cm->module, $student);

                        return [
                            'id' => $cm->module->id,
                            'code' => $cm->module->code,
                            'name' => $cm->module->name,
                            'credits' => (float) $cm->module->total_credits,
                            'is_required' => $cm->is_required,
                            'status' => $progress['status'],
                            'grade' => $progress['grade'],
                            'prerequisites' => $cm->module->prerequisiteModule ? [
                                'id' => $cm->module->prerequisiteModule->id,
                                'code' => $cm->module->prerequisiteModule->code,
                                'met' => $this->checkPrerequisites($cm->module, $student),
                            ] : null,
                            'prerequisites_met' => $this->checkPrerequisites($cm->module, $student),
                        ];
                    })->values(),
                ];
            })
            ->sortBy(fn ($period) => $period['year_level'] * 10 + $period['semester_number'])
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'curriculum_version' => [
                    'id' => $curriculumVersion->id,
                    'version_code' => $curriculumVersion->version_code,
                ],
                'roadmap' => $roadmap,
            ],
        ]);
    }

    /**
     * Get current module progress for dashboard widget.
     * 
     * GET /api/v1/student/modules/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        /** @var Student $student */
        $student = $request->user();

        if (! $student->curriculum_version_id) {
            return response()->json([
                'success' => true,
                'data' => [
                    'current_modules' => [],
                    'statistics' => null,
                ],
            ]);
        }

        $curriculumVersion = $student->curriculumVersion()
            ->with(['curriculumModules.module.units'])
            ->first();

        if (! $curriculumVersion) {
            return response()->json([
                'success' => true,
                'data' => [
                    'current_modules' => [],
                    'statistics' => null,
                ],
            ]);
        }

        // Get in-progress modules
        $currentModules = $curriculumVersion->curriculumModules
            ->map(function ($cm) use ($student) {
                $progress = $this->moduleProgressService->calculateModuleProgress($cm->module, $student);
                return [
                    'module' => $cm->module,
                    'progress' => $progress,
                ];
            })
            ->filter(fn ($item) => $item['progress']['status'] === 'in_progress')
            ->take(3)
            ->map(function ($item) {
                return [
                    'id' => $item['module']->id,
                    'code' => $item['module']->code,
                    'name' => $item['module']->name,
                    'grade' => $item['progress']['grade'],
                    'completion' => $item['progress']['completion'],
                    'status' => $item['progress']['status'],
                ];
            })
            ->values();

        // Calculate quick stats
        $allModules = $curriculumVersion->curriculumModules->map(function ($cm) use ($student) {
            return $this->moduleProgressService->calculateModuleProgress($cm->module, $student);
        });

        $statistics = [
            'total_modules' => $allModules->count(),
            'completed_modules' => $allModules->where('status', 'completed')->count(),
            'in_progress_modules' => $allModules->where('status', 'in_progress')->count(),
            'completion_percentage' => $allModules->count() > 0
                ? round(($allModules->where('status', 'completed')->count() / $allModules->count()) * 100, 1)
                : 0,
            'module_gpa' => $this->calculateModuleGPA($allModules),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'current_modules' => $currentModules,
                'statistics' => $statistics,
            ],
        ]);
    }

    /**
     * Check if student has met all prerequisites for a module.
     */
    private function checkPrerequisites(Module $module, Student $student): bool
    {
        if (! $module->prerequisite_module_id) {
            return true;
        }

        $prerequisiteModule = $module->prerequisiteModule;
        if (! $prerequisiteModule) {
            return true;
        }

        $progress = $this->moduleProgressService->calculateModuleProgress($prerequisiteModule, $student);

        return $progress['status'] === 'completed';
    }

    /**
     * Calculate overall statistics from module progress data.
     */
    private function calculateStatistics($modulesWithProgress, Student $student): array
    {
        $totalModules = $modulesWithProgress->count();
        $completedModules = $modulesWithProgress->where('progress.status', 'completed')->count();
        $inProgressModules = $modulesWithProgress->where('progress.status', 'in_progress')->count();
        $notStartedModules = $modulesWithProgress->where('progress.status', 'not_enrolled')->count();

        $totalCredits = $modulesWithProgress->sum('module.total_credits');
        $completedCredits = $modulesWithProgress
            ->where('progress.status', 'completed')
            ->sum('module.total_credits');

        // Calculate module-based GPA
        $gradedModules = $modulesWithProgress->filter(function ($m) {
            return $m['progress']['status'] === 'completed'
                && $m['progress']['grade'] !== null
                && $m['module']['grading_type'] === 'grade';
        });

        $moduleGPA = $gradedModules->isNotEmpty()
            ? round($gradedModules->avg('progress.grade'), 2)
            : null;

        return [
            'total_modules' => $totalModules,
            'completed_modules' => $completedModules,
            'in_progress_modules' => $inProgressModules,
            'not_started_modules' => $notStartedModules,
            'completion_percentage' => $totalModules > 0
                ? round(($completedModules / $totalModules) * 100, 1)
                : 0,
            'total_credits' => (float) $totalCredits,
            'completed_credits' => (float) $completedCredits,
            'credits_percentage' => $totalCredits > 0
                ? round(($completedCredits / $totalCredits) * 100, 1)
                : 0,
            'module_gpa' => $moduleGPA,
        ];
    }

    /**
     * Calculate module-based GPA.
     */
    private function calculateModuleGPA($moduleProgresses): ?float
    {
        $gradedModules = $moduleProgresses->filter(function ($progress) {
            return $progress['status'] === 'completed'
                && $progress['grade'] !== null;
        });

        if ($gradedModules->isEmpty()) {
            return null;
        }

        return round($gradedModules->avg('grade'), 2);
    }
}
