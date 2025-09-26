<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\EventCheckinRequest;
use App\Http\Requests\EventParticipantSearchRequest;
use App\Models\Event;
use App\Models\Student;
use App\Services\EventParticipationService;
use App\Services\QRCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EventCheckinController extends Controller
{
    public function __construct(
        private EventParticipationService $participationService,
        private QRCodeService $qrCodeService
    ) {}

    /**
     * Validate QR code and return student/event information
     */
    public function validateQRCode(Request $request): JsonResponse
    {
        $request->validate([
            'qr_code' => 'required|string',
            'event_id' => 'required|exists:events,id'
        ]);

        try {
            // Validate QR code format
            if (!$this->qrCodeService->isValidQRCodeFormat($request->qr_code)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid QR code format'
                ], 400);
            }

            // Find event by QR code
            $event = $this->qrCodeService->validateQRCode($request->qr_code);

            if (!$event) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid QR code'
                ], 404);
            }

            // Verify this is the correct event
            if ($event->id !== (int) $request->event_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR code does not match selected event'
                ], 400);
            }

            // Check if event allows check-ins
            if (!$event->canCheckIn()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event is not available for check-in at this time'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => 'QR code validated successfully',
                'data' => [
                    'event' => [
                        'id' => $event->id,
                        'title' => $event->title,
                        'location' => $event->location,
                        'start_time' => $event->start_time,
                        'end_time' => $event->end_time,
                        'gold_reward_amount' => $event->gold_reward_amount
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('QR code validation failed', [
                'qr_code' => $request->qr_code,
                'event_id' => $request->event_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to validate QR code'
            ], 500);
        }
    }

    /**
     * Search for student by student ID or name
     */
    public function searchStudent(EventParticipantSearchRequest $request): JsonResponse
    {
        try {
            $query = Student::query();

            // Search by student ID or name
            if ($request->filled('student_id')) {
                $query->where('student_id', 'like', '%' . $request->student_id . '%');
            }

            if ($request->filled('name')) {
                $query->where('full_name', 'like', '%' . $request->name . '%');
            }

            // Limit to same campus as event
            $event = Event::findOrFail($request->event_id);
            $query->where('campus_id', $event->campus_id);

            // Only active students
            $query->where('status', 'active');

            $students = $query->limit(10)->get(['id', 'student_id', 'full_name', 'email', 'campus_id']);

            // Check registration status for each student
            $studentsWithStatus = $students->map(function ($student) use ($event) {
                $participation = $event->getStudentParticipation($student->id);

                return [
                    'id' => $student->id,
                    'student_id' => $student->student_id,
                    'full_name' => $student->full_name,
                    'email' => $student->email,
                    'participation_status' => $participation?->status ?? 'not_registered',
                    'can_check_in' => $participation?->canCheckIn() ?? false,
                    'already_checked_in' => $participation?->isCheckedIn() || $participation?->isCompleted() ?? false
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $studentsWithStatus
            ]);

        } catch (\Exception $e) {
            Log::error('Student search failed', [
                'request' => $request->all(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to search students'
            ], 500);
        }
    }

    /**
     * Check in a student to an event
     */
    public function checkinStudent(EventCheckinRequest $request): JsonResponse
    {
        try {
            $event = Event::findOrFail($request->event_id);
            $student = Student::findOrFail($request->student_id);
            $staff = Auth::user();

            // Collect device information
            $deviceInfo = [
                'user_agent' => $request->header('User-Agent'),
                'ip_address' => $request->ip(),
                'device_type' => $this->detectDeviceType($request->header('User-Agent')),
                'timestamp' => now()->toISOString()
            ];

            // Perform check-in
            $participant = $this->participationService->checkInStudent(
                $event,
                $student,
                $staff,
                $deviceInfo
            );

            return response()->json([
                'success' => true,
                'message' => 'Student checked in successfully',
                'data' => [
                    'participant' => [
                        'id' => $participant->id,
                        'status' => $participant->status,
                        'checkin_time' => $participant->checkin_time,
                        'student' => [
                            'id' => $student->id,
                            'student_id' => $student->student_id,
                            'full_name' => $student->full_name,
                            'email' => $student->email
                        ],
                        'event' => [
                            'id' => $event->id,
                            'title' => $event->title,
                            'gold_reward_amount' => $event->gold_reward_amount
                        ]
                    ]
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => $e->errors()
            ], 422);

        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);

        } catch (\Exception $e) {
            Log::error('Student check-in failed', [
                'event_id' => $request->event_id,
                'student_id' => $request->student_id,
                'staff_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check in student'
            ], 500);
        }
    }

    /**
     * Get event participants with check-in status
     */
    public function getParticipants(Request $request, Event $event): JsonResponse
    {
        try {
            $filters = $request->only(['status', 'search', 'gold_awarded']);
            $perPage = min($request->get('per_page', 15), 50);

            $participants = $this->participationService->getEventParticipants(
                $event,
                $filters,
                $perPage
            );

            return response()->json([
                'success' => true,
                'data' => $participants->items(),
                'meta' => [
                    'current_page' => $participants->currentPage(),
                    'last_page' => $participants->lastPage(),
                    'per_page' => $participants->perPage(),
                    'total' => $participants->total(),
                    'from' => $participants->firstItem(),
                    'to' => $participants->lastItem()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get event participants', [
                'event_id' => $event->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get event participants'
            ], 500);
        }
    }

    /**
     * Get event check-in statistics
     */
    public function getStatistics(Event $event): JsonResponse
    {
        try {
            $statistics = $this->participationService->getEventStatistics($event);

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get event statistics', [
                'event_id' => $event->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get event statistics'
            ], 500);
        }
    }

    /**
     * Detect device type from user agent
     */
    private function detectDeviceType(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'unknown';
        }

        $userAgent = strtolower($userAgent);

        if (strpos($userAgent, 'mobile') !== false || strpos($userAgent, 'android') !== false) {
            return 'mobile';
        }

        if (strpos($userAgent, 'tablet') !== false || strpos($userAgent, 'ipad') !== false) {
            return 'tablet';
        }

        return 'desktop';
    }
}
