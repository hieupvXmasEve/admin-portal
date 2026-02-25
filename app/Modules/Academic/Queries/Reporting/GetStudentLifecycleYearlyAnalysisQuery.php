<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries\Reporting;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetStudentLifecycleYearlyAnalysisQuery
{
    /**
     * Build yearly lifecycle aggregates by intake year (semester start year).
     */
    public function handle(?int $campusId = null): Collection
    {
        $actionFlags = DB::table('student_action_logs')
            ->select('student_id')
            ->selectRaw("MAX(CASE WHEN action_type = 'CAMPUS_TRANSFER' THEN 1 ELSE 0 END) as has_campus_transfer")
            ->groupBy('student_id');

        $yearLabelExpr = $this->academicYearLabelSql('sem.start_date');
        $yearStartExpr = $this->academicYearStartSql('sem.start_date');

        $rows = DB::table('students as s')
            ->join('semesters as sem', 'sem.id', '=', 's.intake_semester_id')
            ->leftJoinSub($actionFlags, 'af', function ($join) {
                $join->on('af.student_id', '=', 's.id');
            })
            ->when($campusId, function ($query, $currentCampusId) {
                $query->where('s.campus_id', $currentCampusId);
            })
            ->selectRaw("{$yearLabelExpr} as year_intake")
            ->selectRaw("{$yearStartExpr} as year_start")
            ->selectRaw('COUNT(DISTINCT s.id) as total_students')
            ->selectRaw('COUNT(DISTINCT CASE WHEN s.user_id IS NOT NULL THEN s.id END) as ne')
            ->selectRaw("COUNT(DISTINCT CASE WHEN s.status = 'intake_pre_uni_gc' THEN s.id END) as intake_pre_uni_gc")
            ->selectRaw("COUNT(DISTINCT CASE WHEN s.status = 'intake_course' THEN s.id END) as intake_course")
            ->selectRaw("COUNT(DISTINCT CASE WHEN s.status = 'pending' THEN s.id END) as pending")
            ->selectRaw("COUNT(DISTINCT CASE WHEN s.status = 'graduated' THEN s.id END) as graduated")
            ->selectRaw("COUNT(DISTINCT CASE WHEN s.status = 'dropout_transfer' THEN s.id END) as do_transfer")
            ->selectRaw('COUNT(DISTINCT CASE WHEN COALESCE(af.has_campus_transfer, 0) = 1 THEN s.id END) as change_campus')
            // Rate numerators are constrained to NE population (students with AP account).
            ->selectRaw("COUNT(DISTINCT CASE WHEN s.user_id IS NOT NULL AND s.status = 'pending' THEN s.id END) as pending_ne")
            ->selectRaw("COUNT(DISTINCT CASE WHEN s.user_id IS NOT NULL AND s.status = 'graduated' THEN s.id END) as graduated_ne")
            ->whereNotNull('s.intake_semester_id')
            ->groupByRaw("{$yearLabelExpr}, {$yearStartExpr}")
            ->orderByDesc('year_start')
            ->get();

        // Defer is grouped by defer start semester year (from_semester_id), not intake year.
        $deferCountsByYear = DB::table('student_action_logs as sal')
            ->join('semesters as dsem', 'dsem.id', '=', 'sal.from_semester_id')
            ->join('students as ds', 'ds.id', '=', 'sal.student_id')
            ->when($campusId, function ($query, $currentCampusId) {
                $query->where('ds.campus_id', $currentCampusId);
            })
            ->where('sal.action_type', 'ACADEMIC_DEFER')
            ->whereNotNull('sal.from_semester_id')
            ->selectRaw('YEAR(dsem.start_date) as year_start')
            ->selectRaw('COUNT(DISTINCT sal.student_id) as defer')
            ->selectRaw('COUNT(DISTINCT CASE WHEN ds.user_id IS NOT NULL THEN sal.student_id END) as defer_ne')
            ->groupByRaw('YEAR(dsem.start_date)')
            ->get()
            ->keyBy('year_start');

        // DO is grouped by dropout semester year (dropout_semester_id), not intake year.
        $dropoutCountsByYear = DB::table('student_action_logs as sal')
            ->join('semesters as dsem', 'dsem.id', '=', 'sal.dropout_semester_id')
            ->join('students as ds', 'ds.id', '=', 'sal.student_id')
            ->when($campusId, function ($query, $currentCampusId) {
                $query->where('ds.campus_id', $currentCampusId);
            })
            ->where('sal.action_type', 'ACADEMIC_DROPOUT')
            ->whereNotNull('sal.dropout_semester_id')
            ->selectRaw('YEAR(dsem.start_date) as year_start')
            ->selectRaw('COUNT(DISTINCT sal.student_id) as do_count')
            ->selectRaw('COUNT(DISTINCT CASE WHEN ds.user_id IS NOT NULL THEN sal.student_id END) as do_count_ne')
            ->groupByRaw('YEAR(dsem.start_date)')
            ->get()
            ->keyBy('year_start');

        return $rows->map(function ($row) use ($deferCountsByYear, $dropoutCountsByYear) {
            $ne = (int) $row->ne;
            $deferRow = $deferCountsByYear->get($row->year_start);
            $dropoutRow = $dropoutCountsByYear->get($row->year_start);
            $defer = (int) ($deferRow->defer ?? 0);
            $deferNe = (int) ($deferRow->defer_ne ?? 0);
            $doCount = (int) ($dropoutRow->do_count ?? 0);
            $doCountNe = (int) ($dropoutRow->do_count_ne ?? 0);

            return [
                'year_intake' => (string) $row->year_intake,
                'year_start' => (int) $row->year_start,
                'total_students' => (int) $row->total_students,
                'ne' => $ne,
                'intake_pre_uni_gc' => (int) $row->intake_pre_uni_gc,
                'intake_course' => (int) $row->intake_course,
                'defer' => $defer,
                'do' => $doCount,
                'change_campus' => (int) $row->change_campus,
                'graduated' => (int) $row->graduated,
                'pending' => (int) $row->pending,
                'do_transfer' => (int) $row->do_transfer,
                'pending_rate' => $this->rate((int) $row->pending_ne, $ne),
                'df_rate' => $this->rate($deferNe, $ne),
                'do_rate' => $this->rate($doCountNe, $ne),
                'graduated_rate' => $this->rate((int) $row->graduated_ne, $ne),
            ];
        });
    }

    private function rate(int $numerator, int $denominator): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }

        return round(($numerator / $denominator) * 100, 2);
    }

    private function academicYearLabelSql(string $dateColumn): string
    {
        return "CAST(YEAR({$dateColumn}) AS CHAR)";
    }

    private function academicYearStartSql(string $dateColumn): string
    {
        return "YEAR({$dateColumn})";
    }
}
