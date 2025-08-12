<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class ApiResponse
{
    /**
     * Base success response
     */
    public static function success(
        mixed $data = null,
        array $meta = [],
        ?string $message = null,
        int $status = Response::HTTP_OK
    ): JsonResponse {
        $body = [
            'success'   => true,
            'timestamp' => now()->toISOString(),
        ];

        if ($data !== null) {
            $body['data'] = $data;
        }

        if (!empty($meta)) {
            $body['meta'] = $meta;
        }

        if ($message !== null) {
            $body['message'] = $message;
        }

        return response()->json($body, $status);
    }

    /**
     * Base error response (unified envelope)
     */
    public static function error(
        string $message,
        array $errors = [], // each item: ['code' => ?, 'field' => ?, 'detail' => ?]
        int $status = Response::HTTP_BAD_REQUEST
    ): JsonResponse {
        return response()->json([
            'success'   => false,
            'message'   => $message,
            'errors'    => array_values($errors), // normalize to indexed array
            'timestamp' => now()->toISOString(),
        ], $status);
    }

    /**
     * Validation error (422)
     * Accepts Laravel validator errors or normalized array
     */
    public static function validationError(
        array $errors,
        string $message = 'Validation failed'
    ): JsonResponse {
        // Nếu $errors là dạng ["field" => ["msg1","msg2"]], chuyển sang itemized
        $normalized = [];
        foreach ($errors as $field => $messages) {
            foreach ((array)$messages as $detail) {
                $normalized[] = [
                    'code'   => 'VALIDATION_ERROR',
                    'field'  => (string) $field,
                    'detail' => (string) $detail,
                ];
            }
        }

        return self::error($message, $normalized, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Auth/Authorization helpers
     */
    public static function authenticationError(string $message = 'Unauthorized'): JsonResponse
    {
        return self::error($message, [
            ['code' => 'AUTHENTICATION_ERROR', 'field' => null, 'detail' => null],
        ], Response::HTTP_UNAUTHORIZED);
    }

    public static function authorizationError(string $message = 'Forbidden'): JsonResponse
    {
        return self::error($message, [
            ['code' => 'AUTHORIZATION_ERROR', 'field' => null, 'detail' => null],
        ], Response::HTTP_FORBIDDEN);
    }

    /**
     * Not found, server error, business logic, rate limit
     */
    public static function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return self::error($message, [
            ['code' => 'NOT_FOUND', 'field' => null, 'detail' => null],
        ], Response::HTTP_NOT_FOUND);
    }

    public static function serverError(string $message = 'Internal server error'): JsonResponse
    {
        return self::error($message, [
            ['code' => 'SERVER_ERROR', 'field' => null, 'detail' => null],
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public static function businessLogicError(
        string $message,
        array $errors = []
    ): JsonResponse {
        // Nếu caller chỉ truyền message chung, vẫn tạo 1 item code chuẩn
        $errors = empty($errors)
            ? [['code' => 'BUSINESS_LOGIC_ERROR', 'field' => null, 'detail' => null]]
            : $errors;

        return self::error($message, $errors, Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public static function rateLimitError(string $message = 'Too many requests'): JsonResponse
    {
        return self::error($message, [
            ['code' => 'RATE_LIMIT', 'field' => null, 'detail' => null],
        ], Response::HTTP_TOO_MANY_REQUESTS);
    }

    /**
     * Paginated response using the unified meta
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        ?string $message = null
    ): JsonResponse {
        $meta = [
            'page'        => $paginator->currentPage(),
            'per_page'    => $paginator->perPage(),
            'total'       => $paginator->total(),
            'total_pages' => $paginator->lastPage(),
        ];

        return self::success(
            data: $paginator->items(),
            meta: $meta,
            message: $message ?? 'Data retrieved successfully',
            status: Response::HTTP_OK
        );
    }
}
