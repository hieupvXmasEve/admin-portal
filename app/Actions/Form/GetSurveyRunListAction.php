<?php

namespace App\Actions\Form;

use App\Models\FormTarget;
use App\Models\Department;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class GetSurveyRunListAction
{
    /**
     * Get a paginated list of survey runs (FormTarget).
     */
    public function execute(array $filters = []): LengthAwarePaginator
    {
        $query = FormTarget::query()
            ->whereHas('form', function ($q) {
                $q->where('type', 'survey');
            });

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $isAdmin = $user->hasSystemRole('admin') || $user->hasSystemRole('super_admin');

        // Filter by current campus
        if ($campusId = session('current_campus_id')) {
            $query->where('form_targets.campus_id', $campusId);
        }

        $query->with(['form', 'semester', 'campus']);

        // 1. Add count of responses
        $query->select('form_targets.*')
            ->selectSub(function ($q) {
                $q->from('student_form_assignments')
                    ->whereColumn('student_form_assignments.form_target_id', 'form_targets.id')
                    ->whereNotNull('student_form_assignments.response_id')
                    ->selectRaw('count(*)');
            }, 'responses_count')
            ->selectSub(function ($q) {
                $q->from('student_form_assignments')
                    ->whereColumn('student_form_assignments.form_target_id', 'form_targets.id')
                    ->selectRaw('count(*)');
            }, 'assignments_count');

        // 2. Add course details if scope is course
        // Using joins for efficient filtering and sorting
        $query->leftJoin('course_offerings', function ($join) {
            $join->on('form_targets.scope_id', '=', 'course_offerings.id')
                ->where('form_targets.scope_type', '=', 'course');
        })
        ->leftJoin('units', 'course_offerings.unit_id', '=', 'units.id')
        ->leftJoin('lectures', 'course_offerings.lecture_id', '=', 'lectures.id')
        ->selectRaw("form_targets.*, units.code as course_code, units.name as course_name, course_offerings.section_code, CONCAT_WS(' ', lectures.first_name, lectures.last_name) as instructor_name");

        // 3. Department Filtering & Security
        $userDepts = Department::whereHas('memberships', function ($q) use ($user) {
            $q->where('user_id', $user->id)->where('is_active', true);
        })->get(['id', 'code']);

        $userDeptIds = $userDepts->pluck('id')->toArray();
        $userDeptCodes = $userDepts->pluck('code')->filter()->toArray();

        // If not admin and no departments, hide everything as requested
        if (!$isAdmin && empty($userDeptIds)) {
            $query->whereRaw('1 = 0');
        } elseif (!$isAdmin) {
            // Restrict results to user's departments
            $query->where(function ($q) use ($userDeptIds, $userDeptCodes) {
                // Match department-scoped surveys
                $q->where(function ($sq) use ($userDeptIds) {
                    $sq->where('form_targets.scope_type', 'department')
                        ->whereIn('form_targets.scope_id', $userDeptIds);
                });

                // Match course-scoped surveys via instructor's department
                if (!empty($userDeptCodes)) {
                    $q->orWhere(function ($sq) use ($userDeptCodes) {
                        $sq->where('form_targets.scope_type', 'course')
                            ->whereIn('lectures.department', $userDeptCodes);
                    });
                }
            });
        }

        // Explicit department filter from UI
        if (!empty($filters['department_id']) && $filters['department_id'] !== 'all') {
            $deptId = (int) $filters['department_id'];
            $targetDept = Department::find($deptId);
            $targetDeptCode = $targetDept?->code;

            $query->where(function ($q) use ($deptId, $targetDeptCode) {
                $q->where(function ($sq) use ($deptId) {
                    $sq->where('form_targets.scope_type', 'department')
                        ->where('form_targets.scope_id', $deptId);
                });

                if ($targetDeptCode) {
                    $q->orWhere(function ($sq) use ($targetDeptCode) {
                        $sq->where('form_targets.scope_type', 'course')
                            ->where('lectures.department', $targetDeptCode);
                    });
                }
            });
        }

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->whereHas('form', function ($sub) use ($filters) {
                    $sub->where('title', 'like', '%'.$filters['search'].'%');
                })
                ->orWhere('units.code', 'like', '%'.$filters['search'].'%')
                ->orWhere('units.name', 'like', '%'.$filters['search'].'%')
                ->orWhereRaw("CONCAT_WS(' ', lectures.first_name, lectures.last_name) like ?", ['%'.$filters['search'].'%']);
            });
        }

        if (! empty($filters['semester_id']) && $filters['semester_id'] !== 'all') {
            $query->where('form_targets.semester_id', $filters['semester_id']);
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('form_targets.status', $filters['status']);
        }

        $sort = $filters['sort'] ?? 'created_at';
        $direction = $filters['direction'] ?? 'desc';

        // Handle custom sort for responses_count
        if ($sort === 'responses_count') {
            $query->orderBy('responses_count', $direction);
        } elseif ($sort === 'semester') {
            $query->leftJoin('semesters', 'form_targets.semester_id', '=', 'semesters.id')
                ->orderBy('semesters.name', $direction);
        } elseif ($sort === 'form_title') {
            $query->join('forms', 'form_targets.form_id', '=', 'forms.id')
                ->orderBy('forms.title', $direction);
        } else {
            $query->orderBy('form_targets.' . $sort, $direction);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }
}
