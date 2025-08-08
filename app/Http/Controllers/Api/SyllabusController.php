<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Syllabus;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SyllabusController extends Controller
{
    /**
     * Get syllabus for a specific unit
     */
    public function index(Unit $unit, Request $request): JsonResponse
    {
        $syllabus = $unit->syllabus()
            ->with(['curriculumUnit.semester', 'curriculumUnit.curriculumVersion.specialization', 'curriculumUnit.curriculumVersion.program', 'assessmentComponents.details'])
            ->when($request->boolean('active_only'), function ($query) {
                $query->where('is_active', true);
            })
            ->orderBy('is_active', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $syllabus->each(function ($syllabus) {
            $syllabus->total_assessment_weight = $syllabus->assessmentComponents->sum('weight');
        });

        return response()->json([
            'success' => true,
            'data' => [
                'unit' => [
                    'id' => $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                    'credit_points' => $unit->credit_points,
                ],
                'syllabus' => $syllabus->map(function ($syllabus) {
                    return [
                        'id' => $syllabus->id,
                        'version' => $syllabus->version,
                        'description' => $syllabus->description,
                        'total_hours' => $syllabus->total_hours,
                        'hours_per_session' => $syllabus->hours_per_session,
                        'is_active' => $syllabus->is_active,
                        'curriculum_unit' => [
                            'id' => $syllabus->curriculumUnit->id,
                            'semester' => [
                                'id' => $syllabus->curriculumUnit->semester->id,
                                'name' => $syllabus->curriculumUnit->semester->name,
                            ],
                            'specialization' => [
                                'id' => $syllabus->curriculumUnit->curriculumVersion->specialization->id,
                                'name' => $syllabus->curriculumUnit->curriculumVersion->specialization->name,
                            ],
                            'program' => [
                                'id' => $syllabus->curriculumUnit->curriculumVersion->program->id,
                                'name' => $syllabus->curriculumUnit->curriculumVersion->program->name,
                            ],
                        ],
                        'assessment_components' => $syllabus->assessmentComponents->map(function ($component) {
                            return [
                                'id' => $component->id,
                                'name' => $component->name,
                                'weight' => $component->weight,
                                'type' => $component->type,
                                'is_required_to_sit_final_exam' => $component->is_required_to_sit_final_exam,
                                'details' => $component->details->map(function ($detail) {
                                    return [
                                        'id' => $detail->id,
                                        'name' => $detail->name,
                                        'weight' => $detail->weight,
                                    ];
                                }),
                            ];
                        }),
                        'total_assessment_weight' => $syllabus->total_assessment_weight,
                        'created_at' => $syllabus->created_at->toISOString(),
                        'updated_at' => $syllabus->updated_at->toISOString(),
                    ];
                }),
            ],
        ]);
    }

    /**
     * Get a specific syllabus
     */
    public function show(Unit $unit, Syllabus $syllabus): JsonResponse
    {
        $syllabus->load([
            'curriculumUnit.semester',
            'curriculumUnit.curriculumVersion.specialization',
            'curriculumUnit.curriculumVersion.program',
            'assessmentComponents.details',
        ]);

        $syllabus->total_assessment_weight = $syllabus->assessmentComponents->sum('weight');

        return response()->json([
            'success' => true,
            'data' => [
                'unit' => [
                    'id' => $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                    'credit_points' => $unit->credit_points,
                ],
                'syllabus' => [
                    'id' => $syllabus->id,
                    'version' => $syllabus->version,
                    'description' => $syllabus->description,
                    'total_hours' => $syllabus->total_hours,
                    'hours_per_session' => $syllabus->hours_per_session,
                    'is_active' => $syllabus->is_active,
                    'curriculum_unit' => [
                        'id' => $syllabus->curriculumUnit->id,
                        'semester' => [
                            'id' => $syllabus->curriculumUnit->semester->id,
                            'name' => $syllabus->curriculumUnit->semester->name,
                        ],
                        'specialization' => [
                            'id' => $syllabus->curriculumUnit->curriculumVersion->specialization->id,
                            'name' => $syllabus->curriculumUnit->curriculumVersion->specialization->name,
                        ],
                        'program' => [
                            'id' => $syllabus->curriculumUnit->curriculumVersion->program->id,
                            'name' => $syllabus->curriculumUnit->curriculumVersion->program->name,
                        ],
                    ],
                    'assessment_components' => $syllabus->assessmentComponents->map(function ($component) {
                        return [
                            'id' => $component->id,
                            'name' => $component->name,
                            'weight' => $component->weight,
                            'type' => $component->type,
                            'is_required_to_sit_final_exam' => $component->is_required_to_sit_final_exam,
                            'details' => $component->details->map(function ($detail) {
                                return [
                                    'id' => $detail->id,
                                    'name' => $detail->name,
                                    'weight' => $detail->weight,
                                ];
                            }),
                        ];
                    }),
                    'total_assessment_weight' => $syllabus->total_assessment_weight,
                    'has_complete_assessment_structure' => $syllabus->hasCompleteAssessmentStructure(),
                    'created_at' => $syllabus->created_at->toISOString(),
                    'updated_at' => $syllabus->updated_at->toISOString(),
                ],
            ],
        ]);
    }

    /**
     * Get active syllabus for a unit
     */
    public function active(Unit $unit): JsonResponse
    {
        $activeSyllabus = $unit->activeSyllabus()
            ->with([
                'curriculumUnit.semester',
                'curriculumUnit.curriculumVersion.specialization',
                'curriculumUnit.curriculumVersion.program',
                'assessmentComponents.details',
            ])
            ->first();

        if (! $activeSyllabus) {
            return response()->json([
                'success' => false,
                'message' => 'No active syllabus found for this unit',
            ], 404);
        }

        $activeSyllabus->total_assessment_weight = $activeSyllabus->assessmentComponents->sum('weight');

        return response()->json([
            'success' => true,
            'data' => [
                'unit' => [
                    'id' => $unit->id,
                    'code' => $unit->code,
                    'name' => $unit->name,
                    'credit_points' => $unit->credit_points,
                ],
                'syllabus' => [
                    'id' => $activeSyllabus->id,
                    'version' => $activeSyllabus->version,
                    'description' => $activeSyllabus->description,
                    'total_hours' => $activeSyllabus->total_hours,
                    'hours_per_session' => $activeSyllabus->hours_per_session,
                    'is_active' => $activeSyllabus->is_active,
                    'curriculum_unit' => [
                        'id' => $activeSyllabus->curriculumUnit->id,
                        'semester' => [
                            'id' => $activeSyllabus->curriculumUnit->semester->id,
                            'name' => $activeSyllabus->curriculumUnit->semester->name,
                        ],
                        'specialization' => [
                            'id' => $activeSyllabus->curriculumUnit->curriculumVersion->specialization->id,
                            'name' => $activeSyllabus->curriculumUnit->curriculumVersion->specialization->name,
                        ],
                        'program' => [
                            'id' => $activeSyllabus->curriculumUnit->curriculumVersion->program->id,
                            'name' => $activeSyllabus->curriculumUnit->curriculumVersion->program->name,
                        ],
                    ],
                    'assessment_components' => $activeSyllabus->assessmentComponents->map(function ($component) {
                        return [
                            'id' => $component->id,
                            'name' => $component->name,
                            'weight' => $component->weight,
                            'type' => $component->type,
                            'is_required_to_sit_final_exam' => $component->is_required_to_sit_final_exam,
                            'details' => $component->details->map(function ($detail) {
                                return [
                                    'id' => $detail->id,
                                    'name' => $detail->name,
                                    'weight' => $detail->weight,
                                ];
                            }),
                        ];
                    }),
                    'total_assessment_weight' => $activeSyllabus->total_assessment_weight,
                    'has_complete_assessment_structure' => $activeSyllabus->hasCompleteAssessmentStructure(),
                    'created_at' => $activeSyllabus->created_at->toISOString(),
                    'updated_at' => $activeSyllabus->updated_at->toISOString(),
                ],
            ],
        ]);
    }
}
