<?php

namespace App\Actions\Form;

use App\Models\FormResponse;
use App\Models\FormTarget;
use Illuminate\Pagination\LengthAwarePaginator;

class GetSurveyResponseListAction
{
    /**
     * Get a paginated list of responses for a specific survey run.
     */
    public function execute(FormTarget $target, array $filters = []): LengthAwarePaginator
    {
        $query = \App\Models\StudentFormAssignment::query()
            ->where('form_target_id', $target->id)
            ->with(['student', 'response.answers.question', 'response.answers.selectedOptions']);

        if (! empty($filters['search'])) {
            $query->whereHas('student', function ($q) use ($filters) {
                $q->where('full_name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('student_id', 'like', '%'.$filters['search'].'%');
            });
        }

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'pending') {
                $query->where('status', 'not_started');
            } else {
                $query->whereHas('response', function ($q) use ($filters) {
                    $q->where('status', $filters['status']);
                });
            }
        }

        $sort = $filters['sort'] ?? 'completed_at';
        $direction = $filters['direction'] ?? 'desc';

        if ($sort === 'submitted_at') {
            $sort = 'completed_at';
        }

        $query->orderBy($sort, $direction);

        return $query->paginate($filters['per_page'] ?? 15);
    }
}
