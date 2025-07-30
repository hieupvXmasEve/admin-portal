<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeachingAssignmentException extends Exception
{
    protected string $errorCode;
    protected array $context;

    public function __construct(
        string $message = '',
        string $errorCode = 'TEACHING_ASSIGNMENT_ERROR',
        array $context = [],
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errorCode = $errorCode;
        $this->context = $context;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function render(Request $request): JsonResponse
    {
        $status = $this->getHttpStatusCode();

        return response()->json([
            'success' => false,
            'error' => $this->getMessage(),
            'error_code' => $this->errorCode,
            'context' => $this->context,
        ], $status);
    }

    protected function getHttpStatusCode(): int
    {
        return match ($this->errorCode) {
            'LECTURER_NOT_FOUND',
            'COURSE_OFFERING_NOT_FOUND' => 404,
            'LECTURER_NOT_AVAILABLE',
            'SCHEDULE_CONFLICT',
            'ASSIGNMENT_CONFLICT',
            'INVALID_ASSIGNMENT_DATA' => 422,
            'PERMISSION_DENIED' => 403,
            'ASSIGNMENT_LOCKED' => 423,
            default => 500
        };
    }
}

class LecturerNotAvailableException extends TeachingAssignmentException
{
    public function __construct(int $lecturerId, string $reason = '', array $context = [])
    {
        $message = "Lecturer with ID {$lecturerId} is not available for assignment";
        if ($reason) {
            $message .= ": {$reason}";
        }

        parent::__construct(
            $message,
            'LECTURER_NOT_AVAILABLE',
            array_merge(['lecturer_id' => $lecturerId, 'reason' => $reason], $context)
        );
    }
}

class ScheduleConflictException extends TeachingAssignmentException
{
    public function __construct(int $lecturerId, int $courseOfferingId, array $conflicts = [])
    {
        $conflictCount = count($conflicts);
        $message = "Schedule conflict detected for lecturer {$lecturerId} with course offering {$courseOfferingId}";
        
        if ($conflictCount > 0) {
            $message .= " ({$conflictCount} conflict" . ($conflictCount > 1 ? 's' : '') . ')';
        }

        parent::__construct(
            $message,
            'SCHEDULE_CONFLICT',
            [
                'lecturer_id' => $lecturerId,
                'course_offering_id' => $courseOfferingId,
                'conflicts' => $conflicts
            ]
        );
    }
}

class AssignmentConflictException extends TeachingAssignmentException
{
    public function __construct(int $courseOfferingId, ?int $currentLecturerId = null)
    {
        $message = "Assignment conflict for course offering {$courseOfferingId}";
        
        if ($currentLecturerId) {
            $message .= " (currently assigned to lecturer {$currentLecturerId})";
        }

        parent::__construct(
            $message,
            'ASSIGNMENT_CONFLICT',
            [
                'course_offering_id' => $courseOfferingId,
                'current_lecturer_id' => $currentLecturerId
            ]
        );
    }
}

class LecturerNotFoundException extends TeachingAssignmentException
{
    public function __construct(int $lecturerId)
    {
        parent::__construct(
            "Lecturer with ID {$lecturerId} not found",
            'LECTURER_NOT_FOUND',
            ['lecturer_id' => $lecturerId]
        );
    }
}

class CourseOfferingNotFoundException extends TeachingAssignmentException
{
    public function __construct(int $courseOfferingId)
    {
        parent::__construct(
            "Course offering with ID {$courseOfferingId} not found",
            'COURSE_OFFERING_NOT_FOUND',
            ['course_offering_id' => $courseOfferingId]
        );
    }
}

class InvalidAssignmentDataException extends TeachingAssignmentException
{
    public function __construct(string $field, string $reason = '', array $context = [])
    {
        $message = "Invalid assignment data for field '{$field}'";
        if ($reason) {
            $message .= ": {$reason}";
        }

        parent::__construct(
            $message,
            'INVALID_ASSIGNMENT_DATA',
            array_merge(['field' => $field, 'reason' => $reason], $context)
        );
    }
}

class AssignmentPermissionException extends TeachingAssignmentException
{
    public function __construct(string $action, string $reason = '')
    {
        $message = "Permission denied for action '{$action}'";
        if ($reason) {
            $message .= ": {$reason}";
        }

        parent::__construct(
            $message,
            'PERMISSION_DENIED',
            ['action' => $action, 'reason' => $reason]
        );
    }
}

class AssignmentLockedException extends TeachingAssignmentException
{
    public function __construct(int $courseOfferingId, string $reason = '')
    {
        $message = "Course offering {$courseOfferingId} is locked for assignment changes";
        if ($reason) {
            $message .= ": {$reason}";
        }

        parent::__construct(
            $message,
            'ASSIGNMENT_LOCKED',
            ['course_offering_id' => $courseOfferingId, 'reason' => $reason]
        );
    }
}

class ExportException extends TeachingAssignmentException
{
    public function __construct(string $format, string $reason = '', array $context = [])
    {
        $message = "Export failed for format '{$format}'";
        if ($reason) {
            $message .= ": {$reason}";
        }

        parent::__construct(
            $message,
            'EXPORT_FAILED',
            array_merge(['format' => $format, 'reason' => $reason], $context)
        );
    }
}
