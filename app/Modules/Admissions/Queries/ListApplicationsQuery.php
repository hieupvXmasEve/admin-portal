<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Queries;

use App\Models\StudentApplication;
use App\Modules\Admissions\Actions\ExportApplicationsAction;
use App\Shared\Contracts\Upload\ApplicationDocumentCatalogReader;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class ListApplicationsQuery
{
    /** Select-driven filter fields: exact match against a validated string. */
    private const SELECT_FILTERS = [
        'gender', 'ethnicity', 'religion', 'crm_major', 'scholarship',
        'pathway_gateway', 'graduation_year', 'gpa_type', 'province', 'english_test_type',
    ];

    /** Free-text filter fields: `like` match, wildcards escaped. */
    private const LIKE_FILTERS = ['school', 'birth_place'];

    /**
     * Every advanced-filter key beyond search/status/intake, so
     * {@see ExportApplicationsAction} can pull
     * exactly this set from its request data — one whitelist, not a second one
     * that can drift from the first.
     */
    public const ADVANCED_FILTER_KEYS = [
        ...self::SELECT_FILTERS, ...self::LIKE_FILTERS,
        'synced', 'gpa_min', 'gpa_max', 'overall_min', 'overall_max', 'paid_min', 'paid_max',
    ];

    public function __construct(
        private readonly GetApplicationConversionReadinessQuery $readinessQuery,
        private readonly ApplicationDocumentCatalogReader $documentCatalog,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function handle(array $filters, ?string $campusCode): LengthAwarePaginator
    {
        $query = StudentApplication::query()->with([
            'student:id,student_id,full_name',
            'documents' => fn ($query) => $query->orderBy('page_index')->orderBy('id'),
            'guardians',
            'academicScores',
        ]);

        // Fail closed: a null campus code means the session's campus binding
        // could not be resolved (e.g. a stale/deleted campus id), not "show
        // every campus". See ADR risk: campus scoping must not fail open.
        if ($campusCode === null) {
            $query->whereNull('id');
        } else {
            $query->where('campus_code', $campusCode);
        }

        $this->scopeFilters($query, $filters);

        return $query->orderBy($filters['sort'], $filters['direction'])->paginate($filters['per_page'])->withQueryString()->through(
            fn (StudentApplication $application): array => [
                'id' => $application->id, 'full_name' => $application->full_name, 'student_code' => $application->student_code, 'email' => $application->email, 'national_id' => $application->national_id, 'phone' => $application->phone, 'intended_program' => $application->intended_program, 'intake' => $application->intake, 'status' => $application->status, 'created_at' => $application->created_at, 'student' => $application->student,
                'primary_guardian_name' => $application->guardians->firstWhere('is_primary', true)?->full_name, 'primary_guardian_relationship' => $application->guardians->firstWhere('is_primary', true)?->relationship, 'primary_guardian_phone' => $application->guardians->firstWhere('is_primary', true)?->phone,
                // Both parents, not just whichever is primary — a staff member
                // scanning the list wants father AND mother contacts, not one.
                'father_guardian_name' => $application->guardians->firstWhere('relationship', 'father')?->full_name, 'father_guardian_phone' => $application->guardians->firstWhere('relationship', 'father')?->phone,
                'mother_guardian_name' => $application->guardians->firstWhere('relationship', 'mother')?->full_name, 'mother_guardian_phone' => $application->guardians->firstWhere('relationship', 'mother')?->phone,
                // Full CRM parity, per user request 2026-08-14: every scalar CRM
                // field synced onto student_applications is now surfaced. Only
                // intended_specialization, sut_id, and study_link_status stay
                // excluded (0/395 fill, no CRM contract writes them).
                'gender' => $application->gender, 'ethnicity' => $application->ethnicity, 'address' => $application->address,
                'birth_day' => $application->birth_day, 'birth_month' => $application->birth_month, 'birth_year' => $application->birth_year,
                'english_test_type' => $application->english_test_type, 'exam_date' => $application->exam_date, 'overall' => $application->overall,
                'listening' => $application->listening, 'reading' => $application->reading, 'writing' => $application->writing, 'speaking' => $application->speaking,
                'crm_campus' => $application->crm_campus, 'crm_major' => $application->crm_major, 'province' => $application->province,
                'new_province' => $application->new_province, 'new_street' => $application->new_street, 'new_ward' => $application->new_ward,
                'permanent_address' => $application->permanent_address, 'birth_place' => $application->birth_place, 'nationality' => $application->nationality, 'religion' => $application->religion, 'id_card_place_of_issue' => $application->id_card_place_of_issue, 'school' => $application->school, 'graduation_year' => $application->graduation_year, 'gpa' => $application->gpa, 'gpa_type' => $application->gpa_type, 'scholarship' => $application->scholarship, 'pathway_gateway' => $application->pathway_gateway, 'uu_dai_gc' => $application->uu_dai_gc, 'crm_paid_amount' => $application->crm_paid_amount, 'registration_form' => $application->registration_form, 'last_synced_at' => $application->last_synced_at,
                // Keyed by subject_code (school-report + national-exam); absence
                // of a key means "not reported" (see ApplicationAcademicScore).
                'academic_scores' => $application->academicScores->pluck('score', 'subject_code'),
                'documents_by_type' => $application->documents->groupBy('file_type_code')->map(fn ($documents) => $documents->map(fn ($document): array => ['id' => $document->id, 'link' => $document->link, 'original_name' => $document->original_name, 'page_index' => $document->page_index])->values()),
                // Only a pending application can ever be approved, so readiness
                // is only meaningful — and only computed — for those rows.
                'conversion_readiness' => $application->isPending() ? $this->readinessQuery->handle($application) : null,
            ],
        );
    }

    /** @return array{document_types: mixed, intakes: mixed} */
    public function filters(?string $campusCode): array
    {
        return [
            'document_types' => collect($this->documentCatalog->activeOrdered())
                ->map(fn ($type): array => ['code' => $type->code, 'name' => $type->name]),
            'intakes' => StudentApplication::query()->when($campusCode !== null, fn ($query) => $query->where('campus_code', $campusCode))->whereNotNull('intake')->where('intake', '!=', '')->distinct()->orderBy('intake')->pluck('intake'),
        ];
    }

    /**
     * Advanced-filter option lists (select fields only; `school`/`birth_place`
     * are free-text and have no option list). Campus-pinned so option values
     * from another campus never leak into this campus's filter panel — and, per
     * the fail-closed rule above, a null campus yields empty lists rather than
     * every campus's values.
     *
     * @return array<string, Collection<int, string>>
     */
    public function filterOptions(?string $campusCode): array
    {
        $options = [];
        foreach (self::SELECT_FILTERS as $column) {
            $options[$column] = $this->distinctOptions($column, $campusCode);
        }

        return $options;
    }

    /**
     * The full filter set — search, status, intake, and every advanced-filter
     * group — as one method shared by the list query and
     * {@see ExportApplicationsAction}, so the
     * export can never drift from what the list applies (D11: one code path).
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilters(Builder $query, array $filters): void
    {
        // Sentinel contract: absent / null / '' / 'all' all mean "no filter" —
        // so a stale bundle or bookmarked URL that still sends `status=all`
        // (pre-normalization) keeps working. '' never actually reaches the
        // server (useDataTable.buildParams() skips it), but null-safety here
        // costs nothing and forecloses the whole class of deploy-skew bugs.
        if (! empty($filters['search'])) {
            $query->where(fn ($query) => $query->where('full_name', 'like', '%'.$filters['search'].'%')->orWhere('email', 'like', '%'.$filters['search'].'%')->orWhere('student_code', 'like', '%'.$filters['search'].'%')->orWhere('national_id', 'like', '%'.$filters['search'].'%')->orWhere('phone', 'like', '%'.$filters['search'].'%'));
        }
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        $intake = $filters['intake'] ?? null;
        if ($intake !== null && $intake !== '' && $intake !== 'all') {
            $query->where('intake', $intake);
        }

        $this->applyFilters($query, $filters);
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        foreach (self::SELECT_FILTERS as $field) {
            $value = $filters[$field] ?? null;
            if ($value !== null && $value !== '' && $value !== 'all') {
                $query->where($field, $value);
            }
        }

        foreach (self::LIKE_FILTERS as $field) {
            $value = $filters[$field] ?? null;
            if ($value !== null && $value !== '') {
                $query->where($field, 'like', '%'.$this->escapeLike($value).'%');
            }
        }

        $synced = $filters['synced'] ?? null;
        if ($synced === 'synced') {
            $query->whereNotNull('last_synced_at');
        } elseif ($synced === 'not_synced') {
            $query->whereNull('last_synced_at');
        }

        // D12: 0.00 means "no data" for gpa/overall, so a range filter must
        // exclude zero rows explicitly rather than let a positive minimum hide
        // them incidentally (a min of 0 would otherwise sweep them back in).
        $this->applyRange($query, 'gpa', $filters['gpa_min'] ?? null, $filters['gpa_max'] ?? null, excludeZero: true);
        $this->applyRange($query, 'overall', $filters['overall_min'] ?? null, $filters['overall_max'] ?? null, excludeZero: true);
        $this->applyRange($query, 'crm_paid_amount', $filters['paid_min'] ?? null, $filters['paid_max'] ?? null, excludeZero: false);
    }

    private function applyRange(Builder $query, string $column, mixed $min, mixed $max, bool $excludeZero): void
    {
        $hasMin = $min !== null && $min !== '';
        $hasMax = $max !== null && $max !== '';
        if (! $hasMin && ! $hasMax) {
            return;
        }
        if ($excludeZero) {
            $query->where($column, '>', 0);
        }
        if ($hasMin) {
            $query->where($column, '>=', $min);
        }
        if ($hasMax) {
            $query->where($column, '<=', $max);
        }
    }

    /**
     * @return Collection<int, string>
     */
    private function distinctOptions(string $column, ?string $campusCode): Collection
    {
        if ($campusCode === null) {
            return collect();
        }

        return StudentApplication::query()
            ->where('campus_code', $campusCode)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column);
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\%_');
    }
}
