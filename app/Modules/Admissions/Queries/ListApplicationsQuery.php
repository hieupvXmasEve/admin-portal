<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Queries;

use App\Models\ApplicationDocumentType;
use App\Models\StudentApplication;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListApplicationsQuery
{
    /** @param array{search: mixed, status: mixed, intake: mixed, per_page: int, sort: string, direction: string} $filters */
    public function handle(array $filters, ?string $campusCode): LengthAwarePaginator
    {
        $query = StudentApplication::query()->with(['student:id,student_id,full_name', 'documents' => fn ($query) => $query->orderBy('page_index')->orderBy('id')]);

        if ($campusCode !== null) {
            $query->where('campus_code', $campusCode);
        }
        if ($filters['search']) {
            $query->where(fn ($query) => $query->where('full_name', 'like', '%'.$filters['search'].'%')->orWhere('email', 'like', '%'.$filters['search'].'%')->orWhere('student_code', 'like', '%'.$filters['search'].'%')->orWhere('national_id', 'like', '%'.$filters['search'].'%')->orWhere('phone', 'like', '%'.$filters['search'].'%'));
        }
        if ($filters['status'] && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        if ($filters['intake'] !== null && $filters['intake'] !== '' && $filters['intake'] !== 'all') {
            $query->where('intake', $filters['intake']);
        }

        return $query->orderBy($filters['sort'], $filters['direction'])->paginate($filters['per_page'])->withQueryString()->through(
            fn (StudentApplication $application): array => [
                'id' => $application->id, 'full_name' => $application->full_name, 'student_code' => $application->student_code, 'email' => $application->email, 'national_id' => $application->national_id, 'phone' => $application->phone, 'intended_program' => $application->intended_program, 'intake' => $application->intake, 'status' => $application->status, 'created_at' => $application->created_at, 'student' => $application->student,
                'documents_by_type' => $application->documents->groupBy('file_type_code')->map(fn ($documents) => $documents->map(fn ($document): array => ['id' => $document->id, 'link' => $document->link, 'original_name' => $document->original_name, 'page_index' => $document->page_index])->values()),
            ],
        );
    }

    /** @return array{document_types: mixed, intakes: mixed} */
    public function filters(?string $campusCode): array
    {
        return [
            'document_types' => ApplicationDocumentType::query()->activeOrdered()->get(['code', 'name']),
            'intakes' => StudentApplication::query()->when($campusCode !== null, fn ($query) => $query->where('campus_code', $campusCode))->whereNotNull('intake')->where('intake', '!=', '')->distinct()->orderBy('intake')->pluck('intake'),
        ];
    }
}
