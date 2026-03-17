<?php

declare(strict_types=1);

namespace App\Services\V1\Student;

use App\Models\Event;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TimetableEventQuery
{
    public function handle(Student $student, Carbon $rangeStart, Carbon $rangeEnd, array $filters = []): Collection
    {
        $query = Event::query()
            ->where('campus_id', $student->campus_id)
            ->where('status', '!=', 'draft')
            ->where('start_time', '<=', $rangeEnd->copy()->endOfDay())
            ->where('end_time', '>=', $rangeStart->copy()->startOfDay())
            ->orderBy('start_time');

        $this->applyFilters($query, $filters);

        return $query->get();
    }

    protected function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['start_time_after'])) {
            $query->whereTime('end_time', '>=', $filters['start_time_after']);
        }

        if (! empty($filters['end_time_before'])) {
            $query->whereTime('start_time', '<=', $filters['end_time_before']);
        }

        if (! empty($filters['time_range']['start'])) {
            $query->whereTime('end_time', '>=', $filters['time_range']['start']);
        }

        if (! empty($filters['time_range']['end'])) {
            $query->whereTime('start_time', '<=', $filters['time_range']['end']);
        }
    }
}
