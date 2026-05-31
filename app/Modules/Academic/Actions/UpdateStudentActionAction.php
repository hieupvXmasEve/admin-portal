<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\StudentActionLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateStudentActionAction
{
    /**
     * Update an existing student action log.
     *
     * @param  StudentActionLog  $actionLog  The action log to update
     * @param  array  $data  Updated data
     * @return StudentActionLog The updated action log
     */
    public static function run(StudentActionLog $actionLog, array $data): StudentActionLog
    {
        return DB::transaction(function () use ($actionLog, $data) {
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
            $updateData = array_intersect_key($data, array_flip($fillable));

            $actionLog->update($updateData);

            // Sync attachments if provided
            if (isset($data['attachment_ids'])) {
                $actionLog->attachments()->sync($data['attachment_ids']);
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
