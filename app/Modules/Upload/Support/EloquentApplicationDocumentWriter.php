<?php

declare(strict_types=1);

namespace App\Modules\Upload\Support;

use App\Modules\Upload\Models\ApplicationDocument;
use App\Shared\Contracts\Upload\ApplicationDocumentConflictException;
use App\Shared\Contracts\Upload\ApplicationDocumentWriter;
use Illuminate\Support\Arr;

class EloquentApplicationDocumentWriter implements ApplicationDocumentWriter
{
    public function upsert(int $applicationId, string $crmFileId, array $fields): void
    {
        $belongsToAnotherApplication = ApplicationDocument::query()
            ->where('crm_file_id', $crmFileId)
            ->where('student_application_id', '!=', $applicationId)
            ->exists();

        if ($belongsToAnotherApplication) {
            throw new ApplicationDocumentConflictException("crm_file_id [{$crmFileId}] already belongs to a different application.");
        }

        if (array_key_exists('link', $fields)) {
            $this->assertHttpLink($fields['link']);
        }

        // Strip crm_file_id/student_application_id from the update payload —
        // both are already the match key above; letting a caller pass either
        // in $fields would let it silently re-point the row past the
        // collision check just performed.
        $fields = Arr::except($fields, ['crm_file_id', 'student_application_id']);

        ApplicationDocument::query()->updateOrCreate(
            ['crm_file_id' => $crmFileId, 'student_application_id' => $applicationId],
            $fields,
        );
    }

    public function deleteStalePrefixed(int $applicationId, string $prefix, array $keepCrmFileIds): void
    {
        ApplicationDocument::query()
            ->where('student_application_id', $applicationId)
            ->where('crm_file_id', 'like', addcslashes($prefix, '%_\\').'%')
            ->whereNotIn('crm_file_id', $keepCrmFileIds)
            ->delete();
    }

    /**
     * ADR-0050: link is staff-facing (rendered as a raw href in the admin
     * UI) and CRM-supplied — enforced here, not only at the HTTP validation
     * boundary, because the NE sync caller never passes through
     * IngestApplicationRequest at all (it calls the write path directly).
     */
    private function assertHttpLink(mixed $link): void
    {
        if (! is_string($link) || ! preg_match('#^https?://#i', $link)) {
            throw new ApplicationDocumentConflictException('Document link must be an http(s) URL.');
        }
    }
}
