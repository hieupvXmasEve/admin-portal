<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Identity\Actions\ImpersonateLecturerAction;
use App\Modules\Identity\Actions\ImpersonateStudentAction;
use App\Modules\Identity\Http\Requests\Identity\AdminLecturerImpersonationRequest;
use App\Modules\Identity\Http\Requests\Identity\AdminStudentImpersonationRequest;
use App\Modules\Identity\Queries\GetImpersonationSessionsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class ImpersonationController extends Controller
{
    public function __construct(private readonly GetImpersonationSessionsQuery $sessions) {}

    public function impersonateStudent(AdminStudentImpersonationRequest $request): JsonResponse
    {
        $administrator = $this->administrator($request);

        try {
            return ApiResponse::success(
                data: ImpersonateStudentAction::run([
                    ...$request->validated(),
                    'administrator' => $administrator,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]),
                message: 'Student impersonation token generated successfully',
            );
        } catch (\DomainException $exception) {
            return ApiResponse::notFound($exception->getMessage());
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::validationError(
                ['impersonation' => [$exception->getMessage()]],
                $exception->getMessage(),
            );
        } catch (\Throwable $exception) {
            Log::error('Admin student impersonation failed', ['admin_id' => $administrator['id'], 'request_email' => $request->input('email'), 'error' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()]);

            return ApiResponse::serverError('Failed to generate impersonation token');
        }
    }

    public function studentSessions(Request $request): JsonResponse
    {
        return ApiResponse::success(data: $this->sessions->handle($this->administrator($request)), message: 'Impersonation sessions retrieved successfully');
    }

    public function impersonateLecturer(AdminLecturerImpersonationRequest $request): JsonResponse
    {
        $administrator = $this->administrator($request);

        try {
            return ApiResponse::success(
                data: ImpersonateLecturerAction::run([
                    ...$request->validated(),
                    'administrator' => $administrator,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]),
                message: 'Lecturer impersonation token generated successfully',
            );
        } catch (\DomainException $exception) {
            return ApiResponse::notFound($exception->getMessage());
        } catch (\InvalidArgumentException $exception) {
            return ApiResponse::validationError([$exception->getMessage()]);
        } catch (\Throwable $exception) {
            Log::error('Admin lecturer impersonation failed', ['admin_id' => $administrator['id'], 'request_email' => $request->input('email'), 'error' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()]);

            return ApiResponse::serverError('Failed to generate impersonation token');
        }
    }

    public function lecturerSessions(Request $request): JsonResponse
    {
        return ApiResponse::success(data: $this->sessions->handle($this->administrator($request)), message: 'Impersonation sessions retrieved successfully');
    }

    /** @return array{id:int, name:string, email:string} */
    private function administrator(Request $request): array
    {
        $administrator = $request->user();

        return ['id' => (int) $administrator->id, 'name' => (string) $administrator->name, 'email' => (string) $administrator->email];
    }
}
