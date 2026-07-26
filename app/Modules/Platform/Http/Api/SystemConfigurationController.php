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
        UpdateSystemConfigurationAction::run($request->validated());

        return ApiResponse::success(
            $this->configuration->handle(),
            message: 'System configuration updated successfully',
        );
    }

    public function upload(UploadSystemConfigurationFileRequest $request): JsonResponse
    {
        UploadSystemConfigurationFileAction::run([
            'file' => $request->file('file'),
            'slot' => $request->string('slot')->toString(),
        ]);

        $branding = $this->configuration->handle();
        $slot = $request->string('slot')->toString();
        $urlKey = match ($slot) {
            'logo_full' => 'logo_full_url',
            'logo_text' => 'logo_text_url',
            'favicon' => 'favicon_url',
            'apple_touch_icon' => 'apple_touch_icon_url',
        };

        return ApiResponse::success(
            [
                'path' => $branding[$urlKey],
                'stored_path' => $branding[$urlKey],
                'cache_bust' => $branding['branding_version'],
            ],
            message: 'File uploaded successfully',
        );
    }
}
