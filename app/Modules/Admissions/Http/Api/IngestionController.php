<?php

declare(strict_types=1);

namespace App\Modules\Admissions\Http\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ApplicationDocument;
use App\Models\ApplicationGuardian;
use App\Models\StudentApplication;
use App\Modules\Admissions\Actions\UpsertCrmApplicationAction;
use App\Modules\Admissions\Http\Requests\IngestApplicationRequest;
use App\Services\Admissions\Exceptions\ApplicationFrozenException;
use App\Shared\Support\Admissions\AdmissionsIngestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class IngestionController extends Controller
{
    public function __construct(private readonly UpsertCrmApplicationAction $upsertApplication) {}

    public function ping(Request $request): JsonResponse
    {
        return ApiResponse::success(
            data: ['service' => 'admissions-ingestion', 'ability' => AdmissionsIngestion::ABILITY],
            message: 'pong',
        );
    }

    public function upsertApplication(IngestApplicationRequest $request): JsonResponse
    {
        try {
            $result = $this->upsertApplication->handle($request->validated());
        } catch (ApplicationFrozenException) {
            return ApiResponse::conflict('This application is frozen at approval and can no longer be updated via ingestion.');
        }

        return ApiResponse::success(
            data: $this->present($result['application']),
            message: $result['created'] ? 'Application created.' : 'Application updated.',
            status: $result['created'] ? Response::HTTP_CREATED : Response::HTTP_OK,
        );
    }

    /** @return array<string, mixed> */
    private function present(StudentApplication $application): array
    {
        return [
            'id' => $application->id,
            'crm_admission_id' => $application->crm_admission_id,
            'student_code' => $application->student_code,
            'status' => $application->status,
            'guardians' => $application->guardians->map(fn (ApplicationGuardian $guardian): array => [
                'id' => $guardian->id, 'full_name' => $guardian->full_name, 'is_primary' => $guardian->is_primary,
            ])->all(),
            'documents' => $application->documents->map(fn (ApplicationDocument $document): array => [
                'id' => $document->id, 'crm_file_id' => $document->crm_file_id, 'file_type_code' => $document->file_type_code,
            ])->all(),
        ];
    }
}
