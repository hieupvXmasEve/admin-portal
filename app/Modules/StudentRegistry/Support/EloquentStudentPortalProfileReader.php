<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Support;

use App\Models\Student;
use App\Shared\Contracts\Academic\StudentLifecycleStatusReader;
use App\Shared\Contracts\StudentRegistry\DTO\StudentPortalProfile;
use App\Shared\Contracts\StudentRegistry\DTO\StudentProfile;
use App\Shared\Contracts\StudentRegistry\StudentPortalProfileReader;
use App\Shared\Contracts\StudentRegistry\StudentProfileReader;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Registry-owned read projection for the established student portal profile.
 */
final class EloquentStudentPortalProfileReader implements StudentPortalProfileReader
{
    public function __construct(
        private readonly StudentProfileReader $profiles,
        private readonly StudentLifecycleStatusReader $lifecycleStatuses,
    ) {}

    public function forStudent(int $studentId): StudentPortalProfile
    {
        $profile = $this->profiles->findProfile($studentId);
        if ($profile === null) {
            throw new ModelNotFoundException;
        }

        $student = Student::query()
            ->with(['program', 'curriculumVersion', 'campus'])
            ->findOrFail($studentId);
        $status = $this->lifecycleStatuses->statusesFor([$studentId])[$studentId] ?? $student->status;

        return new StudentPortalProfile([
            'info' => $this->personalInfo($profile, $student, $status),
            'preferences' => $this->preferences($student),
            'profile_completion' => $this->completion($profile),
        ]);
    }

    /** @return array<string, mixed> */
    private function personalInfo(StudentProfile $profile, Student $student, ?string $status): array
    {
        $nameParts = explode(' ', $profile->fullName, 2);

        return [
            'id' => $profile->id,
            'student_id' => $profile->studentCode,
            'user_id' => $profile->userId,
            'first_name' => $nameParts[0] ?? '',
            'last_name' => $nameParts[1] ?? '',
            'full_name' => $profile->fullName,
            'date_of_birth' => $profile->dateOfBirth,
            'gender' => $profile->gender,
            'nationality' => $profile->nationality,
            'ethnicity' => $profile->ethnicity,
            'avatar_url' => $profile->avatarUrl,
            'national_id' => $profile->nationalId,
            'address' => $profile->address,
            'current_address_line' => $profile->currentAddressLine,
            'current_ward' => $profile->currentWard,
            'current_province' => $profile->currentProvince,
            'current_country' => $profile->currentCountry,
            'cccd_address' => $profile->cccdAddress,
            'cccd_address_line' => $profile->cccdAddressLine,
            'cccd_ward' => $profile->cccdWard,
            'cccd_province' => $profile->cccdProvince,
            'cccd_country' => $profile->cccdCountry,
            'email' => $profile->email,
            'phone' => $profile->phone,
            'emergency_contact_name' => $profile->emergencyContactName,
            'emergency_contact_phone' => $profile->emergencyContactPhone,
            'emergency_contact_relationship' => $profile->emergencyContactRelationship,
            'emergency_contact_email' => $profile->emergencyContactEmail,
            'emergency_contact_name_1' => $profile->emergencyContactName1,
            'emergency_contact_email_1' => $profile->emergencyContactEmail1,
            'emergency_contact_phone_1' => $profile->emergencyContactPhone1,
            'emergency_contact_relationship_1' => $profile->emergencyContactRelationship1,
            'high_school_name' => $profile->highSchoolName,
            'program' => [
                'id' => $student->program?->id,
                'name' => $student->program?->name,
                'code' => $student->program?->code,
                'degree_type' => $student->program?->degree_type,
                'duration_years' => $student->program?->duration_years,
            ],
            'curriculum_version' => [
                'id' => $student->curriculumVersion?->id,
                'version' => $student->curriculumVersion?->version_code,
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
            'status' => (string) ($status ?? ''),
        ];
    }

    /** @return array<string, mixed> */
    private function preferences(Student $student): array
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

    /** @return array{percentage: float, completed_fields: int, total_fields: int, missing_fields: list<string>, status: string} */
    private function completion(StudentProfile $profile): array
    {
        $fields = [
            'full_name' => $profile->fullName !== '',
            'email' => ! empty($profile->email),
            'phone' => ! empty($profile->phone),
            'date_of_birth' => ! empty($profile->dateOfBirth),
            'address' => ! empty($profile->address),
            'current_address_line' => ! empty($profile->currentAddressLine),
            'current_ward' => ! empty($profile->currentWard),
            'current_province' => ! empty($profile->currentProvince),
            'current_country' => ! empty($profile->currentCountry),
            'cccd_address' => ! empty($profile->cccdAddress),
            'cccd_address_line' => ! empty($profile->cccdAddressLine),
            'cccd_ward' => ! empty($profile->cccdWard),
            'cccd_province' => ! empty($profile->cccdProvince),
            'cccd_country' => ! empty($profile->cccdCountry),
            'national_id' => ! empty($profile->nationalId),
            'ethnicity' => ! empty($profile->ethnicity),
            'emergency_contact_name' => ! empty($profile->emergencyContactName),
            'emergency_contact_phone' => ! empty($profile->emergencyContactPhone),
            'emergency_contact_email' => ! empty($profile->emergencyContactEmail),
            'emergency_contact_name_1' => ! empty($profile->emergencyContactName1),
            'emergency_contact_email_1' => ! empty($profile->emergencyContactEmail1),
            'emergency_contact_phone_1' => ! empty($profile->emergencyContactPhone1),
            'emergency_contact_relationship_1' => ! empty($profile->emergencyContactRelationship1),
            'gender' => ! empty($profile->gender),
            'nationality' => ! empty($profile->nationality),
        ];
        $completedFields = array_filter($fields);
        $totalFields = count($fields);
        $percentage = round((count($completedFields) / $totalFields) * 100, 1);

        return [
            'percentage' => $percentage,
            'completed_fields' => count($completedFields),
            'total_fields' => $totalFields,
            'missing_fields' => array_keys(array_filter($fields, static fn (bool $completed): bool => ! $completed)),
            'status' => match (true) {
                $percentage >= 90 => 'complete',
                $percentage >= 70 => 'mostly_complete',
                $percentage >= 50 => 'partially_complete',
                default => 'incomplete',
            },
        ];
    }
}
