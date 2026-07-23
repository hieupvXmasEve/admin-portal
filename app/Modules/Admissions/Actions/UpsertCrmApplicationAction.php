<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Actions;

use App\Models\ApplicationDocument;
use App\Models\ApplicationDocumentType;
use App\Models\StudentApplication;
use App\Modules\Admissions\Support\ApplicantGuardianManager;
use App\Services\Admissions\Exceptions\ApplicationFrozenException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

final class UpsertCrmApplicationAction
{
    public function __construct(private readonly ApplicantGuardianManager $guardians) {}

    /** @param array<string, mixed> $data
     * @return array{application: StudentApplication, created: bool}
     */
    public static function run(array $data): array
    {
        return app(self::class)->handle($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{application: StudentApplication, created: bool}
     */
    public function handle(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $existing = StudentApplication::query()->where('crm_admission_id', $data['crm_admission_id'])->first();
            if ($existing !== null && ! $existing->isPending()) {
                throw new ApplicationFrozenException($existing);
            }

            $application = $existing ?? new StudentApplication;
            $application->fill($this->attributes($data));
            $application->save();

            if (array_key_exists('guardians', $data)) {
                $this->syncGuardians($application, $data['guardians'] ?? []);
            }
            if (array_key_exists('documents', $data)) {
                $this->syncDocuments($application, $data['documents'] ?? []);
            }

            return ['application' => $application->load('guardians', 'documents'), 'created' => $existing === null];
        });
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $core = Arr::only($data, [
            'crm_admission_id', 'student_code', 'full_name', 'gender', 'ethnicity', 'birth_day', 'birth_month', 'birth_year',
            'national_id', 'phone', 'email', 'address', 'health_information', 'campus_code', 'intended_program',
            'intended_specialization', 'intake', 'is_international_applicant', 'exception_units', 'sut_id',
            'english_qualifications', 'study_link_status',
        ]);
        $test = $data['english_test'] ?? [];

        return [...$core,
            'english_test_type' => $test['test_type'] ?? null,
            'exam_date' => $test['exam_date'] ?? null,
            'listening' => $test['listening'] ?? null,
            'reading' => $test['reading'] ?? null,
            'writing' => $test['writing'] ?? null,
            'speaking' => $test['speaking'] ?? null,
            'overall' => $test['overall'] ?? null,
        ];
    }

    /** @param array<int, array<string, mixed>> $guardians */
    private function syncGuardians(StudentApplication $application, array $guardians): void
    {
        $application->guardians()->delete();
        foreach ($guardians as $guardian) {
            $this->guardians->create($application, Arr::only($guardian, [
                'full_name', 'relationship', 'phone', 'email', 'occupation', 'address', 'is_primary',
            ]));
        }
    }

    /** @param array<int, array<string, mixed>> $documents */
    private function syncDocuments(StudentApplication $application, array $documents): void
    {
        if ($documents === []) {
            return;
        }
        $codes = array_values(array_unique(array_column($documents, 'file_type_code')));
        $catalogNames = ApplicationDocumentType::query()->whereIn('code', $codes)->pluck('name', 'code')->all();

        foreach ($documents as $document) {
            $code = $document['file_type_code'];
            ApplicationDocument::query()->updateOrCreate(['crm_file_id' => $document['crm_file_id']], [
                'student_application_id' => $application->id,
                'file_type_code' => $code,
                'file_type_name' => $document['file_type_name'] ?? $catalogNames[$code] ?? null,
                'page_index' => $document['page_index'] ?? 0,
                'original_name' => $document['original_name'] ?? null,
                'link' => $document['link'],
                'mime_type' => $document['mime_type'] ?? null,
                'size' => $document['size'] ?? null,
                'status' => $document['status'] ?? null,
            ]);
        }
    }
}
