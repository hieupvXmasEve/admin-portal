<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Reporting;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class ExportStudentScholarshipRosterQuery
{
    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'student_code',
            'student_name',
            'student_email',
            'campus_name',
            'program_name',
            'intake',
            'has_scholarship',
            'scholarship_code',
            'scholarship_name',
            'scholarship_type',
            'scholarship_amount',
            'total_amount',
            'total_terms',
            'valid_from',
            'valid_until',
            'scholarship_is_active',
            'awarded_at',
            'award_notes',
        ];
    }

    public function cursor(): LazyCollection
    {
        return $this->buildQuery()->cursor();
    }

    /**
     * @return list<int|string|null>
     */
    public function formatRow(object $row): array
    {
        return [
            $row->student_code,
            $row->student_name,
            $row->student_email,
            $row->campus_name,
            $row->program_name,
            $row->intake,
            $row->scholarship_code !== null ? 'yes' : 'no',
            $row->scholarship_code,
            $row->scholarship_name,
            $row->scholarship_type,
            $row->scholarship_amount,
            $row->total_amount,
            $row->total_terms,
            $row->valid_from,
            $row->valid_until,
            $this->formatBoolean($row->scholarship_is_active),
            $row->awarded_at,
            $row->award_notes,
        ];
    }

    private function buildQuery(): Builder
    {
        return DB::table('students as s')
            ->join('campuses as c', 's.campus_id', '=', 'c.id')
            ->join('programs as p', 's.program_id', '=', 'p.id')
            ->leftJoin('student_scholarship_awards as ssa', 'ssa.student_id', '=', 's.id')
            ->leftJoin('scholarship_definitions as sd', 'sd.code', '=', 'ssa.scholarship_code')
            ->whereNull('s.deleted_at')
            ->select([
                's.student_id as student_code',
                's.full_name as student_name',
                's.email as student_email',
                'c.name as campus_name',
                'p.name as program_name',
                's.intake',
                'ssa.scholarship_code',
                'sd.name as scholarship_name',
                'sd.type as scholarship_type',
                'sd.amount as scholarship_amount',
                'sd.total_amount',
                'sd.total_terms',
                'sd.valid_from',
                'sd.valid_until',
                'sd.is_active as scholarship_is_active',
                'ssa.awarded_at',
                'ssa.notes as award_notes',
            ])
            ->orderBy('s.student_id');
    }

    private function formatBoolean(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'yes' : 'no';
    }
}
