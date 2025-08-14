<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SystemConfigUpdateRequest;
use App\Services\SystemConfigService;
use Illuminate\Http\JsonResponse;

class SystemConfigController extends Controller
{
    public function __construct(
        private SystemConfigService $systemConfigService
    ) {}

    /**
     * Get system configuration
     */
    public function index(): JsonResponse
    {
        $config = $this->systemConfigService->getConfig();
        
        return response()->json([
            'success' => true,
            'message' => 'System configuration retrieved successfully',
            'data' => $config
        ]);
    }

    /**
     * Get specific configuration value
     */
    public function show(string $key): JsonResponse
    {
        $value = $this->systemConfigService->get($key);
        
        if ($value === null) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration key not found',
                'data' => null
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Configuration value retrieved successfully',
            'data' => [
                $key => $value
            ]
        ]);
    }

    /**
     * Update system configuration
     */
    public function update(SystemConfigUpdateRequest $request): JsonResponse
    {
        try {
            $success = $this->systemConfigService->updateConfig($request->validated());
            
            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update system configuration',
                    'data' => null
                ], 500);
            }
            
            $updatedConfig = $this->systemConfigService->getConfig();
            
            return response()->json([
                'success' => true,
                'message' => 'System configuration updated successfully',
                'data' => $updatedConfig
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to update system config: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating configuration',
                'data' => null
            ], 500);
        }
    }

    /**
     * Upload file for system configuration
     */
    public function uploadFile(\Illuminate\Http\Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:png,jpg,jpeg,svg,gif', 'max:2048'],
            'config_key' => ['required', 'string', 'in:logo_full,logo_text']
        ]);

        try {
            $result = $this->systemConfigService->uploadFile(
                $request->file('file'),
                $request->input('config_key')
            );
            
            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to upload system config file: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while uploading file: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
