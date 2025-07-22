<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;

class ApiExceptionHandler
{
    /**
     * Handle API exceptions and return appropriate JSON responses
     */
    public function handle(Throwable $exception, Request $request): JsonResponse
    {
        // Log the exception for monitoring
        $this->logException($exception, $request);

        // Handle specific exception types
        return match (true) {
            $exception instanceof ValidationException => $this->handleValidationException($exception),
            $exception instanceof AuthenticationException => $this->handleAuthenticationException($exception),
            $exception instanceof AccessDeniedHttpException => $this->handleAuthorizationException($exception),
            $exception instanceof ModelNotFoundException => $this->handleModelNotFoundException($exception),
            $exception instanceof NotFoundHttpException => $this->handleNotFoundHttpException($exception),
            $exception instanceof TooManyRequestsHttpException => $this->handleTooManyRequestsException($exception),
            $exception instanceof BusinessLogicException => $this->handleBusinessLogicException($exception),
            default => $this->handleGenericException($exception),
        };
    }

    /**
     * Handle validation exceptions
     */
    protected function handleValidationException(ValidationException $exception): JsonResponse
    {
        return ApiResponse::validationError(
            $exception->errors(),
            'The given data was invalid'
        );
    }

    /**
     * Handle authentication exceptions
     */
    protected function handleAuthenticationException(AuthenticationException $exception): JsonResponse
    {
        return ApiResponse::authenticationError(
            $exception->getMessage() ?: 'Authentication required'
        );
    }

    /**
     * Handle authorization exceptions
     */
    protected function handleAuthorizationException(AccessDeniedHttpException $exception): JsonResponse
    {
        return ApiResponse::authorizationError(
            $exception->getMessage() ?: 'Access denied'
        );
    }

    /**
     * Handle model not found exceptions
     */
    protected function handleModelNotFoundException(ModelNotFoundException $exception): JsonResponse
    {
        $model = class_basename($exception->getModel());
        return ApiResponse::notFound(
            "The requested {$model} was not found"
        );
    }

    /**
     * Handle not found HTTP exceptions
     */
    protected function handleNotFoundHttpException(NotFoundHttpException $exception): JsonResponse
    {
        return ApiResponse::notFound(
            'The requested resource was not found'
        );
    }

    /**
     * Handle too many requests exceptions
     */
    protected function handleTooManyRequestsException(TooManyRequestsHttpException $exception): JsonResponse
    {
        $retryAfter = $exception->getHeaders()['Retry-After'] ?? null;
        $message = $retryAfter 
            ? "Too many requests. Try again in {$retryAfter} seconds."
            : 'Too many requests. Please try again later.';

        return ApiResponse::rateLimitError($message);
    }

    /**
     * Handle business logic exceptions
     */
    protected function handleBusinessLogicException(BusinessLogicException $exception): JsonResponse
    {
        return ApiResponse::businessLogicError(
            $exception->getMessage(),
            $exception->getErrors()
        );
    }

    /**
     * Handle generic exceptions
     */
    protected function handleGenericException(Throwable $exception): JsonResponse
    {
        // Don't expose internal errors in production
        $message = app()->environment('production') 
            ? 'An unexpected error occurred'
            : $exception->getMessage();

        return ApiResponse::serverError($message);
    }

    /**
     * Log exception for monitoring
     */
    protected function logException(Throwable $exception, Request $request): void
    {
        $context = [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'user_id' => $request->user()?->id,
            'ip' => $request->ip(),
        ];

        // Log validation errors as warnings, others as errors
        if ($exception instanceof ValidationException) {
            Log::channel('api')->warning('API Validation Error', $context);
        } elseif ($exception instanceof AuthenticationException || 
                  $exception instanceof AccessDeniedHttpException) {
            Log::channel('api')->warning('API Authorization Error', $context);
        } else {
            Log::channel('api')->error('API Exception', array_merge($context, [
                'trace' => $exception->getTraceAsString(),
            ]));
        }
    }
}
