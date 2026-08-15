<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Models\Student;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ParentOrStudentAccess
{
    public function __construct(
        private readonly StudentApiAuthorization $studentAuth,
        private readonly ParentStudentAccess $parentAccess,
    ) {}

    /**
     * Handle an incoming request.
     *
     * Explicit replacement for `either:parent.student.access,student.api.auth`.
     * Student → full StudentApiAuthorization chain (active + holds + permissions).
     * Parent (User) → ParentStudentAccess proxy. No exception swallowing; each
     * leg returns its own explicit failure response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::authenticationError('Authentication required');
        }

        if ($user instanceof Student) {
            return $this->studentAuth->handle($request, $next);
        }

        if ($user instanceof User) {
            return $this->parentAccess->handle($request, $next);
        }

        return ApiResponse::authorizationError('Invalid user type');
    }
}
