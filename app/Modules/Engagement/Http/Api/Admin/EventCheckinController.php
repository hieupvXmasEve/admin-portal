<?php

declare(strict_types=1);

namespace App\Modules\Engagement\Http\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Event;
use App\Models\EventParticipant;
use App\Modules\Engagement\Actions\EventParticipationOperations;
use App\Modules\Engagement\Http\Requests\EventCheckinRequest;
use App\Modules\Engagement\Http\Requests\EventParticipantSearchRequest;
use App\Modules\Engagement\Http\Requests\ListEventParticipantsRequest;
use App\Services\QRCodeService;
use App\Shared\Contracts\Academic\StudentLifecycleStatusReader;
use App\Shared\Contracts\StudentRegistry\DTO\StudentReference;
use App\Shared\Contracts\StudentRegistry\StudentReferenceReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class EventCheckinController extends Controller
{
    public function __construct(
        private EventParticipationOperations $participationService,
        private QRCodeService $qrCodeService,
        private StudentReferenceReader $studentReferenceReader,
        private StudentLifecycleStatusReader $studentLifecycleStatusReader,
    ) {}

    /**
     * Search for student by student ID or name
     */
    public function searchStudent(EventParticipantSearchRequest $request): JsonResponse
    {
        try {
            $event = Event::findOrFail($request->event_id);
            $search = (string) ($request->input('student_id') ?? $request->input('name') ?? '');
            $students = $this->studentReferenceReader->search($search, (int) $event->campus_id, 10);
            $statuses = $this->studentLifecycleStatusReader->statusesFor(array_map(
                static fn (StudentReference $student): int => $student->id,
                $students,
            ));

            // Check registration status for each student
            $studentsWithStatus = collect($students)
                ->filter(static fn (StudentReference $student): bool => in_array(
                    $statuses[$student->id] ?? null,
                    ['intake_course', 'intake_pre_uni_gc'],
                    true,
                ))
                ->map(function (StudentReference $student) use ($event) {
                    $participation = $event->getStudentParticipation($student->id);

                    return [
                        'id' => $student->id,
                        'student_id' => $student->studentCode,
                        'full_name' => $student->fullName,
                        'email' => $student->email,
                        'participation_status' => $participation?->status ?? 'not_registered',
                        'can_check_in' => $participation?->canCheckIn() ?? false,
                        'already_checked_in' => $participation?->isCheckedIn() || $participation?->isCompleted() ?? false,
                    ];
                });

            return ApiResponse::success($studentsWithStatus);
        } catch (\Exception $e) {
            Log::error('Student search failed', [
                'request' => $request->all(),
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::serverError('Failed to search students');
        }
    }

    /**
     * Check in a student to an event
     */
    public function checkinStudent(EventCheckinRequest $request): JsonResponse
    {
        try {
            $event = Event::findOrFail($request->event_id);
            $staff = Auth::user();

            // 1. Parse QR Code (supports both participation and student_id formats)
            $qrData = $this->qrCodeService->parseQRCode($request->qr_code);

            if (! $qrData) {
                return ApiResponse::error('Invalid QR code format');
            }

            $student = null;
            $participation = null;

            // 2. Handle based on QR code type
            if ($qrData['type'] === 'participation') {
                // Original flow: QR contains participation info

                // Verify QR belongs to the selected event
                if ($qrData['event_id'] !== (int) $request->event_id) {
                    return ApiResponse::error('QR code is for a different event');
                }

                // Find participation record
                $participation = EventParticipant::find($qrData['participation_id']);

                if (! $participation) {
                    return ApiResponse::notFound('Participation record not found');
                }

                // Validate participation is active
                if (! $participation->isActive()) {
                    return ApiResponse::error('Participation is not active');
                }

                $student = $this->studentReferenceReader->find((int) $participation->student_id);
                if ($student === null) {
                    return ApiResponse::notFound('Student not found');
                }

                // Check if already checked in
                if ($participation->isCheckedIn() || $participation->isCompleted()) {
                    return ApiResponse::error('Student is already checked in', [], 200, [
                        'already_checked_in' => true,
                        'checkin_time' => $participation->checkin_time,
                        'student' => $this->studentPayload($student),
                    ]);
                }

            } elseif ($qrData['type'] === 'student_id') {
                // New flow: QR contains only student_id

                // Find student by student_id
                $student = $this->studentReferenceReader->findByStudentCode(
                    $qrData['student_id_string'],
                    (int) $event->campus_id,
                );

                if (! $student) {
                    return ApiResponse::notFound('Student not found or not in the same campus as event');
                }

                // Validate student is active
                if (! $this->isStudentActive($student->id)) {
                    return ApiResponse::error('Student account is not active');
                }

                // Check if student already has participation
                $existingParticipation = $event->getStudentParticipation($student->id);

                if ($existingParticipation && ($existingParticipation->isCheckedIn() || $existingParticipation->isCompleted())) {
                    return ApiResponse::error('Student is already checked in', [], 200, [
                        'already_checked_in' => true,
                        'checkin_time' => $existingParticipation->checkin_time,
                        'student' => $this->studentPayload($student),
                    ]);
                }

                // Check if event requires registration and student is NOT registered
                if ($event->requiresRegistration()) {
                    if (! $existingParticipation || ! $existingParticipation->isActive()) {
                        return ApiResponse::error('This event requires prior registration. Student must register before check-in.');
                    }
                    // Student is registered, continue to check-in
                }

                // For walk-in events, check capacity
                if (! $event->requiresRegistration() && $event->hasReachedCapacity()) {
                    return ApiResponse::error('Event has reached maximum capacity');
                }
            }

            // 3. Collect device information
            $deviceInfo = [
                'user_agent' => $request->header('User-Agent'),
                'ip_address' => $request->ip(),
                'device_type' => $this->detectDeviceType($request->header('User-Agent')),
                'timestamp' => now()->toISOString(),
            ];

            // 4. Perform check-in (this will auto-register if needed)
            $participant = $this->participationService->checkInStudent(
                $event,
                $student->id,
                $staff,
                $deviceInfo
            );

            return ApiResponse::success([
                'participant' => [
                    'id' => $participant->id,
                    'status' => $participant->status,
                    'checkin_time' => $participant->checkin_time,
                    'student' => $this->studentPayload($student),
                    'event' => [
                        'id' => $event->id,
                        'title' => $event->title,
                        'gold_reward_amount' => $event->gold_reward_amount,
                    ],
                ],
            ], message: 'Student checked in successfully');
        } catch (ValidationException $e) {
            return ApiResponse::validationError($e->errors(), $e->getMessage());
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage());
        } catch (\Exception $e) {
            Log::error('Student check-in failed', [
                'event_id' => $request->event_id,
                'qr_code' => $request->qr_code,
                'staff_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::serverError('Failed to check in student');
        }
    }

    /**
     * Get event participants with check-in status
     */
    public function getParticipants(ListEventParticipantsRequest $request, Event $event): JsonResponse
    {
        try {
            $validated = $request->validated();
            $filters = array_filter([
                'status' => $validated['status'] ?? null,
                'search' => $validated['search'] ?? null,
                'gold_awarded' => $validated['gold_awarded'] ?? null,
            ], static fn (mixed $value): bool => $value !== null);
            $perPage = $validated['per_page'] ?? 15;

            $participants = $this->participationService->getEventParticipants(
                $event,
                $filters,
                $perPage
            );

            return ApiResponse::success(
                $participants->items(),
                [
                    'current_page' => $participants->currentPage(),
                    'last_page' => $participants->lastPage(),
                    'per_page' => $participants->perPage(),
                    'total' => $participants->total(),
                    'from' => $participants->firstItem(),
                    'to' => $participants->lastItem(),
                ],
            );
        } catch (\Exception $e) {
            Log::error('Failed to get event participants', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::serverError('Failed to get event participants');
        }
    }

    /**
     * Get event check-in statistics
     */
    public function getStatistics(Event $event): JsonResponse
    {
        try {
            $statistics = $this->participationService->getEventStatistics($event);

            return ApiResponse::success($statistics);
        } catch (\Exception $e) {
            Log::error('Failed to get event statistics', [
                'event_id' => $event->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::serverError('Failed to get event statistics');
        }
    }

    /**
     * Detect device type from user agent
     */
    private function detectDeviceType(?string $userAgent): string
    {
        if (! $userAgent) {
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

    private function isStudentActive(int $studentId): bool
    {
        $status = $this->studentLifecycleStatusReader->statusesFor([$studentId])[$studentId] ?? null;

        return ! in_array($status, ['inactive', 'dropout', 'dropout_transfer', 'graduated', 'pending'], true);
    }

    /** @return array{id: int, student_id: string, full_name: string, email: string|null} */
    private function studentPayload(StudentReference $student): array
    {
        return [
            'id' => $student->id,
            'student_id' => $student->studentCode,
            'full_name' => $student->fullName,
            'email' => $student->email,
        ];
    }
}
