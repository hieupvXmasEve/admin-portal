<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Actions\Student\GetContextAction;
use App\Actions\Student\UpdateSettingsAction;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class StudentContextController extends Controller
{
    public function index(Request $request, GetContextAction $action): JsonResponse
    {
        $student = $request->user();
        $context = $action->execute($student);

        return ApiResponse::success($context);
    }

    public function updateSettings(Request $request, UpdateSettingsAction $action): JsonResponse
    {
        $request->validate([
            'settings' => 'required|array',
        ]);

        $student = $request->user();
        $settings = $action->execute($student, $request->input('settings'));

        return ApiResponse::success(
            data: ['settings' => $settings->settings],
            message: 'Settings updated successfully.'
        );
    }
}
