<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\StudentActionLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UploadActionAttachmentAction
{
    /**
     * Add attachments to an existing student action log.
     *
     * @param  StudentActionLog  $actionLog  The action log to add attachments to
     * @param  array  $data  Data including attachment_ids
     * @return StudentActionLog The updated action log
     */
    public static function run(StudentActionLog $actionLog, array $data): StudentActionLog
    {
        return DB::transaction(function () use ($actionLog, $data) {
            $attachmentIds = $data['attachment_ids'] ?? [];

            if (! empty($attachmentIds)) {
                // Sync without detaching - add new attachments
                $actionLog->attachments()->syncWithoutDetaching($attachmentIds);

                // Update missing_documents flag if requested
                if (isset($data['mark_documents_complete']) && $data['mark_documents_complete']) {
                    $actionLog->update(['missing_documents' => false]);
                }
            }

            Log::info("Attachments added to student action log", [
                'action_log_id' => $actionLog->id,
                'student_id' => $actionLog->student_id,
                'attachment_count' => count($attachmentIds),
            ]);

            return $actionLog->load('attachments');
        });
    }
}
