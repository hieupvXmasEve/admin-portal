<?php

declare(strict_types=1);

namespace App\Services\V1\Student;

use App\Models\Semester;
use App\Models\Student;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProfileService
{
    /**
     * Get student profile information
     */
    public function getProfile(Student $student): array
    {
        $cacheKey = "profile:student:{$student->id}";

        return Cache::remember($cacheKey, 1, function () use ($student) {
            return [
                'personal_info' => $this->getPersonalInfo($student),
                'academic_info' => $this->getAcademicInfo($student),
                'contact_info' => $this->getContactInfo($student),
                // 'enrollment_info' => $this->getEnrollmentInfo($student),
                'preferences' => $this->getPreferences($student),
                'profile_completion' => $this->calculateProfileCompletion($student),
            ];
        });
    }

    /**
     * Update student profile
     */
    public function updateProfile(Student $student, array $data): bool
    {
        return DB::transaction(function () use ($student, $data) {
            $updated = $student->update($this->filterUpdateableFields($data));

            if ($updated) {
                $this->clearProfileCache($student);
            }

            return $updated;
        });
    }

    /**
     * Upload and update avatar
     */
    public function uploadAvatar(Student $student, UploadedFile $file): array
    {
        // Delete old avatar if exists
        if ($student->avatar_url) {
            // Extract path from URL if it's stored as URL
            $oldPath = str_replace(Storage::disk('public')->url(''), '', $student->avatar_url);
            if ($oldPath && Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        // Store new avatar
        $path = $file->store('avatars', 'public');
        $avatarUrl = Storage::disk('public')->url($path);

        $student->update(['avatar_url' => $avatarUrl]);
        $this->clearProfileCache($student);

        return [
            'avatar_path' => $path,
            'avatar_url' => $avatarUrl,
        ];
    }

    /**
     * Get study plan
     */
    public function getStudyPlan(Student $student): array
    {
        $cacheKey = "study_plan:student:{$student->id}";

        return Cache::remember($cacheKey, 1800, function () use ($student) {
            $currentSemester = $this->resolveCurrentSemester();
            $completedUnits = $this->getCompletedUnits($student);
            $currentEnrollments = $this->getCurrentEnrollments($student);
            $remainingRequirements = $this->getRemainingRequirements($student);

            return [
                'current_semester' => $currentSemester ? [
                    'id' => $currentSemester->id,
                    'name' => $currentSemester->name,
                    'code' => $currentSemester->code,
                ] : null,
                'completed_units' => $completedUnits,
                'current_enrollments' => $currentEnrollments,
                'remaining_requirements' => $remainingRequirements,
                'graduation_timeline' => $this->calculateGraduationTimeline($student),
                'recommended_next_units' => $this->getRecommendedNextUnits($student),
            ];
        });
    }

    /**
     * Get academic history
     */
    public function getAcademicHistory(Student $student): array
    {
        $cacheKey = "academic_history:student:{$student->id}";

        return Cache::remember($cacheKey, 1800, function () use ($student) {
            $academicRecords = $student->academicRecords()
                ->with(['unit', 'semester', 'courseOffering.lecturer'])
                ->orderBy('semester_id', 'desc')
                ->get();

            return [
                'academic_records' => $this->formatAcademicRecords($academicRecords),
                'semester_summary' => $this->calculateSemesterSummary($academicRecords),
                'gpa_history' => $this->getGPAHistory($student),
                'credit_progression' => $this->getCreditProgression($student),
                'academic_achievements' => $this->getAcademicAchievements($student),
            ];
        });
    }

    /**
     * Get personal information
     */
    protected function getPersonalInfo(Student $student): array
    {
        // Parse first and last name from full_name if they don't exist as separate fields
        $nameParts = $student->full_name ? explode(' ', $student->full_name, 2) : ['', ''];
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';

        return [
            'student_id' => $student->student_id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $student->full_name,
            'preferred_name' => $student->preferred_name,
            'date_of_birth' => $student->date_of_birth?->toDateString(),
            'gender' => $student->gender,
            'nationality' => $student->nationality,
            'avatar_url' => $student->avatar_url,
            'national_id' => $student->national_id,
            'cccd_address' => $student->cccd_address,
            'address' => $student->address,
        ];
    }

    /**
     * Get academic information
     */
    protected function getAcademicInfo(Student $student): array
    {
        return [
            'program' => [
                'id' => $student->program?->id,
                'name' => $student->program?->name,
                'code' => $student->program?->code,
                'degree_type' => $student->program?->degree_type,
                'duration_years' => $student->program?->duration_years,
            ],
            'curriculum_version' => [
                'id' => $student->curriculumVersion?->id,
                'version' => $student->curriculumVersion?->version,
                'effective_date' => $student->curriculumVersion?->effective_date?->toDateString(),
            ],
            'campus' => [
                'id' => $student->campus?->id,
                'name' => $student->campus?->name,
                'code' => $student->campus?->code,
                'location' => $student->campus?->location,
            ],
            'enrollment_date' => $student->admission_date?->toDateString(),
            'expected_graduation_date' => $student->expected_graduation_date?->toDateString(),
            'study_mode' => (string) ($student->study_mode ?? ''),
            'status' => (string) ($student->status ?? ''),
        ];
    }

    /**
     * Get contact information
     */
    protected function getContactInfo(Student $student): array
    {
        // Parse address if it's a single string field or provide empty structure
        $addressData = [
            'street' => null,
            'city' => null,
            'state' => null,
            'postal_code' => null,
            'country' => null,
        ];

        return [
            'email' => $student->email,
            'phone' => $student->phone,
            'emergency_contact_name' => $student->emergency_contact_name,
            'emergency_contact_phone' => $student->emergency_contact_phone,
            'emergency_contact_relationship' => $student->emergency_contact_relationship,
            'address' => $addressData,
            'high_school_name' => $student->high_school_name,
        ];
    }

    /**
     * Get enrollment information
     */
    protected function getEnrollmentInfo(Student $student): array
    {
        $totalCreditsEarned = $student->academicRecords()
            ->where('completion_status', 'completed')
            ->sum('credit_hours_earned');

        $totalCreditsRequired = $student->curriculumVersion?->total_credit_hours ?? 0;

        return [
            'total_credits_earned' => $totalCreditsEarned,
            'total_credits_required' => $totalCreditsRequired,
            'credits_remaining' => max(0, $totalCreditsRequired - $totalCreditsEarned),
            'completion_percentage' => $totalCreditsRequired > 0
                ? round(($totalCreditsEarned / $totalCreditsRequired) * 100, 1)
                : 0,
            'current_semester_credits' => $this->getCurrentSemesterCredits($student),
            'academic_standing' => $this->getAcademicStanding($student),
        ];
    }

    /**
     * Get user preferences
     */
    protected function getPreferences(Student $student): array
    {
        return [
            'language' => $student->preferred_language ?? 'en',
            'timezone' => $student->timezone ?? 'Australia/Melbourne',
            'date_format' => $student->date_format ?? 'DD/MM/YYYY',
            'time_format' => $student->time_format ?? '24h',
            'theme' => $student->theme_preference ?? 'light',
            'notifications' => [
                'email_enabled' => $student->email_notifications ?? true,
                'push_enabled' => $student->push_notifications ?? true,
                'sms_enabled' => $student->sms_notifications ?? false,
            ],
        ];
    }

    /**
     * Calculate profile completion percentage
     */
    protected function calculateProfileCompletion(Student $student): array
    {
        // Parse first and last name from full_name for completion check
        $nameParts = $student->full_name ? explode(' ', $student->full_name, 2) : ['', ''];
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';

        $fields = [
            'full_name' => ! empty($student->full_name),
            'email' => ! empty($student->email),
            'phone' => ! empty($student->phone),
            'date_of_birth' => ! empty($student->date_of_birth),
            'address' => ! empty($student->address),
            'cccd_address' => ! empty($student->cccd_address),
            'national_id' => ! empty($student->national_id),
            'emergency_contact_name' => ! empty($student->emergency_contact_name),
            'emergency_contact_phone' => ! empty($student->emergency_contact_phone),
            'avatar_url' => ! empty($student->avatar_url),
            'gender' => ! empty($student->gender),
            'nationality' => ! empty($student->nationality),
        ];

        $completedFields = array_filter($fields);
        $totalFields = count($fields);
        $completedCount = count($completedFields);
        $percentage = round(($completedCount / $totalFields) * 100, 1);

        return [
            'percentage' => $percentage,
            'completed_fields' => $completedCount,
            'total_fields' => $totalFields,
            'missing_fields' => array_keys(array_filter($fields, fn($completed) => ! $completed)),
            'status' => $this->getCompletionStatus($percentage),
        ];
    }

    /**
     * Filter updateable fields
     */
    protected function filterUpdateableFields(array $data): array
    {
        $allowedFields = [
            'full_name',
            'phone',
            'date_of_birth',
            'address',
            'cccd_address',
            'national_id',
            'high_school_name',
            'gender',
        ];

        return array_intersect_key($data, array_flip($allowedFields));
    }

    /**
     * Get completed units
     */
    protected function getCompletedUnits(Student $student): array
    {
        return $student->academicRecords()
            ->where('completion_status', 'completed')
            ->with(['unit', 'semester'])
            ->get()
            ->map(function ($record) {
                return [
                    'unit_code' => $record->unit?->code,
                    'unit_name' => $record->unit?->name,
                    'credit_hours' => $record->credit_hours,
                    'grade' => $record->final_letter_grade,
                    'semester' => $record->semester?->name,
                    'completion_date' => $record->completion_date?->toDateString(),
                ];
            })
            ->toArray();
    }

    /**
     * Get current enrollments
     */
    protected function getCurrentEnrollments(Student $student): array
    {
        $currentSemester = $this->resolveCurrentSemester();

        if (! $currentSemester) {
            return [];
        }

        return $student->courseRegistrations()
            ->where('semester_id', $currentSemester->id)
            ->where('registration_status', 'registered')
            ->with(['courseOffering.curriculumUnit.unit'])
            ->get()
            ->map(function ($registration) {
                return [
                    'unit_code' => $registration->courseOffering?->curriculumUnit?->unit?->code,
                    'unit_name' => $registration->courseOffering?->curriculumUnit?->unit?->name,
                    'credit_hours' => $registration->courseOffering?->curriculumUnit?->credit_hours,
                    'registration_date' => $registration->registration_date?->toDateString(),
                ];
            })
            ->toArray();
    }

    /**
     * Get remaining requirements
     */
    protected function getRemainingRequirements(Student $student): array
    {
        // This would calculate remaining curriculum requirements
        // Implementation depends on your curriculum structure
        return [
            'core_units' => [],
            'elective_units' => [],
            'total_credits_remaining' => 0,
        ];
    }

    /**
     * Calculate graduation timeline
     */
    protected function calculateGraduationTimeline(Student $student): array
    {
        $totalCreditsRequired = $student->curriculumVersion?->total_credit_hours ?? 0;
        $creditsEarned = $student->academicRecords()
            ->where('completion_status', 'completed')
            ->sum('credit_hours_earned');

        $creditsRemaining = $totalCreditsRequired - $creditsEarned;
        $averageCreditsPerSemester = 18; // Typical full-time load

        $semestersRemaining = $creditsRemaining > 0
            ? (int) ceil($creditsRemaining / $averageCreditsPerSemester)
            : 0;

        return [
            'credits_remaining' => $creditsRemaining,
            'semesters_remaining' => $semestersRemaining,
            'estimated_graduation_date' => $this->calculateEstimatedGraduationDate($semestersRemaining),
            'on_track' => $semestersRemaining <= $this->getExpectedSemestersRemaining($student),
        ];
    }

    /**
     * Get recommended next units
     */
    protected function getRecommendedNextUnits(Student $student): array
    {
        // This would analyze completed units and recommend next units
        // Implementation depends on your curriculum and prerequisite structure
        return [];
    }

    /**
     * Format academic records
     */
    protected function formatAcademicRecords(Collection $records): array
    {
        return $records->groupBy('semester_id')->map(function ($semesterRecords, $semesterId) {
            $semester = $semesterRecords->first()->semester ?? null;

            return [
                'semester' => [
                    'id' => $semester?->id,
                    'name' => $semester?->name,
                    'code' => $semester?->code,
                ],
                'courses' => $semesterRecords->map(function ($record) {
                    return [
                        'unit_code' => $record->unit?->code,
                        'unit_name' => $record->unit?->name,
                        'credit_hours' => $record->credit_hours,
                        'grade' => $record->final_letter_grade,
                        'grade_points' => $record->grade_points,
                        'completion_status' => $record->completion_status,
                        'lecturer' => $record->courseOffering?->lecturer?->full_name,
                    ];
                })->toArray(),
            ];
        })->values()->toArray();
    }

    /**
     * Calculate semester summary
     */
    protected function calculateSemesterSummary(Collection $records): array
    {
        return $records->groupBy('semester_id')->map(function ($semesterRecords) {
            $semester = $semesterRecords->first()->semester ?? null;
            $completedRecords = $semesterRecords->where('completion_status', 'completed');

            $totalCredits = $semesterRecords->sum('credit_hours');
            $earnedCredits = $completedRecords->sum('credit_hours_earned');
            $qualityPoints = $completedRecords->sum('quality_points');

            return [
                'semester_name' => $semester?->name,
                'total_courses' => $semesterRecords->count(),
                'completed_courses' => $completedRecords->count(),
                'total_credits' => $totalCredits,
                'earned_credits' => $earnedCredits,
                'semester_gpa' => $earnedCredits > 0 ? round($qualityPoints / $earnedCredits, 2) : 0,
            ];
        })->values()->toArray();
    }

    /**
     * Get GPA history
     */
    protected function getGPAHistory(Student $student): array
    {
        return $student->gpaCalculations()
            ->where('calculation_type', 'semester')
            ->with('semester')
            ->orderBy('created_at')
            ->get()
            ->map(function ($calculation) {
                return [
                    'semester' => $calculation->semester?->name,
                    'gpa' => round($calculation->gpa, 2),
                    'credit_hours' => $calculation->credit_hours_earned,
                    'academic_standing' => $calculation->academic_standing,
                ];
            })
            ->toArray();
    }

    /**
     * Get credit progression
     */
    protected function getCreditProgression(Student $student): array
    {
        $records = $student->academicRecords()
            ->where('completion_status', 'completed')
            ->with('semester')
            ->orderBy('completion_date')
            ->get();

        $progression = [];
        $cumulativeCredits = 0;

        foreach ($records->groupBy('semester_id') as $semesterRecords) {
            $semester = $semesterRecords->first()->semester ?? null;
            $semesterCredits = $semesterRecords->sum('credit_hours_earned');
            $cumulativeCredits += $semesterCredits;

            $progression[] = [
                'semester' => $semester?->name,
                'semester_credits' => $semesterCredits,
                'cumulative_credits' => $cumulativeCredits,
            ];
        }

        return $progression;
    }

    /**
     * Get academic achievements
     */
    protected function getAcademicAchievements(Student $student): array
    {
        $achievements = [];

        // Dean's List achievements
        $deansList = $student->gpaCalculations()
            ->where('gpa', '>=', 3.7)
            ->where('calculation_type', 'semester')
            ->with('semester')
            ->get();

        foreach ($deansList as $achievement) {
            $achievements[] = [
                'type' => 'deans_list',
                'title' => 'Dean\'s List',
                'description' => 'Achieved GPA of ' . round($achievement->gpa, 2),
                'semester' => $achievement->semester?->name,
                'date' => $achievement->created_at?->toDateString(),
            ];
        }

        return $achievements;
    }

    /**
     * Get current semester credits
     */
    protected function getCurrentSemesterCredits(Student $student): int
    {
        $currentSemester = $this->resolveCurrentSemester();

        if (! $currentSemester) {
            return 0;
        }

        return $student->courseRegistrations()
            ->where('semester_id', $currentSemester->id)
            ->where('registration_status', 'registered')
            ->with('courseOffering.curriculumUnit')
            ->get()
            ->sum('courseOffering.curriculumUnit.credit_hours');
    }

    /**
     * Get academic standing
     */
    protected function getAcademicStanding(Student $student): string
    {
        $latestGPA = $student->gpaCalculations()
            ->where('calculation_type', 'cumulative')
            ->latest()
            ->first();

        if (! $latestGPA) {
            return 'Good Standing';
        }

        return match (true) {
            $latestGPA->gpa >= 3.7 => 'Dean\'s List',
            $latestGPA->gpa >= 3.5 => 'High Honors',
            $latestGPA->gpa >= 3.0 => 'Good Standing',
            $latestGPA->gpa >= 2.0 => 'Satisfactory Standing',
            default => 'Academic Probation',
        };
    }

    /**
     * Get completion status
     */
    protected function getCompletionStatus(float $percentage): string
    {
        return match (true) {
            $percentage >= 90 => 'complete',
            $percentage >= 70 => 'mostly_complete',
            $percentage >= 50 => 'partially_complete',
            default => 'incomplete',
        };
    }

    /**
     * Calculate estimated graduation date
     */
    protected function calculateEstimatedGraduationDate(int $semestersRemaining): ?string
    {
        if ($semestersRemaining <= 0) {
            return null;
        }

        // Assuming 2 semesters per year
        $yearsRemaining = (int) ceil($semestersRemaining / 2);

        return now()->addYears($yearsRemaining)->format('Y-m-d');
    }

    /**
     * Get expected semesters remaining
     */
    protected function getExpectedSemestersRemaining(Student $student): int
    {
        if (! $student->expected_graduation_date) {
            return 0;
        }

        $monthsRemaining = now()->diffInMonths($student->expected_graduation_date);

        return (int) ceil($monthsRemaining / 6); // Assuming 6 months per semester
    }

    /**
     * Clear profile cache
     */
    protected function clearProfileCache(Student $student): void
    {
        $patterns = [
            "profile:student:{$student->id}",
            "study_plan:student:{$student->id}",
            "academic_history:student:{$student->id}",
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }

    /**
     * Resolve current semester from system state
     */
    protected function resolveCurrentSemester(): ?Semester
    {
        // Prefer explicitly active semester if set
        $active = Semester::getActiveSemester();
        if ($active) {
            return $active;
        }

        // Fallback: pick semester that wraps current date
        return Semester::query()
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->orderBy('start_date', 'desc')
            ->first();
    }
}
