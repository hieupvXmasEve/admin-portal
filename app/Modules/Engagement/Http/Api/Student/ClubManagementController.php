<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Api\Student;

use App\Exceptions\BusinessLogicException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Student\ClubManagementResource;
use App\Http\Resources\Api\V1\Student\ClubMemberResource;
use App\Http\Resources\Api\V1\Student\ClubResource;
use App\Http\Responses\ApiResponse;
use App\Models\Club;
use App\Models\ClubMember;
use App\Modules\Engagement\Actions\ClubMembershipOperations;
use App\Modules\Engagement\Actions\ClubOperations;
use App\Modules\Engagement\Http\Requests\RejectMemberRequest;
use App\Modules\Engagement\Http\Requests\UpdateClubRequest;
use App\Modules\Engagement\Http\Requests\UpdateRoleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ClubManagementController extends Controller
{
    public function __construct(
        protected ClubOperations $clubService,
        protected ClubMembershipOperations $membershipService
    ) {}

    /**
     * Get club management dashboard data
     */
    public function managementDashboard(Request $request, Club $club): JsonResponse
    {
        try {
            $student = $request->user();

            // Check authorization - must be president
            if (! $club->isPresident($student->id)) {
                return ApiResponse::authorizationError('You are not authorized to manage this club');
            }

            // Ensure club is on the same campus
            if ($club->campus_id !== $student->campus_id) {
                return ApiResponse::notFound('Club not found');
            }

            $managementData = $this->clubService->getClubManagementData($club, $student->id);

            return ApiResponse::success(
                data: new ClubManagementResource($managementData),
                message: 'Club management data retrieved successfully'
            );
        } catch (BusinessLogicException $e) {
            return ApiResponse::businessLogicError($e->getMessage(), $e->getErrors());
        } catch (\Throwable $e) {
            Log::channel('api')->error('Club management dashboard failed', [
                'club_id' => $club->id,
                'student_id' => $request->user()->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ApiResponse::serverError('Failed to retrieve club management data');
        }
    }

    /**
     * Update club information
     */
    public function update(UpdateClubRequest $request, Club $club): JsonResponse
    {
        try {
            $student = $request->user();

            // Check authorization - must be president
            if (! $club->isPresident($student->id)) {
                return ApiResponse::authorizationError('You are not authorized to update this club');
            }

            // Ensure club is on the same campus
            if ($club->campus_id !== $student->campus_id) {
                return ApiResponse::notFound('Club not found');
            }

            $data = $request->validated();
            $updatedClub = $this->clubService->updateClub($club, $data);

            return ApiResponse::success(
                data: new ClubResource($updatedClub->load(['campus', 'president.student'])),
                message: 'Club information updated successfully'
            );
        } catch (BusinessLogicException $e) {
            return ApiResponse::businessLogicError($e->getMessage(), $e->getErrors());
        } catch (\Throwable $e) {
            Log::channel('api')->error('Club update failed', [
                'club_id' => $club->id,
                'student_id' => $request->user()->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ApiResponse::serverError('Failed to update club information');
        }
    }

    /**
     * List club members and applications
     */
    public function members(Request $request, Club $club): JsonResponse
    {
        try {
            $student = $request->user();

            // Check authorization - must be president
            if (! $club->isPresident($student->id)) {
                return ApiResponse::authorizationError('You are not authorized to view club members');
            }

            // Ensure club is on the same campus
            if ($club->campus_id !== $student->campus_id) {
                return ApiResponse::notFound('Club not found');
            }

            // Get filter parameters
            $status = $request->get('status');
            $role = $request->get('role');
            $perPage = min((int) $request->get('per_page', 15), 50);

            // Build query
            $query = $club->members()
                ->with(['student', 'approver'])
                ->orderBy('status')
                ->orderBy('role')
                ->orderBy('joined_at', 'desc');

            // Apply filters
            if ($status) {
                $query->where('status', $status);
            }

            if ($role) {
                $query->where('role', $role);
            }

            // Paginate results
            $members = $query->paginate($perPage);

            return ApiResponse::paginated(
                $members->setCollection(
                    $members->getCollection()->map(fn ($member) => new ClubMemberResource($member))
                ),
                'Club members retrieved successfully'
            );
        } catch (\Throwable $e) {
            Log::channel('api')->error('Club members retrieval failed', [
                'club_id' => $club->id,
                'student_id' => $request->user()->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ApiResponse::serverError('Failed to retrieve club members');
        }
    }

    /**
     * Approve a membership application
     */
    public function approveMember(Request $request, Club $club, ClubMember $member): JsonResponse
    {
        try {
            $student = $request->user();

            // Check authorization - must be president
            if (! $club->isPresident($student->id)) {
                return ApiResponse::authorizationError('You are not authorized to approve this membership');
            }

            // Ensure club and member match
            if ($member->club_id !== $club->id) {
                return ApiResponse::notFound('Member not found in this club');
            }

            // Ensure club is on the same campus
            if ($club->campus_id !== $student->campus_id) {
                return ApiResponse::notFound('Club not found');
            }

            $approvedMember = $this->membershipService->approveMembership($member, $student->id);

            return ApiResponse::success(
                data: new ClubMemberResource($approvedMember->load(['student', 'club', 'approver'])),
                message: 'Membership application approved successfully'
            );
        } catch (BusinessLogicException $e) {
            return ApiResponse::businessLogicError($e->getMessage(), $e->getErrors());
        } catch (\Throwable $e) {
            Log::channel('api')->error('Member approval failed', [
                'club_id' => $club->id,
                'member_id' => $member->id,
                'student_id' => $request->user()->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ApiResponse::serverError('Failed to approve membership application');
        }
    }

    /**
     * Reject a membership application
     */
    public function rejectMember(RejectMemberRequest $request, Club $club, ClubMember $member): JsonResponse
    {
        try {
            $student = $request->user();

            // Check authorization - must be president
            if (! $club->isPresident($student->id)) {
                return ApiResponse::authorizationError('You are not authorized to reject this membership');
            }

            // Ensure club and member match
            if ($member->club_id !== $club->id) {
                return ApiResponse::notFound('Member not found in this club');
            }

            // Ensure club is on the same campus
            if ($club->campus_id !== $student->campus_id) {
                return ApiResponse::notFound('Club not found');
            }

            $data = $request->validated();
            $rejectedMember = $this->membershipService->rejectMembership(
                $member,
                $student->id,
                $data['rejection_reason'] ?? null
            );

            return ApiResponse::success(
                data: new ClubMemberResource($rejectedMember->load(['student', 'club', 'approver'])),
                message: 'Membership application rejected successfully'
            );
        } catch (BusinessLogicException $e) {
            return ApiResponse::businessLogicError($e->getMessage(), $e->getErrors());
        } catch (\Throwable $e) {
            Log::channel('api')->error('Member rejection failed', [
                'club_id' => $club->id,
                'member_id' => $member->id,
                'student_id' => $request->user()->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ApiResponse::serverError('Failed to reject membership application');
        }
    }

    /**
     * Update member role
     */
    public function updateMemberRole(UpdateRoleRequest $request, Club $club, ClubMember $member): JsonResponse
    {
        try {
            $student = $request->user();

            // Check authorization - must be president
            if (! $club->isPresident($student->id)) {
                return ApiResponse::authorizationError('You are not authorized to update this member\'s role');
            }

            // Ensure club and member match
            if ($member->club_id !== $club->id) {
                return ApiResponse::notFound('Member not found in this club');
            }

            // Ensure club is on the same campus
            if ($club->campus_id !== $student->campus_id) {
                return ApiResponse::notFound('Club not found');
            }

            $data = $request->validated();

            // Update member role
            $updatedMember = $this->membershipService->updateMemberRole(
                $member,
                $data['role'],
                $student->id,
                $data['change_reason'] ?? null
            );

            // Update responsibilities if provided
            if (isset($data['responsibilities'])) {
                $updatedMember->update(['responsibilities' => $data['responsibilities']]);
            }

            return ApiResponse::success(
                data: new ClubMemberResource($updatedMember->fresh(['student', 'club', 'roleHistory'])),
                message: 'Member role updated successfully'
            );
        } catch (BusinessLogicException $e) {
            return ApiResponse::businessLogicError($e->getMessage(), $e->getErrors());
        } catch (\Throwable $e) {
            Log::channel('api')->error('Member role update failed', [
                'club_id' => $club->id,
                'member_id' => $member->id,
                'student_id' => $request->user()->id,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ApiResponse::serverError('Failed to update member role');
        }
    }
}
