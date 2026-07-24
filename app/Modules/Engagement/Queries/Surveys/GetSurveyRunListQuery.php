<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Queries\Surveys;

use App\Models\FormTarget;
use App\Shared\Contracts\Institution\DepartmentReferenceReader;
use Illuminate\Pagination\LengthAwarePaginator;

final class GetSurveyRunListQuery
{
    public function __construct(private readonly DepartmentReferenceReader $departments) {}

    /**
     * Get a paginated list of survey runs (FormTarget).
     * All authenticated staff can view and filter all survey runs.
     */
    public function execute(array $filters = []): LengthAwarePaginator
    {
        $query = FormTarget::query()
            ->whereHas('form', function ($q) {
                $q->where('type', 'survey');
            });

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

        // 2. Add course details via joins for efficient filtering and sorting
        $query->leftJoin('course_offerings', function ($join) {
            $join->on('form_targets.scope_id', '=', 'course_offerings.id')
                ->where('form_targets.scope_type', '=', 'course');
        })
            ->leftJoin('units', 'course_offerings.unit_id', '=', 'units.id')
            ->leftJoin('lectures', 'course_offerings.lecture_id', '=', 'lectures.id')
            ->selectRaw("form_targets.*, units.code as course_code, units.name as course_name, course_offerings.section_code, CONCAT_WS(' ', lectures.first_name, lectures.last_name) as instructor_name");

        // 3. Optional department filter from UI (no access restriction — all staff can see all)
        if (! empty($filters['department_id']) && $filters['department_id'] !== 'all') {
            $deptId = (int) $filters['department_id'];
            $dept = $this->departments->find($deptId);

            $query->where(function ($q) use ($deptId, $dept) {
                // Always include dept-scoped surveys for this department
                $q->where(function ($sq) use ($deptId) {
                    $sq->where('form_targets.scope_type', 'department')
                        ->where('form_targets.scope_id', $deptId);
                });

                // ACA (Academic Service) also sees all course-scoped surveys
                if ($dept?->code === 'ACA') {
                    $q->orWhere('form_targets.scope_type', 'course');
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
            $query->orderBy('form_targets.'.$sort, $direction);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Return ordered list of FormTarget IDs matching the given filters.
     * Used for Prev/Next navigation in the Aggregate/detail view.
     */
    public function getOrderedIds(array $filters = []): array
    {
        $query = FormTarget::query()
            ->whereHas('form', function ($q) {
                $q->where('type', 'survey');
            });

        if ($campusId = session('current_campus_id')) {
            $query->where('form_targets.campus_id', $campusId);
        }

        $query->select('form_targets.id')
            ->selectSub(function ($q) {
                $q->from('student_form_assignments')
                    ->whereColumn('student_form_assignments.form_target_id', 'form_targets.id')
                    ->whereNotNull('student_form_assignments.response_id')
                    ->selectRaw('count(*)');
            }, 'responses_count');

        $query->leftJoin('course_offerings', function ($join) {
            $join->on('form_targets.scope_id', '=', 'course_offerings.id')
                ->where('form_targets.scope_type', '=', 'course');
        })
            ->leftJoin('units', 'course_offerings.unit_id', '=', 'units.id')
            ->leftJoin('lectures', 'course_offerings.lecture_id', '=', 'lectures.id');

        if (! empty($filters['department_id']) && $filters['department_id'] !== 'all') {
            $deptId = (int) $filters['department_id'];
            $dept = $this->departments->find($deptId);

            $query->where(function ($q) use ($deptId, $dept) {
                $q->where(function ($sq) use ($deptId) {
                    $sq->where('form_targets.scope_type', 'department')
                        ->where('form_targets.scope_id', $deptId);
                });
                if ($dept?->code === 'ACA') {
                    $q->orWhere('form_targets.scope_type', 'course');
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

        if ($sort === 'responses_count') {
            $query->orderBy('responses_count', $direction);
        } elseif ($sort === 'semester') {
            $query->leftJoin('semesters', 'form_targets.semester_id', '=', 'semesters.id')
                ->orderBy('semesters.name', $direction);
        } elseif ($sort === 'form_title') {
            $query->join('forms', 'form_targets.form_id', '=', 'forms.id')
                ->orderBy('forms.title', $direction);
        } else {
            $query->orderBy('form_targets.'.$sort, $direction);
        }

        return $query->pluck('form_targets.id')->toArray();
    }
}
