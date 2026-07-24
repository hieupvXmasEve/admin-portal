<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Queries\Surveys;

use Illuminate\Support\Facades\DB;

class GetSurveyProgramStatsQuery
{
    /**
     * Get course survey stats grouped by program.
     *
     * Only counts form_targets with scope_type = 'course' (survey môn học),
     * excluding department-scoped or other non-course surveys.
     *
     * @param  array{semester_id?: string|null}  $filters
     * @return array{rows: list<array>, totals: array}
     */
    public function handle(array $filters): array
    {
        $campusId = session('current_campus_id');
        $semesterId = ($filters['semester_id'] ?? null) !== 'all'
            ? ($filters['semester_id'] ?? null)
            : null;

        $bindings = [];
        $semesterClause = '';

        if ($semesterId) {
            $semesterClause = 'AND ft.semester_id = ?';
            $bindings[] = $semesterId;
        }

        if ($campusId) {
            $campusClause = 'AND ft.campus_id = ?';
            $bindings[] = $campusId;
        } else {
            $campusClause = '';
        }

        $sql = "
            SELECT
                p.code   AS program_code,
                p.name   AS program_name,
                COUNT(DISTINCT sfa.response_id) AS submissions,
                SUM(CASE WHEN per_resp.avg_score >= 4 THEN 1 ELSE 0 END) AS high_rated_count
            FROM student_form_assignments sfa
            JOIN students s   ON sfa.student_id = s.id
            JOIN programs p   ON s.program_id = p.id
            JOIN form_targets ft ON sfa.form_target_id = ft.id
                AND ft.scope_type = 'course'
            JOIN forms f      ON ft.form_id = f.id AND f.type = 'survey'
            LEFT JOIN (
                SELECT a.response_id, AVG(a.answer_number) AS avg_score
                FROM answers a
                JOIN questions q ON a.question_id = q.id AND q.type = 'rating'
                WHERE a.answer_number IS NOT NULL
                GROUP BY a.response_id
            ) per_resp ON per_resp.response_id = sfa.response_id
            WHERE sfa.response_id IS NOT NULL
              {$semesterClause}
              {$campusClause}
            GROUP BY p.id, p.code, p.name
            ORDER BY p.name ASC
        ";

        $rawRows = DB::select($sql, $bindings);

        $rows = array_map(fn ($row) => [
            'program_code' => $row->program_code,
            'program_name' => $row->program_name,
            'submissions' => (int) $row->submissions,
            'high_rated_count' => (int) $row->high_rated_count,
            'percent' => $row->submissions > 0
                ? round(($row->high_rated_count / $row->submissions) * 100, 1)
                : null,
        ], $rawRows);

        $totalSubmissions = array_sum(array_column($rows, 'submissions'));
        $totalHighRated = array_sum(array_column($rows, 'high_rated_count'));

        return [
            'rows' => $rows,
            'totals' => [
                'submissions' => $totalSubmissions,
                'high_rated_count' => $totalHighRated,
                'percent' => $totalSubmissions > 0
                    ? round(($totalHighRated / $totalSubmissions) * 100, 1)
                    : null,
            ],
        ];
    }
}
