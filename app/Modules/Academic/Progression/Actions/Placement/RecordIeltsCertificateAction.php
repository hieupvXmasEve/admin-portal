<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions\Placement;

use App\Enums\AcademicProgressionEventType;
use App\Enums\ProgressionTriggerSource;
use App\Models\AcademicProgressionEvent;
use App\Models\IeltsCertificate;
use App\Models\Student;
use App\Shared\Contracts\DomainEvents\DomainEvent;
use App\Shared\Contracts\DomainEvents\DomainEventPublisher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class RecordIeltsCertificateAction
{
    /** @param array{student_id:int, overall_score:float, semester_id:int, upload_record_id?:int|null, missing_documents?:bool, issue_date?:string|null, notes?:string|null, created_by_user_id?:int|null} $data */
    public static function run(array $data): IeltsCertificate
    {
        $student = Student::query()->findOrFail($data['student_id']);
        $userId = $data['created_by_user_id'] ?? Auth::id();

        return DB::transaction(function () use ($data, $student, $userId): IeltsCertificate {
            $missingDocuments = empty($data['upload_record_id']) || (bool) ($data['missing_documents'] ?? false);
            $certificate = IeltsCertificate::query()->create([
                'student_id' => $student->id,
                'overall_score' => $data['overall_score'],
                'submitted_at' => now(),
                'upload_record_id' => $data['upload_record_id'] ?? null,
                'missing_documents' => $missingDocuments,
                'issue_date' => $data['issue_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            AcademicProgressionEvent::query()->create([
                'student_id' => $student->id,
                'event_type' => AcademicProgressionEventType::IELTS_RECORDED,
                'semester_id' => $data['semester_id'],
                'effective_at' => now(),
                'trigger_source' => ProgressionTriggerSource::IELTS,
                'created_by_user_id' => $userId,
                'ielts_certificate_id' => $certificate->id,
                'notes' => sprintf('IELTS score %s recorded%s', $certificate->overall_score, $missingDocuments ? ' (missing documents)' : ''),
            ]);
            app(DomainEventPublisher::class)->publishAfterCommit(new DomainEvent(
                name: 'academic.ielts_recorded',
                deduplicationKey: implode(':', ['academic.ielts_recorded', 'certificate', $certificate->id]),
                occurredAt: CarbonImmutable::now(),
                aggregateType: 'student',
                aggregateId: (string) $student->id,
                campusId: $student->campus_id,
                actorUserId: $userId,
                payload: ['student_id' => $student->id, 'certificate_id' => $certificate->id, 'overall_score' => $certificate->overall_score],
            ));

            return $certificate->load('uploadRecord');
        });
    }

    public static function updateDocument(IeltsCertificate $certificate, int $uploadRecordId): IeltsCertificate
    {
        $certificate->update(['upload_record_id' => $uploadRecordId, 'missing_documents' => false]);

        return $certificate->fresh(['uploadRecord']);
    }
}
