<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions;

use App\Models\StudentActionLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class UpdateStudentActionAction
{
    /**
     * Update an existing student action log.
     *
     * @param  array{action_log_id: int, fields: array<string, mixed>}  $data
     */
    public static function run(array $data): StudentActionLog
    {
        $actionLog = StudentActionLog::query()->findOrFail($data['action_log_id']);
        $fields = $data['fields'];

        return DB::transaction(function () use ($actionLog, $fields) {
            // Filter updateable fields
            $fillable = [
                'reason',
                'notes',
                'signed_at',
                'decision_number',
                'decision_signed_at',
                'decision_signer',
                'decision_id',
                'missing_documents',
                // Semester fields
                'from_semester_id',
                'return_semester_id',
                'egc_defer_from_block_number',
                'intended_intake_semester_id',
                'dropout_semester_id',
                'effective_semester_id',
                // Campus transfer fields
                'from_campus_id',
                'to_campus_id',
                'effective_at',
            ];

            // Only update fields that are present in data and fillable
            $updateData = array_intersect_key($fields, array_flip($fillable));

            $actionLog->update($updateData);

            // Sync attachments if provided
            if (isset($fields['attachment_ids'])) {
                $actionLog->attachments()->sync($fields['attachment_ids']);
            }

            Log::info('Student action updated', [
                'student_id' => $actionLog->student_id,
                'action_log_id' => $actionLog->id,
                'updated_by' => auth()->id(),
            ]);

            return $actionLog->load(['student', 'changedBy', 'attachments', 'decision']);
        });
    }
}
