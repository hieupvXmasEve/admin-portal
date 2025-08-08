<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\AssignmentConflictException;
use App\Exceptions\CourseOfferingNotFoundException;
use App\Exceptions\InvalidAssignmentDataException;
use App\Exceptions\LecturerNotAvailableException;
use App\Exceptions\LecturerNotFoundException;
use App\Exceptions\ScheduleConflictException;
use App\Exports\TeachingAssignmentsExport;
use App\Models\CourseOffering;
use App\Models\Lecture;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

// use Barryvdh\DomPDF\Facade\Pdf; // TODO: Install barryvdh/laravel-dompdf package

class TeachingAssignmentService
{
    private array $currentFilters = [];

    /**
     * Get teaching assignments with filtering and pagination
     */
    public function getAssignments(array $filters = []): LengthAwarePaginator
    {
        $query = CourseOffering::query()
            ->forTeachingAssignments()
            ->withAssignmentDetails();

        // Apply filters
        if (! empty($filters['semester_id'])) {
            $query->where('semester_id', $filters['semester_id']);
        }
        Log::info('$data', [
            'data' => $query->get()->toArray(),
            'campus' => app('campus')->id,
        ]);

        // Filter by current campus (include unassigned courses and assigned courses from current campus)
        $query->where(function ($campusQuery) {
            $campusQuery->whereNull('lecture_id') // Include unassigned courses
                ->orWhereHas('lecture', function ($lectureQuery) {
                    $lectureQuery->where('campus_id', app('campus')->id);
                });
        });
        Log::info('$data', [
            'data 2' => count($query->get()->toArray()),
        ]);
        if (! empty($filters['assignment_status'])) {
            $query->byAssignmentStatus($filters['assignment_status']);
        }

        if (! empty($filters['faculty'])) {
            $query->whereHas('lecture', function ($lectureQuery) use ($filters) {
                $lectureQuery->where('faculty', $filters['faculty']);
            });
        }

        if (! empty($filters['department'])) {
            $query->whereHas('lecture', function ($lectureQuery) use ($filters) {
                $lectureQuery->where('department', $filters['department']);
            });
        }

        // Search functionality
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($searchQuery) use ($search) {
                // Search by unit code and name
                $searchQuery->whereHas('curriculumUnit.unit', function ($unitQuery) use ($search) {
                    $unitQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                })
                    // Search by lecturer name
                    ->orWhereHas('lecture', function ($lectureQuery) use ($search) {
                        $lectureQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('employee_id', 'like', "%{$search}%");
                    })
                    // Search by section code
                    ->orWhere('section_code', 'like', "%{$search}%");
            });
        }

        // Default ordering
        $query->orderBy('semester_id', 'desc')
            ->orderByRaw('CASE
                WHEN lecture_id IS NULL AND EXISTS(
                    SELECT 1 FROM semesters s WHERE s.id = course_offerings.semester_id AND s.start_date <= NOW()
                ) THEN 1
                WHEN lecture_id IS NULL THEN 2
                ELSE 3
            END')
            ->orderBy('id');

        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Assign a lecturer to a course offering
     */
    public function assignLecturer(int $courseOfferingId, int $lecturerId): bool
    {
        try {
            DB::beginTransaction();

            // Validate inputs
            if ($courseOfferingId <= 0) {
                throw new InvalidAssignmentDataException('course_offering_id', 'Must be a positive integer');
            }
            if ($lecturerId <= 0) {
                throw new InvalidAssignmentDataException('lecturer_id', 'Must be a positive integer');
            }

            // Find entities
            $courseOffering = CourseOffering::find($courseOfferingId);
            if (! $courseOffering) {
                throw new CourseOfferingNotFoundException($courseOfferingId);
            }

            $lecturer = Lecture::find($lecturerId);
            if (! $lecturer) {
                throw new LecturerNotFoundException($lecturerId);
            }

            // Check if course offering is already assigned
            if ($courseOffering->lecture_id && $courseOffering->lecture_id !== $lecturerId) {
                throw new AssignmentConflictException($courseOfferingId, $courseOffering->lecture_id);
            }

            // Validate lecturer availability
            if (! $lecturer->isAvailableForAssignment()) {
                throw new LecturerNotAvailableException(
                    $lecturerId,
                    'Lecturer is not marked as available for assignment',
                    ['employment_status' => $lecturer->employment_status, 'is_active' => $lecturer->is_active]
                );
            }

            // Validate assignment compatibility
            if (! $courseOffering->canBeAssignedTo($lecturer)) {
                throw new LecturerNotAvailableException(
                    $lecturerId,
                    'Lecturer does not meet the requirements for this course offering'
                );
            }

            // Check for schedule conflicts
            $conflicts = $this->checkScheduleConflicts($lecturerId, $courseOfferingId);
            if (! empty($conflicts)) {
                throw new ScheduleConflictException($lecturerId, $courseOfferingId, $conflicts);
            }

            // Perform assignment
            $courseOffering->lecture_id = $lecturerId;
            $courseOffering->save();

            DB::commit();

            Log::info('Lecturer assigned to course offering', [
                'course_offering_id' => $courseOfferingId,
                'lecturer_id' => $lecturerId,
                'lecturer_name' => $lecturer->display_name,
                'unit_code' => $courseOffering->curriculumUnit?->unit?->code,
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to assign lecturer', [
                'course_offering_id' => $courseOfferingId,
                'lecturer_id' => $lecturerId,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
            ]);
            throw $e;
        }
    }

    /**
     * Remove lecturer assignment from a course offering
     */
    public function unassignLecturer(int $courseOfferingId): bool
    {
        try {
            DB::beginTransaction();

            // Validate input
            if ($courseOfferingId <= 0) {
                throw new InvalidAssignmentDataException('course_offering_id', 'Must be a positive integer');
            }

            // Find course offering
            $courseOffering = CourseOffering::find($courseOfferingId);
            if (! $courseOffering) {
                throw new CourseOfferingNotFoundException($courseOfferingId);
            }

            // Check if course offering has an assignment
            if (! $courseOffering->lecture_id) {
                throw new AssignmentConflictException($courseOfferingId);
            }

            $previousLecturerId = $courseOffering->lecture_id;

            // Perform unassignment
            $courseOffering->lecture_id = null;
            $courseOffering->save();

            DB::commit();

            Log::info('Lecturer unassigned from course offering', [
                'course_offering_id' => $courseOfferingId,
                'previous_lecturer_id' => $previousLecturerId,
                'unit_code' => $courseOffering->curriculumUnit?->unit?->code,
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to unassign lecturer', [
                'course_offering_id' => $courseOfferingId,
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
            ]);
            throw $e;
        }
    }

    /**
     * Get available lecturers for a course offering with filtering
     */
    public function getAvailableLecturers(int $courseOfferingId, array $filters = []): Collection
    {
        $courseOffering = CourseOffering::findOrFail($courseOfferingId);

        $query = Lecture::query()
            ->availableForTeaching()
            ->withCurrentLoad();

        // Apply filters
        if (! empty($filters['faculty']) || ! empty($filters['department'])) {
            $query->byFacultyAndDepartment($filters['faculty'] ?? null, $filters['department'] ?? null);
        }

        // Filter by current campus
        $query->where('campus_id', app('campus')->id);

        // Search by name or employee ID
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        $lecturers = $query->orderByName()->get();

        // Filter out lecturers with conflicts and add conflict information
        return $lecturers->map(function ($lecturer) use ($courseOffering) {
            $lecturer->can_be_assigned = $courseOffering->canBeAssignedTo($lecturer);
            $lecturer->conflicts = $lecturer->hasScheduleConflictWith($courseOffering)
                ? $lecturer->getConflictingCourses($courseOffering)
                : collect();

            return $lecturer;
        });
    }

    /**
     * Check for scheduling conflicts when assigning a lecturer
     */
    public function checkScheduleConflicts(int $lecturerId, int $courseOfferingId): array
    {
        $lecturer = Lecture::findOrFail($lecturerId);
        $courseOffering = CourseOffering::findOrFail($courseOfferingId);

        if (! $lecturer->hasScheduleConflictWith($courseOffering)) {
            return [];
        }

        $conflictingCourses = $lecturer->getConflictingCourses($courseOffering);

        return $conflictingCourses->map(function ($conflictingCourse) use ($courseOffering) {
            return [
                'conflicting_course_id' => $conflictingCourse->id,
                'unit_code' => $conflictingCourse->curriculumUnit?->unit?->code,
                'unit_name' => $conflictingCourse->curriculumUnit?->unit?->name,
                'section_code' => $conflictingCourse->section_code,
                'schedule_days' => $conflictingCourse->schedule_days,
                'schedule_time_start' => $conflictingCourse->schedule_time_start?->format('H:i'),
                'schedule_time_end' => $conflictingCourse->schedule_time_end?->format('H:i'),
                'conflict_type' => $this->determineConflictType($courseOffering, $conflictingCourse),
            ];
        })->toArray();
    }

    /**
     * Determine the type of schedule conflict
     */
    private function determineConflictType(CourseOffering $courseOffering, CourseOffering $conflictingCourse): string
    {
        $startTime1 = $courseOffering->schedule_time_start;
        $endTime1 = $courseOffering->schedule_time_end;
        $startTime2 = $conflictingCourse->schedule_time_start;
        $endTime2 = $conflictingCourse->schedule_time_end;

        if ($startTime1 && $endTime1 && $startTime2 && $endTime2) {
            if ($startTime1->eq($startTime2) && $endTime1->eq($endTime2)) {
                return 'same_time';
            }

            return 'time_overlap';
        }

        return 'schedule_conflict';
    }

    /**
     * Export teaching assignments in specified format
     */
    public function exportAssignments(array $filters = [], string $format = 'excel'): string
    {
        // Store filters for use in export methods
        $this->currentFilters = $filters;

        // Get all assignments without pagination for export
        $assignments = $this->getAssignmentsForExport($filters);

        switch ($format) {
            case 'excel':
                return $this->exportToExcel($assignments);
            case 'pdf':
                return $this->exportToPdf($assignments);
            default:
                throw new \InvalidArgumentException("Unsupported export format: {$format}");
        }
    }

    /**
     * Get assignments for export (without pagination)
     */
    private function getAssignmentsForExport(array $filters = []): Collection
    {
        $query = CourseOffering::query()
            ->forTeachingAssignments()
            ->withAssignmentDetails();

        // Apply the same filters as getAssignments method
        if (! empty($filters['semester_id'])) {
            $query->where('semester_id', $filters['semester_id']);
        }

        // Filter by current campus (include unassigned courses and assigned courses from current campus)
        $query->where(function ($campusQuery) {
            $campusQuery->whereNull('lecture_id') // Include unassigned courses
                ->orWhereHas('lecture', function ($lectureQuery) {
                    $lectureQuery->where('campus_id', app('campus')->id);
                });
        });

        if (! empty($filters['assignment_status'])) {
            $query->byAssignmentStatus($filters['assignment_status']);
        }

        if (! empty($filters['faculty'])) {
            $query->whereHas('lecture', function ($lectureQuery) use ($filters) {
                $lectureQuery->where('faculty', $filters['faculty']);
            });
        }

        if (! empty($filters['department'])) {
            $query->whereHas('lecture', function ($lectureQuery) use ($filters) {
                $lectureQuery->where('department', $filters['department']);
            });
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($searchQuery) use ($search) {
                $searchQuery->whereHas('curriculumUnit.unit', function ($unitQuery) use ($search) {
                    $unitQuery->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                })
                    ->orWhereHas('lecture', function ($lectureQuery) use ($search) {
                        $lectureQuery->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('employee_id', 'like', "%{$search}%");
                    })
                    ->orWhere('section_code', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('semester_id', 'desc')
            ->orderBy('id')
            ->get();
    }

    /**
     * Export to Excel format
     */
    private function exportToExcel(Collection $assignments): string
    {
        // Ensure exports directory exists
        if (! Storage::exists('exports')) {
            Storage::makeDirectory('exports');
        }

        $fileName = 'teaching-assignments-'.now()->format('Y-m-d-H-i-s').'.xlsx';
        $filePath = 'exports/'.$fileName;

        Excel::store(new TeachingAssignmentsExport($assignments, $this->currentFilters ?? []), $filePath, 'local');

        return Storage::path($filePath);
    }

    /**
     * Export to PDF format
     */
    private function exportToPdf(Collection $assignments): string
    {
        // TODO: Install barryvdh/laravel-dompdf package for PDF export
        // Run: composer require barryvdh/laravel-dompdf

        throw new \Exception('PDF export requires barryvdh/laravel-dompdf package. Please run: composer require barryvdh/laravel-dompdf');
        /*
        $fileName = 'teaching-assignments-' . now()->format('Y-m-d-H-i-s') . '.pdf';
        $filePath = 'exports/' . $fileName;

        // Prepare data for the PDF view
        $data = [
            'assignments' => $assignments,
            'filters' => $this->currentFilters ?? [],
            'generated_at' => now(),
        ];

        // Generate PDF
        $pdf = Pdf::loadView('exports.teaching-assignments-pdf', $data)
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'defaultFont' => 'Arial',
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
            ]);

        // Store the PDF
        Storage::put($filePath, $pdf->output());

        return Storage::path($filePath);
        */
    }
}
