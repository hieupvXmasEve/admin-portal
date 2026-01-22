<?php

namespace App\Modules\Finance\Http\Export;

use App\Models\StudentInvoice;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Illuminate\Http\Request;

class InvoiceExport implements FromQuery, WithHeadings, WithMapping
{
    protected $filters;

    public function __construct(array $filters)
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = StudentInvoice::query()
            ->with(['student', 'semester'])
            ->latest();

        if ($campusId = app('campus')?->id) {
            $query->forCampus($campusId);
        }

        if (!empty($this->filters['semester_id'])) {
            $query->forSemester($this->filters['semester_id']);
        }

        if (!empty($this->filters['search'])) {
            $query->search($this->filters['search']);
        }

        if (!empty($this->filters['status']) && $this->filters['status'] !== 'all') {
            $query->filterByStatus($this->filters['status']);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'Invoice #',
            'Student Name',
            'Student ID',
            'Semester',
            'Total Amount',
            'Paid Amount',
            'Balance',
            'Status',
            'Due Date',
        ];
    }

    public function map($invoice): array
    {
        return [
            $invoice->invoice_number,
            $invoice->student->full_name,
            $invoice->student->student_id,
            $invoice->semester->name,
            $invoice->total_amount,
            $invoice->paid_amount,
            $invoice->outstanding_balance,
            $invoice->real_time_status,
            $invoice->due_date?->format('Y-m-d'),
        ];
    }
}
