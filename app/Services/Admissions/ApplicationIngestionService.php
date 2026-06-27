<?php

declare(strict_types=1);

namespace App\Services\Admissions;

use App\Models\ApplicationDocument;
use App\Models\ApplicationDocumentType;
use App\Models\StudentApplication;
use App\Services\Admissions\Exceptions\ApplicationFrozenException;
use App\Services\ApplicationGuardianService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Create-or-update of a `pending` Application from a CRM ingestion payload
 * (ADR-0004). Never approves/rejects/revokes — those stay staff-only.
 *
 * Idempotency keys: the Application by `crm_admission_id`, each Document by
 * `crm_file_id`. Re-sending the same admission upserts in place rather than
 * duplicating. Updates are allowed only while the Application is `pending`; an
 * update to an `enrolled`/`rejected` Application raises
 * {@see ApplicationFrozenException} (mapped to `409` by the controller) and the
 * whole upsert rolls back, so a frozen record is never partially touched.
 *
 * Guardians have no external key, so the payload's Guardian list replaces the
 * stored set wholesale (idempotent in outcome); Documents do carry a key, so
 * they upsert per `crm_file_id`.
 */
class ApplicationIngestionService
{
    public function __construct(
        private readonly ApplicationGuardianService $guardians,
    ) {}

    /**
     * Upsert an Application and its children from a validated payload.
     *
     * @param  array<string, mixed>  $data
     * @return array{application: StudentApplication, created: bool}
     */
    public function upsert(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $existing = StudentApplication::query()
                ->where('crm_admission_id', $data['crm_admission_id'])
                ->first();

            if ($existing !== null && ! $existing->isPending()) {
                throw new ApplicationFrozenException($existing);
            }

            $created = $existing === null;
            $application = $existing ?? new StudentApplication;

            // Born `pending`; status and audit columns are never CRM-set.
            $application->fill($this->coreAttributes($data));
            $application->save();

            // Children are present-keyed: only touched when the CRM sends them,
            // so a payload that omits `guardians`/`documents` leaves them intact.
            if (array_key_exists('guardians', $data)) {
                $this->syncGuardians($application, $data['guardians'] ?? []);
            }

            if (array_key_exists('documents', $data)) {
                $this->syncDocuments($application, $data['documents'] ?? []);
            }

            return [
                'application' => $application->load('guardians', 'documents'),
                'created' => $created,
            ];
        });
    }

    /**
     * Project the payload onto the Application's core columns, flattening the
     * nested `english_test` object onto the inline test columns.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function coreAttributes(array $data): array
    {
        $core = Arr::only($data, [
            'crm_admission_id',
            'student_code',
            'full_name',
            'gender',
            'ethnicity',
            'birth_day',
            'birth_month',
            'birth_year',
            'national_id',
            'phone',
            'email',
            'address',
            'health_information',
            'campus_code',
            'intended_program',
            'intended_specialization',
            'intake',
            'is_international_applicant',
            'exception_units',
            'sut_id',
            'english_qualifications',
            'study_link_status',
        ]);

        $test = $data['english_test'] ?? [];

        return [
            ...$core,
            'english_test_type' => $test['test_type'] ?? null,
            'exam_date' => $test['exam_date'] ?? null,
            'listening' => $test['listening'] ?? null,
            'reading' => $test['reading'] ?? null,
            'writing' => $test['writing'] ?? null,
            'speaking' => $test['speaking'] ?? null,
            'overall' => $test['overall'] ?? null,
        ];
    }

    /**
     * Replace the Application's Guardian set from the payload. The
     * {@see ApplicationGuardianService} keeps exactly one primary: the first
     * Guardian is primary unless a later one is flagged.
     *
     * @param  array<int, array<string, mixed>>  $guardians
     */
    private function syncGuardians(StudentApplication $application, array $guardians): void
    {
        $application->guardians()->delete();

        foreach ($guardians as $guardian) {
            $this->guardians->create($application, Arr::only($guardian, [
                'full_name',
                'relationship',
                'phone',
                'email',
                'occupation',
                'address',
                'is_primary',
            ]));
        }
    }

    /**
     * Upsert each Document by `crm_file_id`, which is globally unique (the CRM
     * owns it), so a re-send updates the existing row — and re-points it at this
     * Application — rather than colliding with the unique index. `file_type_name`
     * falls back to the mirrored catalog when the payload omits it.
     *
     * @param  array<int, array<string, mixed>>  $documents
     */
    private function syncDocuments(StudentApplication $application, array $documents): void
    {
        if ($documents === []) {
            return;
        }

        $catalogNames = $this->catalogNamesFor($documents);

        foreach ($documents as $document) {
            $code = $document['file_type_code'];

            ApplicationDocument::query()->updateOrCreate(
                ['crm_file_id' => $document['crm_file_id']],
                [
                    'student_application_id' => $application->id,
                    'file_type_code' => $code,
                    'file_type_name' => $document['file_type_name'] ?? $catalogNames[$code] ?? null,
                    'page_index' => $document['page_index'] ?? 0,
                    'original_name' => $document['original_name'] ?? null,
                    'link' => $document['link'],
                    'mime_type' => $document['mime_type'] ?? null,
                    'size' => $document['size'] ?? null,
                    'status' => $document['status'] ?? null,
                ],
            );
        }
    }

    /**
     * Map of document-type code => catalog name for the payload's codes.
     *
     * @param  array<int, array<string, mixed>>  $documents
     * @return array<string, string>
     */
    private function catalogNamesFor(array $documents): array
    {
        $codes = array_values(array_unique(array_column($documents, 'file_type_code')));

        return ApplicationDocumentType::query()
            ->whereIn('code', $codes)
            ->pluck('name', 'code')
            ->all();
    }
}
