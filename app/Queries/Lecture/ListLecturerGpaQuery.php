<?php

declare(strict_types=1);

namespace App\Queries\Lecture;

use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ListLecturerGpaQuery
{
    private const DEFAULT_SORT = 'lecturer_name';

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $campusId): LengthAwarePaginatorContract
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = (int) ($filters['page'] ?? Paginator::resolveCurrentPage());

        if (empty($filters['semester_id'])) {
            return new LengthAwarePaginator([], 0, $perPage, $page, [
                'path' => Paginator::resolveCurrentPath(),
            ]);
        }

        return $this->buildQuery($filters, $campusId)
            ->paginate($perPage)
            ->through(fn (object $row): array => $this->formatRow($row))
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function all(array $filters, int $campusId): Collection
    {
        if (empty($filters['semester_id'])) {
            return collect();
        }

        return $this->buildQuery($filters, $campusId)
            ->get()
            ->map(fn (object $row): array => $this->formatRow($row));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function buildQuery(array $filters, int $campusId): Builder
    {
        $classScores = $this->buildClassScoreSubquery($filters, $campusId);

        $query = DB::query()
            ->fromSub($classScores, 'class_scores')
            ->select([
                'lecturer_id',
                DB::raw('MAX(lecturer_name) as lecturer_name'),
                DB::raw('MAX(email) as email'),
                DB::raw('MAX(employee_id) as employee_id'),
                DB::raw('MAX(employment_type) as employment_type'),
                DB::raw("GROUP_CONCAT(DISTINCT course_label ORDER BY course_label SEPARATOR ', ') as courses"),
                DB::raw('ROUND(AVG(class_gpa), 2) as gpa'),
                DB::raw('COUNT(class_gpa) as evaluated_classes_count'),
                DB::raw('COUNT(*) as classes_count'),
                DB::raw('COALESCE(SUM(responses_count), 0) as responses_count'),
            ])
            ->groupBy('lecturer_id');

        $this->applySorting($query, $filters);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function buildClassScoreSubquery(array $filters, int $campusId): Builder
    {
        $matchingAnswer = "q.type = 'rating'
            AND a.answer_number IS NOT NULL
            AND a.answer_number > 0
            AND LOWER(fs.title) LIKE '%lecturer evaluation%'
            AND f.type = 'survey'";

        $query = DB::table('course_offerings as co')
            ->join('lectures as l', 'l.id', '=', 'co.lecture_id')
            ->join('units as u', 'u.id', '=', 'co.unit_id')
            ->leftJoin('form_targets as ft', function ($join): void {
                $join->on('ft.scope_id', '=', 'co.id')
                    ->where('ft.scope_type', '=', 'course');
            })
            ->leftJoin('forms as f', 'f.id', '=', 'ft.form_id')
            ->leftJoin('student_form_assignments as sfa', function ($join): void {
                $join->on('sfa.form_target_id', '=', 'ft.id')
                    ->whereNotNull('sfa.response_id');
            })
            ->leftJoin('answers as a', 'a.response_id', '=', 'sfa.response_id')
            ->leftJoin('questions as q', 'q.id', '=', 'a.question_id')
            ->leftJoin('form_sections as fs', 'fs.id', '=', 'q.section_id')
            ->where('co.campus_id', $campusId)
            ->where('co.semester_id', (int) $filters['semester_id'])
            ->whereNotNull('co.lecture_id')
            ->whereNull('co.deleted_at')
            ->whereNull('l.deleted_at')
            ->select([
                'co.id as course_offering_id',
                'l.id as lecturer_id',
                DB::raw("TRIM(CONCAT_WS(' ', l.first_name, l.last_name)) as lecturer_name"),
                'l.email',
                'l.employee_id',
                'l.employment_type',
                DB::raw("CONCAT(u.code, CASE WHEN co.section_code IS NULL OR co.section_code = '' THEN '' ELSE CONCAT('.', co.section_code) END) as course_label"),
                DB::raw("AVG(CASE WHEN {$matchingAnswer} THEN a.answer_number END) as class_gpa"),
                DB::raw("COUNT(DISTINCT CASE WHEN {$matchingAnswer} THEN sfa.response_id END) as responses_count"),
            ])
            ->groupBy([
                'co.id',
                'co.section_code',
                'u.code',
                'l.id',
                'l.first_name',
                'l.last_name',
                'l.email',
                'l.employee_id',
                'l.employment_type',
            ]);

        $this->applySearch($query, (string) ($filters['search'] ?? ''));

        return $query;
    }

    private function applySearch(Builder $query, string $search): void
    {
        $search = trim($search);

        if ($search === '') {
            return;
        }

        $like = "%{$search}%";

        $query->where(function (Builder $searchQuery) use ($like): void {
            $searchQuery
                ->where('l.employee_id', 'like', $like)
                ->orWhere('l.first_name', 'like', $like)
                ->orWhere('l.last_name', 'like', $like)
                ->orWhere('l.email', 'like', $like)
                ->orWhere('u.code', 'like', $like)
                ->orWhere('u.name', 'like', $like);
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applySorting(Builder $query, array $filters): void
    {
        $sort = (string) ($filters['sort'] ?? self::DEFAULT_SORT);
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        match ($sort) {
            'employee_id' => $query->orderBy('employee_id', $direction),
            'type' => $query->orderBy('employment_type', $direction),
            'gpa' => $query->orderBy('gpa', $direction),
            'evaluated_classes_count' => $query->orderBy('evaluated_classes_count', $direction),
            'responses_count' => $query->orderBy('responses_count', $direction),
            default => $query->orderBy('lecturer_name', $direction),
        };

        $query->orderBy('lecturer_id', 'asc');
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRow(object $row): array
    {
        $email = (string) ($row->email ?? '');
        $courses = (string) ($row->courses ?? '');
        $employmentType = (string) ($row->employment_type ?? '');

        return [
            'lecturer_id' => (int) $row->lecturer_id,
            'lecturer_name' => (string) $row->lecturer_name,
            'email_account' => $email !== '' ? Str::before($email, '@') : '',
            'employee_id' => (string) $row->employee_id,
            'type' => $employmentType,
            'type_label' => $this->formatEmploymentType($employmentType),
            'courses' => $courses !== '' ? explode(', ', $courses) : [],
            'courses_display' => $courses,
            'gpa' => $row->gpa !== null ? round((float) $row->gpa, 2) : null,
            'evaluated_classes_count' => (int) $row->evaluated_classes_count,
            'classes_count' => (int) $row->classes_count,
            'responses_count' => (int) $row->responses_count,
        ];
    }

    private function formatEmploymentType(string $type): string
    {
        return match ($type) {
            'full_time' => 'Full Time',
            'part_time' => 'Part Time',
            'contract' => 'Contract',
            'visiting' => 'Visiting',
            'emeritus' => 'Emeritus',
            default => $type !== '' ? Str::headline($type) : 'N/A',
        };
    }
}
