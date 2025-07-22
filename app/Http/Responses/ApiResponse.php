<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ApiResponse
{
    /**
     * Create a successful API response
     */
    public static function success(
        mixed $data = null,
        string $message = 'Operation successful',
        int $statusCode = Response::HTTP_OK
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => now()->toISOString(),
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Create an error API response
     */
    public static function error(
        string $message,
        array $errors = [],
        string $errorCode = 'GENERAL_ERROR',
        int $statusCode = Response::HTTP_BAD_REQUEST
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'error_code' => $errorCode,
            'timestamp' => now()->toISOString(),
        ], $statusCode);
    }

    /**
     * Create a validation error response
     */
    public static function validationError(
        array $errors,
        string $message = 'Validation failed'
    ): JsonResponse {
        return self::error(
            $message,
            $errors,
            'VALIDATION_ERROR',
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    /**
     * Create an authentication error response
     */
    public static function authenticationError(
        string $message = 'Authentication failed'
    ): JsonResponse {
        return self::error(
            $message,
            [],
            'AUTHENTICATION_ERROR',
            Response::HTTP_UNAUTHORIZED
        );
    }

    /**
     * Create an authorization error response
     */
    public static function authorizationError(
        string $message = 'Access denied'
    ): JsonResponse {
        return self::error(
            $message,
            [],
            'AUTHORIZATION_ERROR',
            Response::HTTP_FORBIDDEN
        );
    }

    /**
     * Create a not found error response
     */
    public static function notFound(
        string $message = 'Resource not found'
    ): JsonResponse {
        return self::error(
            $message,
            [],
            'NOT_FOUND',
            Response::HTTP_NOT_FOUND
        );
    }

    /**
     * Create a server error response
     */
    public static function serverError(
        string $message = 'Internal server error'
    ): JsonResponse {
        return self::error(
            $message,
            [],
            'SERVER_ERROR',
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }

    /**
     * Create a business logic error response
     */
    public static function businessLogicError(
        string $message,
        array $errors = []
    ): JsonResponse {
        return self::error(
            $message,
            $errors,
            'BUSINESS_LOGIC_ERROR',
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    /**
     * Create a rate limit error response
     */
    public static function rateLimitError(
        string $message = 'Too many requests'
    ): JsonResponse {
        return self::error(
            $message,
            [],
            'RATE_LIMIT_ERROR',
            Response::HTTP_TOO_MANY_REQUESTS
        );
    }

    /**
     * Create a paginated response
     */
    public static function paginated(
        $paginatedData,
        string $message = 'Data retrieved successfully'
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $paginatedData->items(),
            'pagination' => [
                'current_page' => $paginatedData->currentPage(),
                'last_page' => $paginatedData->lastPage(),
                'per_page' => $paginatedData->perPage(),
                'total' => $paginatedData->total(),
                'from' => $paginatedData->firstItem(),
                'to' => $paginatedData->lastItem(),
                'has_more_pages' => $paginatedData->hasMorePages(),
            ],
            'timestamp' => now()->toISOString(),
        ]);
    }
}
