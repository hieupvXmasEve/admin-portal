<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Reporting;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class ExportStudentScholarshipApplicationQuery
{
    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'student_code',
            'student_name',
            'campus_name',
            'program_name',
            'semester_code',
            'semester_name',
            'semester_start_date',
            'scholarship_code',
            'scholarship_name',
            'scholarship_type',
            'discount_amount',
            'discount_status',
            'discount_description',
            'invoice_number',
            'invoice_status',
            'invoice_subtotal',
            'invoice_discount_total',
            'invoice_total_amount',
            'entitlement_lifecycle_status',
            'entitlement_allocation_status',
            'entitlement_amount',
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
            $row->campus_name,
            $row->program_name,
            $row->semester_code,
            $row->semester_name,
            $row->semester_start_date,
            $row->scholarship_code,
            $row->scholarship_name,
            $row->scholarship_type,
            $row->discount_amount,
            $row->discount_status,
            $row->discount_description,
            $row->invoice_number,
            $row->invoice_status,
            $row->invoice_subtotal,
            $row->invoice_discount_total,
            $row->invoice_total_amount,
            $row->entitlement_lifecycle_status,
            $row->entitlement_allocation_status,
            $row->entitlement_amount,
        ];
    }

    private function buildQuery(): Builder
    {
        return DB::table('invoice_discounts as id')
            ->join('student_invoices as si', 'si.id', '=', 'id.invoice_id')
            ->join('students as s', 's.id', '=', 'si.student_id')
            ->join('campuses as c', 'c.id', '=', 's.campus_id')
            ->join('programs as p', 'p.id', '=', 's.program_id')
            ->join('semesters as sem', 'sem.id', '=', 'si.semester_id')
            ->leftJoin('student_scholarship_awards as ssa', 'ssa.student_id', '=', 's.id')
            ->leftJoin('scholarship_definitions as sd', 'sd.code', '=', 'ssa.scholarship_code')
            ->leftJoin('finance_discount_entitlements as fde', 'fde.id', '=', 'id.finance_discount_entitlement_id')
            ->where('id.discount_type', 'scholarship')
            ->whereNull('s.deleted_at')
            ->select([
                's.student_id as student_code',
                's.full_name as student_name',
                'c.name as campus_name',
                'p.name as program_name',
                'sem.code as semester_code',
                'sem.name as semester_name',
                DB::raw('DATE(sem.start_date) as semester_start_date'),
                'ssa.scholarship_code',
                'sd.name as scholarship_name',
                'sd.type as scholarship_type',
                'id.amount as discount_amount',
                'id.status as discount_status',
                'id.description as discount_description',
                'si.invoice_number',
                'si.status as invoice_status',
                'si.cached_subtotal as invoice_subtotal',
                'si.cached_discount_total as invoice_discount_total',
                'si.cached_total_amount as invoice_total_amount',
                'fde.lifecycle_status as entitlement_lifecycle_status',
                'fde.allocation_status as entitlement_allocation_status',
                'fde.amount as entitlement_amount',
            ])
            ->orderBy('sem.start_date')
            ->orderBy('s.student_id');
    }
}
