<?php

namespace App\Actions\Form;

use App\Models\FormTarget;
use Illuminate\Pagination\LengthAwarePaginator;

class GetSurveyRunListAction
{
    /**
     * Get a paginated list of survey runs (FormTarget).
     */
    public function execute(array $filters = []): LengthAwarePaginator
    {
        $query = FormTarget::query();

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
