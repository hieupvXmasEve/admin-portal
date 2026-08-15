<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Upload;

use App\Shared\Contracts\Upload\DTO\UploadRecordSummary;

/**
 * Batch, owning-entity-keyed reads of upload attachments for consumers that
 * cannot import App\Modules\Upload\Models\UploadRecord directly.
 *
 * Deliberately keyed by the owning reply/response id rather than upload
 * record id: a caller only ever has reply/response ids it is already
 * authorized to see (they came from a record it loaded), whereas an
 * id-list-of-upload-ids signature would invite passing arbitrary/unverified
 * upload ids and bypass UploadRecordPolicy's owner/admin/public-context
 * checks.
 */
interface UploadRecordReader
{
    /**
     * Assumes at most one upload record per reply (matches the current
     * schema — no unique constraint enforces it, but nothing writes a
     * second one). If a reply ever legitimately gains more than one, the
     * implementation keeps an arbitrary single row per key rather than
     * erroring.
     *
     * @param  list<int>  $replyIds
     * @return array<int, UploadRecordSummary> keyed by query_replies.id; a
     *                                         reply with no attachment is
     *                                         simply absent from the map
     */
    public function byReplyIds(array $replyIds): array;

    /**
     * @param  list<int>  $responseIds
     * @return array<int, list<UploadRecordSummary>> keyed by responses.id; a
     *                                               response with no
     *                                               attachments maps to []
     */
    public function byResponseIds(array $responseIds): array;
}
