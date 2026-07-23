<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Api\Student;

use App\Exceptions\BusinessLogicException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Student\ClubMemberResource;
use App\Http\Resources\Api\V1\Student\ClubResource;
use App\Http\Responses\ApiResponse;
use App\Models\Club;
use App\Modules\Engagement\Actions\ClubMembershipOperations;
use App\Modules\Engagement\Actions\ClubOperations;
use App\Modules\Engagement\Http\Requests\ClubApplicationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClubController extends Controller
{
    public function __construct(
        protected ClubOperations $clubService,
        protected ClubMembershipOperations $membershipService
    ) {}

    /**
     * List available clubs with search/filter
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $student = $request->user();

            // Get search and filter parameters
            $search = $request->get('search');
            $status = $request->get('status', 'active');
            $perPage = min((int) $request->get('per_page', 15), 50);

            // Build query
            $query = Club::query()
                ->where('campus_id', $student->campus_id)
                ->with(['campus', 'president.student', 'members' => function ($query) use ($student) {
                    $query->where('student_id', $student->id);
                }])
                ->withCount('activeMembers');

            // Apply status filter
            if ($status) {
                $query->where('status', $status);
            }

            // Apply search filter
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Order by name
            $query->orderBy('name');

            // Paginate results
            $clubs = $query->paginate($perPage);

            return ApiResponse::paginated(
                $clubs->setCollection(
                    $clubs->getCollection()->map(fn ($club) => new ClubResource($club))
                ),
                'Clubs retrieved successfully'
            );
        } catch (\Throwable $e) {
            Log::channel('api')->error('Club index failed', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ApiResponse::serverError('Failed to retrieve clubs');
        }
    }

    /**
     * Show detailed club information
     */
    public function show(Request $request, Club $club): JsonResponse
    {
        try {
            $student = $request->user();

            // Ensure club is on the same campus
            if ($club->campus_id !== $student->campus_id) {
                return ApiResponse::notFound('Club not found');
            }

            // Load relationships
            $club->load([
                'campus',
                'president.student',
                'members' => function ($query) use ($student) {
                    $query->where('student_id', $student->id);
                },
            ]);

            // Load member count
            $club->loadCount('activeMembers');

            return ApiResponse::success(
                data: new ClubResource($club),
                message: 'Club details retrieved successfully'
            );
        } catch (\Throwable $e) {
            Log::channel('api')->error('Club show failed', [
                'club_id' => $club->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ApiResponse::serverError('Failed to retrieve club details');
        }
    }

    /**
     * Apply for membership in a club
     */
    public function apply(ClubApplicationRequest $request, Club $club): JsonResponse
    {
        try {
            $student = $request->user();

            // Ensure club is on the same campus
            if ($club->campus_id !== $student->campus_id) {
                return ApiResponse::notFound('Club not found');
            }

            $data = $request->validated();

            $membership = $this->membershipService->applyForMembership(
                $club,
                $student->id,
                $data['application_notes'] ?? null
            );

            return ApiResponse::success(
                data: new ClubMemberResource($membership->load(['student', 'club'])),
                message: 'Membership application submitted successfully'
            );
        } catch (BusinessLogicException $e) {
            return ApiResponse::businessLogicError($e->getMessage(), $e->getErrors());
        } catch (\Throwable $e) {
            Log::channel('api')->error('Club application failed', [
                'club_id' => $club->id,
                'student_id' => $request->user()->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ApiResponse::serverError('Failed to submit membership application');
        }
    }

    /**
     * Get student's club memberships
     */
    public function myMemberships(Request $request): JsonResponse
    {
        try {
            $student = $request->user();

            // Get status filter
            $status = $request->get('status');
            $perPage = min((int) $request->get('per_page', 15), 50);

            // Build query for memberships
            $query = $student->clubMemberships()
                ->with(['club.campus', 'club.president.student', 'approver'])
                ->orderBy('status')
                ->orderBy('joined_at', 'desc');

            // Apply status filter if provided
            if ($status) {
                $query->where('status', $status);
            }

            // Paginate results
            $memberships = $query->paginate($perPage);

            return ApiResponse::paginated(
                $memberships->setCollection(
                    $memberships->getCollection()->map(fn ($membership) => new ClubMemberResource($membership))
                ),
                'Club memberships retrieved successfully'
            );
        } catch (\Throwable $e) {
            Log::channel('api')->error('My memberships retrieval failed', [
                'student_id' => $request->user()->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ApiResponse::serverError('Failed to retrieve club memberships');
        }
    }
}
