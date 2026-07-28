<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Http\Requests\Student;

use App\Models\Student;
use Illuminate\Validation\Rule;

/**
 * Filter rules shared by the student directory listing and its export, so both
 * accept an identical filter vocabulary and a bookmarked listing URL stays
 * exportable.
 */
trait ChecksStudentDirectoryFilters
{
    /**
     * @return array<string, mixed>
     */
    protected function studentFilterRules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'student_ids' => 'nullable|array|max:100',
            'student_ids.*' => 'required|string|max:20|distinct',
            // Keep singular filters for compatibility with existing bookmarks.
            'program_id' => 'nullable|integer|exists:programs,id',
            'status' => ['nullable', 'string', Rule::in(Student::STATUSES)],
            'program_ids' => 'nullable|array|max:100',
            'program_ids.*' => 'required|integer|distinct|exists:programs,id',
            'specialization_ids' => 'nullable|array|max:100',
            'specialization_ids.*' => 'required|integer|distinct|exists:specializations,id',
            'statuses' => 'nullable|array|max:20',
            'statuses.*' => ['required', 'string', 'distinct', Rule::in(Student::STATUSES)],
            'intake_semester_ids' => 'nullable|array|max:100',
            'intake_semester_ids.*' => 'required|integer|distinct|exists:semesters,id',
        ];
    }

    /**
     * Collapse the singular and plural filter forms into the plural shape the
     * directory queries expect.
     *
     * @return array<string, mixed>
     */
    public function normalizedStudentFilters(): array
    {
        $validated = $this->validated();

        return [
            'search' => $validated['search'] ?? '',
            'student_ids' => $validated['student_ids'] ?? [],
            'program_ids' => array_map('intval', $validated['program_ids'] ?? (isset($validated['program_id']) ? [$validated['program_id']] : [])),
            'specialization_ids' => array_map('intval', $validated['specialization_ids'] ?? []),
            'statuses' => $validated['statuses'] ?? (isset($validated['status']) ? [$validated['status']] : []),
            'intake_semester_ids' => array_map('intval', $validated['intake_semester_ids'] ?? []),
        ];
    }
}
