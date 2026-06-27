<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admissions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admissions\IngestApplicationRequest;
use App\Http\Responses\ApiResponse;
use App\Models\ApplicationDocument;
use App\Models\ApplicationGuardian;
use App\Models\StudentApplication;
use App\Services\Admissions\ApplicationIngestionService;
use App\Services\Admissions\Exceptions\ApplicationFrozenException;
use App\Shared\Support\Admissions\AdmissionsIngestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Admissions-CRM ingestion endpoints (server-to-server, ADR-0004).
 *
 * The hardened route group authenticates a scoped service token, asserts the
 * `admissions:ingest` ability, IP-allowlists, rate-limits, and audit-logs every
 * call. Ingestion only creates/updates `pending` Applications — it never
 * approves/rejects/revokes, which stay staff-only.
 */
class IngestionController extends Controller
{
    public function __construct(
        private readonly ApplicationIngestionService $ingestion,
    ) {}

    /**
     * Health check for the ingestion path. Reaching this means the caller
     * presented a valid token carrying the `admissions:ingest` ability and
     * passed the IP allowlist and rate limiter.
     */
    public function ping(Request $request): JsonResponse
    {
        return ApiResponse::success(
            data: [
                'service' => 'admissions-ingestion',
                'ability' => AdmissionsIngestion::ABILITY,
            ],
            message: 'pong',
        );
    }

    /**
     * Create or update an Application (with its Guardians and Documents) from a
     * CRM payload. Idempotent by `crm_admission_id`; an update to an Application
     * frozen at approval returns `409 Conflict`.
     */
    public function upsertApplication(IngestApplicationRequest $request): JsonResponse
    {
        try {
            $result = $this->ingestion->upsert($request->validated());
        } catch (ApplicationFrozenException) {
            return ApiResponse::conflict(
                'This application is frozen at approval and can no longer be updated via ingestion.'
            );
        }

        return ApiResponse::success(
            data: $this->present($result['application']),
            message: $result['created'] ? 'Application created.' : 'Application updated.',
            status: $result['created'] ? Response::HTTP_CREATED : Response::HTTP_OK,
        );
    }

    /**
     * Confirmation echo for the CRM: the persisted identifiers plus the linked
     * Guardian and Document ids, so the CRM can reconcile what Swinx stored.
     *
     * @return array<string, mixed>
     */
    private function present(StudentApplication $application): array
    {
        return [
            'id' => $application->id,
            'crm_admission_id' => $application->crm_admission_id,
            'student_code' => $application->student_code,
            'status' => $application->status,
            'guardians' => $application->guardians
                ->map(fn (ApplicationGuardian $guardian): array => [
                    'id' => $guardian->id,
                    'full_name' => $guardian->full_name,
                    'is_primary' => $guardian->is_primary,
                ])
                ->all(),
            'documents' => $application->documents
                ->map(fn (ApplicationDocument $document): array => [
                    'id' => $document->id,
                    'crm_file_id' => $document->crm_file_id,
                    'file_type_code' => $document->file_type_code,
                ])
                ->all(),
        ];
    }
}
