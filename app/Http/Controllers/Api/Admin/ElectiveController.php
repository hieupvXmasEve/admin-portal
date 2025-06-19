<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CurriculumUnit;
use App\Models\CurriculumVersion;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class ElectiveController extends Controller
{
    /**
     * Get available elective units for a specific curriculum version.
     */
    public function getAvailableElectives(CurriculumVersion $curriculumVersion, Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'nullable|string|max:255',
            'category' => 'nullable|string|in:same_program,other_programs,general',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $search = $request->get('search');
        $category = $request->get('category');
        $perPage = $request->get('per_page', 15);

        $electives = $curriculumVersion->getElectiveUnitsByCategory();

        // Filter by category if specified
        if ($category) {
            $categoryMap = [
                'same_program' => 'same_program_other_specializations',
                'other_programs' => 'cross_program_electives',
                'general' => 'general_electives',
            ];

            $selectedCategory = $categoryMap[$category] ?? 'same_program_other_specializations';
            $units = $electives[$selectedCategory];
        } else {
            // Combine all categories
            $units = collect()
                ->merge($electives['same_program_other_specializations'])
                ->merge($electives['cross_program_electives'])
                ->merge($electives['general_electives'])
                ->unique('id');
        }

        // Apply search filter
        if ($search) {
            $units = $units->filter(function ($unit) use ($search) {
                return stripos($unit->name, $search) !== false ||
                    stripos($unit->code, $search) !== false;
            });
        }

        // Paginate results
        $total = $units->count();
        $units = $units->forPage($request->get('page', 1), $perPage)->values();

        return response()->json([
            'data' => [
                'curriculum_version' => [
                    'id' => $curriculumVersion->id,
                    'specialization' => $curriculumVersion->specialization->name,
                    'program' => $curriculumVersion->program->name,
                ],
                'available_electives' => [
                    'same_program_other_specializations' => [
                        'label' => 'Units from other specializations in the same program',
                        'count' => $electives['same_program_other_specializations']->count(),
                    ],
                    'cross_program_electives' => [
                        'label' => 'Units from other programs',
                        'count' => $electives['cross_program_electives']->count(),
                    ],
                    'general_electives' => [
                        'label' => 'General elective units',
                        'count' => $electives['general_electives']->count(),
                    ]
                ],
                'units' => $units->map(function ($unit) {
                    return [
                        'id' => $unit->id,
                        'code' => $unit->code,
                        'name' => $unit->name,
                        'credit_points' => $unit->credit_points,
                        'has_prerequisites' => $unit->prerequisiteGroups()->exists(),
                        'has_syllabus' => $unit->activeSyllabus()->exists(),
                    ];
                }),
                'pagination' => [
                    'total' => $total,
                    'per_page' => $perPage,
                    'current_page' => $request->get('page', 1),
                    'last_page' => ceil($total / $perPage),
                ],
            ]
        ]);
    }

    /**
     * Get elective slots for a specific curriculum version.
     */
    public function getElectiveSlots(CurriculumVersion $curriculumVersion): JsonResponse
    {
        $electiveSlots = $curriculumVersion->getElectiveSlots();

        return response()->json([
            'data' => [
                'curriculum_version' => [
                    'id' => $curriculumVersion->id,
                    'specialization' => $curriculumVersion->specialization->name,
                    'program' => $curriculumVersion->program->name,
                    'version_code' => $curriculumVersion->version_code,
                ],
                'elective_slots' => $electiveSlots->map(function ($slot) {
                    return [
                        'id' => $slot->id,
                        'year_level' => $slot->year_level,
                        'semester_number' => $slot->semester_number,
                        'current_unit' => [
                            'id' => $slot->unit->id,
                            'code' => $slot->unit->code,
                            'name' => $slot->unit->name,
                            'credit_points' => $slot->unit->credit_points,
                        ],
                        'can_be_changed' => true, // Can be extended with business logic
                        'note' => $slot->note,
                    ];
                }),
                'total_elective_slots' => $electiveSlots->count(),
            ]
        ]);
    }

    /**
     * Update an elective slot with a new unit.
     */
    public function updateElectiveSlot(Request $request, CurriculumUnit $curriculumUnit): JsonResponse
    {
        $request->validate([
            'unit_id' => 'required|exists:units,id',
            'reason' => 'nullable|string|max:500',
        ]);

        // Validate that this is an elective slot
        if (!$curriculumUnit->isElective()) {
            throw ValidationException::withMessages([
                'curriculum_unit' => 'This curriculum unit is not an elective slot.'
            ]);
        }

        $newUnit = Unit::findOrFail($request->unit_id);

        // Validate that the unit can be used as elective
        if (!$newUnit->canBeElectiveFor(
            $curriculumUnit->curriculumVersion->specialization_id,
            $curriculumUnit->curriculumVersion->program_id
        )) {
            throw ValidationException::withMessages([
                'unit_id' => 'This unit cannot be used as an elective for this specialization.'
            ]);
        }

        // Update the curriculum unit
        $oldUnit = $curriculumUnit->unit;
        $curriculumUnit->update([
            'unit_id' => $newUnit->id,
            'note' => $curriculumUnit->note .
                ($request->reason ? " | Updated: {$request->reason}" : " | Updated via API"),
        ]);

        return response()->json([
            'message' => 'Elective unit updated successfully',
            'data' => [
                'curriculum_unit_id' => $curriculumUnit->id,
                'old_unit' => [
                    'id' => $oldUnit->id,
                    'code' => $oldUnit->code,
                    'name' => $oldUnit->name,
                ],
                'new_unit' => [
                    'id' => $newUnit->id,
                    'code' => $newUnit->code,
                    'name' => $newUnit->name,
                    'credit_points' => $newUnit->credit_points,
                ],
            ]
        ]);
    }

    /**
     * Get detailed information about a specific unit for elective selection.
     */
    public function getUnitDetails(Unit $unit): JsonResponse
    {
        $unit->load(['prerequisiteGroups.conditions.requiredUnit', 'activeSyllabus']);

        return response()->json([
            'data' => [
                'id' => $unit->id,
                'code' => $unit->code,
                'name' => $unit->name,
                'credit_points' => $unit->credit_points,
                'prerequisites' => $unit->prerequisiteGroups->map(function ($group) {
                    return [
                        'id' => $group->id,
                        'logic_operator' => $group->logic_operator,
                        'conditions' => $group->conditions->map(function ($condition) {
                            return [
                                'type' => $condition->type,
                                'required_unit' => $condition->requiredUnit ? [
                                    'id' => $condition->requiredUnit->id,
                                    'code' => $condition->requiredUnit->code,
                                    'name' => $condition->requiredUnit->name,
                                ] : null,
                                'required_credits' => $condition->required_credits,
                                'free_text' => $condition->free_text,
                            ];
                        }),
                    ];
                }),
                'syllabus' => $unit->activeSyllabus ? [
                    'id' => $unit->activeSyllabus->id,
                    'version' => $unit->activeSyllabus->version,
                    'description' => $unit->activeSyllabus->description,
                    'total_hours' => $unit->activeSyllabus->total_hours,
                ] : null,
                'used_in_specializations' => $unit->curriculumUnits()
                    ->with('curriculumVersion.specialization')
                    ->get()
                    ->pluck('curriculumVersion.specialization.name')
                    ->unique()
                    ->values(),
            ]
        ]);
    }

    /**
     * Get elective recommendations for a specific curriculum unit.
     */
    public function getElectiveRecommendations(CurriculumUnit $curriculumUnit): JsonResponse
    {
        if (!$curriculumUnit->isElective()) {
            throw ValidationException::withMessages([
                'curriculum_unit' => 'This curriculum unit is not an elective slot.'
            ]);
        }

        $recommendations = $curriculumUnit->getAllAvailableElectives();

        return response()->json([
            'data' => [
                'curriculum_unit' => [
                    'id' => $curriculumUnit->id,
                    'year_level' => $curriculumUnit->year_level,
                    'semester_number' => $curriculumUnit->semester_number,
                    'current_unit' => [
                        'id' => $curriculumUnit->unit->id,
                        'code' => $curriculumUnit->unit->code,
                        'name' => $curriculumUnit->unit->name,
                    ],
                ],
                'recommendations' => [
                    'same_program_other_specializations' => [
                        'label' => 'From other specializations in your program',
                        'units' => $recommendations['same_program_other_specializations']->take(10)->map(function ($unit) {
                            return [
                                'id' => $unit->id,
                                'code' => $unit->code,
                                'name' => $unit->name,
                                'credit_points' => $unit->credit_points,
                            ];
                        }),
                        'total_count' => $recommendations['same_program_other_specializations']->count(),
                    ],
                    'other_programs' => [
                        'label' => 'From other programs',
                        'units' => $recommendations['other_programs']->take(10)->map(function ($unit) {
                            return [
                                'id' => $unit->id,
                                'code' => $unit->code,
                                'name' => $unit->name,
                                'credit_points' => $unit->credit_points,
                            ];
                        }),
                        'total_count' => $recommendations['other_programs']->count(),
                    ],
                    'unassigned_units' => [
                        'label' => 'General electives',
                        'units' => $recommendations['unassigned_units']->take(10)->map(function ($unit) {
                            return [
                                'id' => $unit->id,
                                'code' => $unit->code,
                                'name' => $unit->name,
                                'credit_points' => $unit->credit_points,
                            ];
                        }),
                        'total_count' => $recommendations['unassigned_units']->count(),
                    ],
                ],
            ]
        ]);
    }
}
