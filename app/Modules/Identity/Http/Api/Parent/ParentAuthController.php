<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Api\Parent;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Actions\ParentLoginAction;
use App\Modules\Identity\Actions\ParentLogoutAction;
use App\Modules\Identity\Actions\ParentGoogleLoginAction;
use App\Modules\Identity\Http\Requests\Identity\ParentLoginRequest;
use App\Modules\Identity\Http\Requests\Identity\GoogleLoginRequest;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentAuthController extends Controller
{
    public function login(ParentLoginRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $data['ip'] = $request->ip();
            $result = ParentLoginAction::run($data);
            return ApiResponse::success($result, [], 'Login successful');
        } catch (\Exception $e) {
            return ApiResponse::authenticationError($e->getMessage());
        }
    }

    public function loginWithGoogle(GoogleLoginRequest $request): JsonResponse
    {
        try {
            $result = ParentGoogleLoginAction::run([
                'id_token' => $request->id_token,
                'ip' => $request->ip(),
                'device_name' => $request->device_name,
                'remember_me' => $request->boolean('remember_me'),
            ]);
            return ApiResponse::success($result, [], 'Google login successful');
        } catch (\Exception $e) {
            return ApiResponse::authenticationError($e->getMessage());
        }
    }

    public function logout(Request $request): JsonResponse
    {
        ParentLogoutAction::run($request);
        return ApiResponse::success(null, [], 'Logged out successfully');
    }
}
