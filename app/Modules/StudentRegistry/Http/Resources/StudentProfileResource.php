<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class StudentProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'info' => $this->formatPersonalInfo($this->resource['info']),
            //            'academic_info' => $this->formatAcademicInfo($this->resource['academic_info']),
            //            'contact_info' => $this->formatContactInfo($this->resource['contact_info']),
            // 'enrollment_info' => $this->formatEnrollmentInfo($this->resource['enrollment_info']),
            'preferences' => $this->formatPreferences($this->resource['preferences']),
            'profile_completion' => $this->formatProfileCompletion($this->resource['profile_completion']),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Format personal information
     */
    protected function formatPersonalInfo(array $personalInfo): array
    {
        $program = $personalInfo['program'] ?? [];
        $cv = $personalInfo['curriculum_version'] ?? [];
        $campus = $personalInfo['campus'] ?? [];
        $cvVersion = $cv['version'] ?? null;

        $studyMode = (string) ($personalInfo['study_mode'] ?? '');
        $status = (string) ($personalInfo['status'] ?? '');

        return [
            'id' => $personalInfo['id'],
            'student_id' => $personalInfo['student_id'],
            'user_id' => $personalInfo['user_id'],
            'first_name' => $personalInfo['first_name'],
            'last_name' => $personalInfo['last_name'],
            'full_name' => $personalInfo['full_name'],

            'date_of_birth' => $personalInfo['date_of_birth'],
            'age' => $personalInfo['date_of_birth']
                ? \Carbon\Carbon::parse($personalInfo['date_of_birth'])->age
                : null,
            'gender' => $personalInfo['gender'],
            'nationality' => $personalInfo['nationality'],
            'ethnicity' => $personalInfo['ethnicity'] ?? null,
            'national_id' => $personalInfo['national_id'],
            'address' => $personalInfo['address'],
            'current_address' => [
                'line' => $personalInfo['current_address_line'] ?? null,
                'ward' => $personalInfo['current_ward'] ?? null,
                'province' => $personalInfo['current_province'] ?? null,
                'country' => $personalInfo['current_country'] ?? null,
            ],
            'email' => $personalInfo['email'],
            'phone' => $personalInfo['phone'] ?? null,
            'emergency_contact_name' => $personalInfo['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $personalInfo['emergency_contact_phone'] ?? null,
            'emergency_contact_relationship' => $personalInfo['emergency_contact_relationship'] ?? null,
            'emergency_contact_email' => $personalInfo['emergency_contact_email'] ?? null,
            'emergency_contact_name_1' => $personalInfo['emergency_contact_name_1'] ?? null,
            'emergency_contact_email_1' => $personalInfo['emergency_contact_email_1'] ?? null,
            'emergency_contact_phone_1' => $personalInfo['emergency_contact_phone_1'] ?? null,
            'emergency_contact_relationship_1' => $personalInfo['emergency_contact_relationship_1'] ?? null,
            'high_school_name' => $personalInfo['high_school_name'] ?? null,

            'cccd_address' => $personalInfo['cccd_address'],
            'cccd_address_detail' => [
                'line' => $personalInfo['cccd_address_line'] ?? null,
                'ward' => $personalInfo['cccd_ward'] ?? null,
                'province' => $personalInfo['cccd_province'] ?? null,
                'country' => $personalInfo['cccd_country'] ?? null,
            ],
            'avatar' => [
                'url' => $personalInfo['avatar_url'],
                'has_avatar' => ! empty($personalInfo['avatar_url']),
            ],
            'program' => [
                'id' => $program['id'] ?? null,
                'name' => $program['name'] ?? null,
                'code' => $program['code'] ?? null,
                'degree_type' => $program['degree_type'] ?? null,
                'duration_years' => $program['duration_years'] ?? null,
                'display_name' => ($program['code'] ?? '') . ' - ' . ($program['name'] ?? ''),
            ],
            'curriculum_version' => [
                'id' => $cv['id'] ?? null,
                'version' => $cvVersion,
                'effective_date' => $cv['effective_date'] ?? null,
                'display' => $cvVersion !== null && $cvVersion !== '' ? ('Version ' . $cvVersion) : null,
            ],
            'campus' => [
                'id' => $campus['id'] ?? null,
                'name' => $campus['name'] ?? null,
                'code' => $campus['code'] ?? null,
                'location' => $campus['location'] ?? null,
                'display_name' => ($campus['name'] ?? '') . (($campus['code'] ?? null) ? ' (' . $campus['code'] . ')' : ''),
            ],
            'enrollment' => [
                'enrollment_date' => $personalInfo['enrollment_date'] ?? null,
                'expected_graduation_date' => $personalInfo['expected_graduation_date'] ?? null,
                'study_mode' => $studyMode,
                'study_mode_display' => $this->getStudyModeDisplay($studyMode),
                'status' => $status,
                'status_display' => $this->getStatusDisplay($status),
                'status_color' => $this->getStatusColor($status),
            ],
        ];
    }

    /**
     * Format preferences
     */
    protected function formatPreferences(array $preferences): array
    {
        return [
            'localization' => [
                'language' => $preferences['language'],
                'language_display' => $this->getLanguageDisplay($preferences['language']),
                'timezone' => $preferences['timezone'],
                'date_format' => $preferences['date_format'],
                'time_format' => $preferences['time_format'],
                'time_format_display' => $preferences['time_format'] === '24h' ? '24 Hour' : '12 Hour',
            ],
            'appearance' => [
                'theme' => $preferences['theme'],
                'theme_display' => $this->getThemeDisplay($preferences['theme']),
            ],
            'notifications' => [
                'email_enabled' => $preferences['notifications']['email_enabled'],
                'push_enabled' => $preferences['notifications']['push_enabled'],
                'sms_enabled' => $preferences['notifications']['sms_enabled'],
                'enabled_channels' => $this->getEnabledChannels($preferences['notifications']),
            ],
        ];
    }

    /**
     * Format profile completion
     */
    protected function formatProfileCompletion(array $completion): array
    {
        return [
            'overview' => [
                'percentage' => $completion['percentage'],
                'completed_fields' => $completion['completed_fields'],
                'total_fields' => $completion['total_fields'],
                'status' => $completion['status'],
                'status_display' => $this->getCompletionStatusDisplay($completion['status']),
                'status_color' => $this->getCompletionStatusColor($completion['status']),
            ],
            'missing_fields' => collect($completion['missing_fields'])->map(function ($field) {
                return [
                    'field' => $field,
                    'display_name' => $this->getFieldDisplayName($field),
                    'category' => $this->getFieldCategory($field),
                    'priority' => $this->getFieldPriority($field),
                ];
            })->toArray(),
            'recommendations' => $this->getCompletionRecommendations($completion),
        ];
    }

    /**
     * Get study mode display
     */
    protected function getStudyModeDisplay(string $mode): string
    {
        return match ($mode) {
            'full_time' => 'Full Time',
            'part_time' => 'Part Time',
            'online' => 'Online',
            'hybrid' => 'Hybrid',
            default => ucfirst(str_replace('_', ' ', $mode)),
        };
    }

    /**
     * Get status display
     */
    protected function getStatusDisplay(string $status): string
    {
        return match ($status) {
            'active' => 'Active',
            'inactive' => 'Inactive',
            'suspended' => 'Suspended',
            'graduated' => 'Graduated',
            'withdrawn' => 'Withdrawn',
            default => ucfirst($status),
        };
    }

    /**
     * Get status color
     */
    protected function getStatusColor(string $status): string
    {
        return match ($status) {
            'active' => '#22c55e',      // Green
            'inactive' => '#f59e0b',    // Amber
            'suspended' => '#ef4444',   // Red
            'graduated' => '#3b82f6',   // Blue
            'withdrawn' => '#6b7280',   // Gray
            default => '#6b7280',       // Gray
        };
    }

    /**
     * Format address
     */
    protected function formatAddress(array $address): string
    {
        $parts = array_filter([
            $address['street'],
            $address['city'],
            $address['state'],
            $address['postal_code'],
            $address['country'],
        ]);

        return implode(', ', $parts);
    }

    /**
     * Check if address is complete
     */
    protected function isAddressComplete(array $address): bool
    {
        return ! empty($address['street']) &&
            ! empty($address['city']) &&
            ! empty($address['country']);
    }

    /**
     * Get completion status
     */
    protected function getCompletionStatus(float $percentage): string
    {
        return match (true) {
            $percentage >= 90 => 'excellent',
            $percentage >= 70 => 'good',
            $percentage >= 50 => 'satisfactory',
            $percentage >= 25 => 'needs_improvement',
            default => 'poor',
        };
    }

    /**
     * Get progress color
     */
    protected function getProgressColor(float $percentage): string
    {
        return match (true) {
            $percentage >= 80 => '#22c55e', // Green
            $percentage >= 60 => '#3b82f6', // Blue
            $percentage >= 40 => '#f59e0b', // Amber
            default => '#ef4444',           // Red
        };
    }

    /**
     * Get standing color
     */
    protected function getStandingColor(string $standing): string
    {
        return match ($standing) {
            'Dean\'s List', 'High Honors' => '#22c55e', // Green
            'Good Standing' => '#3b82f6',               // Blue
            'Satisfactory Standing' => '#f59e0b',       // Amber
            'Academic Probation' => '#ef4444',          // Red
            default => '#6b7280',                       // Gray
        };
    }

    /**
     * Get language display
     */
    protected function getLanguageDisplay(string $language): string
    {
        return match ($language) {
            'en' => 'English',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'zh' => 'Chinese',
            'ja' => 'Japanese',
            default => ucfirst($language),
        };
    }

    /**
     * Get theme display
     */
    protected function getThemeDisplay(string $theme): string
    {
        return match ($theme) {
            'light' => 'Light',
            'dark' => 'Dark',
            'auto' => 'Auto',
            default => ucfirst($theme),
        };
    }

    /**
     * Get enabled notification channels
     */
    protected function getEnabledChannels(array $notifications): array
    {
        $channels = [];

        if ($notifications['email_enabled']) {
            $channels[] = 'email';
        }
        if ($notifications['push_enabled']) {
            $channels[] = 'push';
        }
        if ($notifications['sms_enabled']) {
            $channels[] = 'sms';
        }

        return $channels;
    }

    /**
     * Get completion status display
     */
    protected function getCompletionStatusDisplay(string $status): string
    {
        return match ($status) {
            'complete' => 'Complete',
            'mostly_complete' => 'Mostly Complete',
            'partially_complete' => 'Partially Complete',
            'incomplete' => 'Incomplete',
            default => ucfirst($status),
        };
    }

    /**
     * Get completion status color
     */
    protected function getCompletionStatusColor(string $status): string
    {
        return match ($status) {
            'complete' => '#22c55e',           // Green
            'mostly_complete' => '#3b82f6',    // Blue
            'partially_complete' => '#f59e0b', // Amber
            'incomplete' => '#ef4444',         // Red
            default => '#6b7280',              // Gray
        };
    }

    /**
     * Get field display name
     */
    protected function getFieldDisplayName(string $field): string
    {
        return match ($field) {
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'Email Address',
            'phone' => 'Phone Number',
            'date_of_birth' => 'Date of Birth',
            'address_street' => 'Street Address',
            'address_city' => 'City',
            'emergency_contact_name' => 'Emergency Contact Name',
            'emergency_contact_phone' => 'Emergency Contact Phone',
            'emergency_contact_email' => 'Emergency Contact Email',
            'emergency_contact_name_1' => 'Emergency Contact Name 1',
            'emergency_contact_email_2' => 'Emergency Contact Email 2',
            'emergency_contact_phone_2' => 'Emergency Contact Phone 2',
            'emergency_contact_relationship_2' => 'Emergency Contact Relationship 2',
            'avatar_path' => 'Profile Photo',
            default => ucfirst(str_replace('_', ' ', $field)),
        };
    }

    /**
     * Get field category
     */
    protected function getFieldCategory(string $field): string
    {
        return match (true) {
            str_starts_with($field, 'emergency_') => 'emergency_contact',
            str_starts_with($field, 'address_') => 'address',
            in_array($field, ['first_name', 'last_name', 'date_of_birth', 'avatar_path']) => 'personal',
            in_array($field, ['email', 'phone']) => 'contact',
            default => 'other',
        };
    }

    /**
     * Get field priority
     */
    protected function getFieldPriority(string $field): string
    {
        return match ($field) {
            'first_name', 'last_name', 'email' => 'high',
            'phone', 'emergency_contact_name', 'emergency_contact_phone' => 'medium',
            default => 'low',
        };
    }

    /**
     * Get completion recommendations
     */
    protected function getCompletionRecommendations(array $completion): array
    {
        $recommendations = [];

        if ($completion['percentage'] < 50) {
            $recommendations[] = 'Complete your basic profile information to improve your experience';
        }

        if (in_array('phone', $completion['missing_fields'])) {
            $recommendations[] = 'Add your phone number for important notifications';
        }

        if (
            in_array('emergency_contact_name', $completion['missing_fields']) ||
            in_array('emergency_contact_phone', $completion['missing_fields'])
        ) {
            $recommendations[] = 'Add emergency contact information for safety purposes';
        }

        if (in_array('avatar_path', $completion['missing_fields'])) {
            $recommendations[] = 'Upload a profile photo to personalize your account';
        }

        return $recommendations;
    }
}
