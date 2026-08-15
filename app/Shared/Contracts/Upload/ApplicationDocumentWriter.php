<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Upload;

/**
 * Raw persistence for CRM-ingested application documents, for consumers
 * that cannot import App\Modules\Upload\Models\ApplicationDocument
 * directly. Orchestration (shape detection, field mapping, catalog name
 * resolution) stays with the caller — this contract only writes.
 */
interface ApplicationDocumentWriter
{
    /**
     * Upsert a document scoped to its owning application, keyed by
     * (crm_file_id, student_application_id) rather than crm_file_id alone.
     * $fields may not override the match key — an implementation must strip
     * `crm_file_id`/`student_application_id` from $fields before writing.
     *
     * crm_file_id is globally unique in storage. Throws
     * ApplicationDocumentConflictException if it already belongs to a
     * DIFFERENT application: silently creating a second row would hit that
     * unique constraint, and silently updating the existing row would
     * re-point another application's document onto this one. Also throws
     * ApplicationDocumentConflictException if $fields['link'] is present and
     * is not an http(s) URL (it renders as a raw href in staff-facing UI).
     *
     * @param  array<string, mixed>  $fields
     */
    public function upsert(int $applicationId, string $crmFileId, array $fields): void;

    /**
     * Delete every document for the application whose crm_file_id starts
     * with $prefix and is not in $keepCrmFileIds — the reconciliation half
     * of a "replace this application's synced set" sync (e.g. NE pull sync
     * scopes its own documents under an "ne:" prefix).
     *
     * @param  list<string>  $keepCrmFileIds
     */
    public function deleteStalePrefixed(int $applicationId, string $prefix, array $keepCrmFileIds): void;
}
