<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Actions;

use App\Models\ApplicationDocument;
use App\Models\ApplicationDocumentType;
use App\Models\ApplicationGuardian;
use App\Models\StudentApplication;
use App\Modules\Admissions\Models\ApplicationAcademicScore;
use App\Modules\Admissions\Support\ApplicantGuardianManager;
use App\Services\Admissions\Exceptions\ApplicationFrozenException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The live write path for both ingest surfaces:
 *  - **push** (`source` absent/`'push'`): the external CRM ingest contract —
 *    matched by `crm_admission_id`, guardians replace-wholesale (unchanged
 *    contract, relied on by the push caller to *remove* a wrongly attached
 *    parent).
 *  - **ne** (`source === 'ne'`): the CRM NE pull sync — matched by
 *    `student_code` (`crm_admission_id` is always null for this path, per D1),
 *    guardians reconciled father/mother-only, documents keyed on the local
 *    application id with `ne:`-scoped reconciliation, academic scores synced.
 */
final class UpsertCrmApplicationAction
{
    private const SOURCE_NE = 'ne';

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
        $isNe = ($data['source'] ?? 'push') === self::SOURCE_NE;

        return DB::transaction(function () use ($data, $isNe): array {
            $existing = $this->resolveExisting($data);
            if ($existing !== null && ! $existing->isPending()) {
                throw new ApplicationFrozenException($existing);
            }

            $application = $existing ?? new StudentApplication;
            $application->fill($this->attributes($data));
            $application->last_synced_at = $isNe ? now() : $application->last_synced_at;
            $application->save();

            if (array_key_exists('guardians', $data)) {
                $isNe
                    ? $this->reconcileNeGuardians($application, $data['guardians'] ?? [])
                    : $this->syncGuardians($application, $data['guardians'] ?? []);
            }
            if (array_key_exists('documents', $data)) {
                $isNe
                    ? $this->reconcileNeDocuments($application, $data['documents'] ?? [])
                    : $this->syncDocuments($application, $data['documents'] ?? []);
            }
            if ($isNe && array_key_exists('academic_scores', $data)) {
                $this->syncAcademicScores($application, $data['academic_scores'] ?? []);
            }

            return ['application' => $application->load('guardians', 'documents', 'academicScores'), 'created' => $existing === null];
        });
    }

    /** @param array<string, mixed> $data */
    private function resolveExisting(array $data): ?StudentApplication
    {
        $crmAdmissionId = $data['crm_admission_id'] ?? null;

        if (filled($crmAdmissionId)) {
            return StudentApplication::query()->where('crm_admission_id', $crmAdmissionId)->lockForUpdate()->first();
        }

        $studentCode = $data['student_code'] ?? null;

        if (! filled($studentCode)) {
            // Neither key present: `where('student_code', null)` would rewrite
            // to `whereNull` and silently adopt an arbitrary existing row —
            // never allowed to reach that point (finding 1).
            throw new InvalidArgumentException('crm_admission_id or student_code is required.');
        }

        return StudentApplication::query()->where('student_code', $studentCode)->lockForUpdate()->first();
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
            'crm_campus', 'crm_major', 'province', 'new_province', 'new_street', 'new_ward', 'permanent_address',
            'birth_place', 'nationality', 'religion', 'id_card_place_of_issue', 'school', 'graduation_year',
            'gpa', 'gpa_type', 'scholarship', 'pathway_gateway', 'uu_dai_gc', 'crm_paid_amount',
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

    /**
     * Father/mother-only reconciliation (D-decision, finding 9): demote every
     * guardian on this application, upsert each supplied relationship, then
     * promote exactly one. A staff-added guardian under any other relationship
     * label is never deleted or edited — only possibly demoted by step one.
     *
     * No DB uniqueness is enforced on `(application, relationship)` — the
     * manual guardian-add screen legitimately allows two `father` rows (e.g.
     * biological + step-parent), so a schema constraint would break that
     * feature. `updateOrCreate` here just matches the first row for the
     * relationship (Eloquent's normal `first()` semantics); any additional
     * duplicate a staff member added by hand is left alone, same as any
     * other relationship label.
     *
     * A record with neither father nor mother is a no-op: demoting the
     * current primary with nothing to promote would leave the application
     * with guardians but no primary, silently dropping the emergency contact
     * at approval — so this method never touches guardians unless it also
     * has a father/mother row to promote.
     *
     * @param  list<array{relationship: string, full_name: string, phone: string|null, is_primary: bool}>  $guardians
     */
    private function reconcileNeGuardians(StudentApplication $application, array $guardians): void
    {
        $parents = array_values(array_filter($guardians, static fn (array $guardian): bool => in_array($guardian['relationship'], ['father', 'mother'], true)));

        if ($parents === []) {
            return;
        }

        $application->guardians()->where('is_primary', true)->update(['is_primary' => false]);

        $promoteRelationship = null;
        foreach ($parents as $guardian) {
            ApplicationGuardian::query()->updateOrCreate(
                ['student_application_id' => $application->id, 'relationship' => $guardian['relationship']],
                [
                    'full_name' => $guardian['full_name'],
                    'phone' => $guardian['phone'] ?? null,
                    'is_primary' => false,
                ],
            );

            if ($guardian['is_primary'] || $promoteRelationship === null) {
                $promoteRelationship = $guardian['relationship'];
            }
        }

        ApplicationGuardian::query()
            ->where('student_application_id', $application->id)
            ->where('relationship', $promoteRelationship)
            ->update(['is_primary' => true]);
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

    /**
     * `crm_file_id` is keyed on the local application id (never the CRM
     * `student_code`), so a crafted CRM value cannot re-point another
     * application's documents (finding 7). A CRM URL going null must not
     * leave a stale row pointing at a dead file (finding 12) — every `ne:`
     * document not in the just-written set is deleted.
     *
     * @param  list<array{crm_file_id_suffix: string, file_type_code: string, page_index: int, link: string}>  $documents
     */
    private function reconcileNeDocuments(StudentApplication $application, array $documents): void
    {
        $codes = array_values(array_unique(array_column($documents, 'file_type_code')));
        $catalogNames = ApplicationDocumentType::query()->whereIn('code', $codes)->pluck('name', 'code')->all();

        $writtenIds = [];
        foreach ($documents as $document) {
            $crmFileId = "ne:{$application->id}:{$document['crm_file_id_suffix']}";
            $code = $document['file_type_code'];

            ApplicationDocument::query()->updateOrCreate(
                ['crm_file_id' => $crmFileId, 'student_application_id' => $application->id],
                [
                    'file_type_code' => $code,
                    'file_type_name' => $catalogNames[$code] ?? null,
                    'page_index' => $document['page_index'],
                    'link' => $document['link'],
                ],
            );

            $writtenIds[] = $crmFileId;
        }

        ApplicationDocument::query()
            ->where('student_application_id', $application->id)
            ->where('crm_file_id', 'like', 'ne:%')
            ->whereNotIn('crm_file_id', $writtenIds)
            ->delete();
    }

    /**
     * A subject with no CRM score deletes any existing row for it — absence
     * means "not reported", distinct from a reported score of 0.
     *
     * @param  list<array{subject_code: string, score: float, source: string}>  $scores
     */
    private function syncAcademicScores(StudentApplication $application, array $scores): void
    {
        $suppliedSubjects = array_column($scores, 'subject_code');

        foreach ($scores as $score) {
            ApplicationAcademicScore::query()->updateOrCreate(
                ['student_application_id' => $application->id, 'subject_code' => $score['subject_code']],
                ['score' => $score['score'], 'source' => $score['source']],
            );
        }

        $application->academicScores()->whereNotIn('subject_code', $suppliedSubjects)->delete();
    }
}
