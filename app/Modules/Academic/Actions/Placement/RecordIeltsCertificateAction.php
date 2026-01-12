<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions\Placement;

use App\Enums\AcademicProgressionEventType;
use App\Enums\ProgressionTriggerSource;
use App\Models\AcademicProgressionEvent;
use App\Models\IeltsCertificate;
use App\Models\Student;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecordIeltsCertificateAction
{
    /**
     * Record an IELTS certificate for a student.
     *
     * @param array $data {
     *     student_id: int,
     *     overall_score: float,
     *     semester_id: int,
     *     upload_record_id: ?int,
     *     missing_documents: ?bool,
     *     issue_date: ?string,
     *     notes: ?string,
     * }
     */
    public static function run(array $data): IeltsCertificate
    {
        $studentId = $data['student_id'];
        $score = (float) $data['overall_score'];
        $semesterId = $data['semester_id'];
        $userId = $data['created_by_user_id'] ?? Auth::id();

        $student = Student::findOrFail($studentId);

        return DB::transaction(function () use ($student, $score, $semesterId, $data, $userId) {
            $missingDocuments = empty($data['upload_record_id']) || ($data['missing_documents'] ?? false);

            // Create IELTS certificate
            $certificate = IeltsCertificate::create([
                'student_id' => $student->id,
                'overall_score' => $score,
                'submitted_at' => now(),
                'upload_record_id' => $data['upload_record_id'] ?? null,
                'missing_documents' => $missingDocuments,
                'issue_date' => $data['issue_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            // Create IELTS_RECORDED event
            AcademicProgressionEvent::create([
                'student_id' => $student->id,
                'event_type' => AcademicProgressionEventType::IELTS_RECORDED,
                'semester_id' => $semesterId,
                'effective_at' => now(),
                'trigger_source' => ProgressionTriggerSource::IELTS,
                'created_by_user_id' => $userId,
                'ielts_certificate_id' => $certificate->id,
                'notes' => sprintf(
                    'IELTS score %s recorded%s',
                    $score,
                    $missingDocuments ? ' (missing documents)' : ''
                ),
            ]);

            Log::info('IELTS certificate recorded', [
                'student_id' => $student->id,
                'certificate_id' => $certificate->id,
                'score' => $score,
                'missing_documents' => $missingDocuments,
                'created_by' => $userId,
            ]);

            return $certificate->load('uploadRecord');
        });
    }

    /**
     * Update missing_documents flag when file is uploaded.
     */
    public static function updateDocument(
        IeltsCertificate $certificate,
        int $uploadRecordId
    ): IeltsCertificate {
        $certificate->update([
            'upload_record_id' => $uploadRecordId,
            'missing_documents' => false,
        ]);

        Log::info('IELTS certificate document updated', [
            'certificate_id' => $certificate->id,
            'upload_record_id' => $uploadRecordId,
        ]);

        return $certificate->fresh(['uploadRecord']);
    }
}
