<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Api;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Platform\Actions\UpdateSystemConfigurationAction;
use App\Modules\Platform\Actions\UploadSystemConfigurationFileAction;
use App\Modules\Platform\Http\Requests\SystemConfiguration\UpdateSystemConfigurationRequest;
use App\Modules\Platform\Http\Requests\SystemConfiguration\UploadSystemConfigurationFileRequest;
use App\Modules\Platform\Queries\GetPublicSystemConfigurationQuery;
use Illuminate\Http\JsonResponse;

final class SystemConfigurationController extends Controller
{
    public function __construct(private readonly GetPublicSystemConfigurationQuery $configuration) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success(
            $this->configuration->handle(),
            message: 'System configuration retrieved successfully',
        );
    }

    public function show(string $key): JsonResponse
    {
        $value = $this->configuration->value($key);

        if ($value === null) {
            return ApiResponse::notFound('Configuration key not found');
        }

        return ApiResponse::success(
            [$key => $value],
            message: 'Configuration value retrieved successfully',
        );
    }

    public function update(UpdateSystemConfigurationRequest $request): JsonResponse
    {
        return ApiResponse::success(
            UpdateSystemConfigurationAction::run($request->validated()),
            message: 'System configuration updated successfully',
        );
    }

    public function upload(UploadSystemConfigurationFileRequest $request): JsonResponse
    {
        return ApiResponse::success(
            UploadSystemConfigurationFileAction::run([
                'file' => $request->file('file'),
                'configuration_key' => $request->string('config_key')->toString(),
            ]),
            message: 'File uploaded successfully',
        );
    }
}
