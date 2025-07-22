<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'personal_info' => $this->formatPersonalInfo($this->resource['personal_info']),
            'academic_info' => $this->formatAcademicInfo($this->resource['academic_info']),
            'contact_info' => $this->formatContactInfo($this->resource['contact_info']),
            'enrollment_info' => $this->formatEnrollmentInfo($this->resource['enrollment_info']),
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
        return [
            'student_id' => $personalInfo['student_id'],
            'name' => [
                'first_name' => $personalInfo['first_name'],
                'last_name' => $personalInfo['last_name'],
                'full_name' => $personalInfo['full_name'],
                'preferred_name' => $personalInfo['preferred_name'],
                'display_name' => $personalInfo['preferred_name'] ?: $personalInfo['full_name'],
            ],
            'personal_details' => [
                'date_of_birth' => $personalInfo['date_of_birth'],
                'age' => $personalInfo['date_of_birth'] 
                    ? \Carbon\Carbon::parse($personalInfo['date_of_birth'])->age 
                    : null,
                'gender' => $personalInfo['gender'],
                'nationality' => $personalInfo['nationality'],
            ],
            'avatar' => [
                'url' => $personalInfo['avatar_url'],
                'has_avatar' => !empty($personalInfo['avatar_url']),
            ],
        ];
    }

    /**
     * Format academic information
     */
    protected function formatAcademicInfo(array $academicInfo): array
    {
        return [
            'program' => [
                'id' => $academicInfo['program']['id'],
                'name' => $academicInfo['program']['name'],
                'code' => $academicInfo['program']['code'],
                'degree_type' => $academicInfo['program']['degree_type'],
                'duration_years' => $academicInfo['program']['duration_years'],
                'display_name' => $academicInfo['program']['code'] . ' - ' . $academicInfo['program']['name'],
            ],
            'curriculum_version' => [
                'id' => $academicInfo['curriculum_version']['id'],
                'version' => $academicInfo['curriculum_version']['version'],
                'effective_date' => $academicInfo['curriculum_version']['effective_date'],
                'display' => 'Version ' . $academicInfo['curriculum_version']['version'],
            ],
            'campus' => [
                'id' => $academicInfo['campus']['id'],
                'name' => $academicInfo['campus']['name'],
                'code' => $academicInfo['campus']['code'],
                'location' => $academicInfo['campus']['location'],
                'display_name' => $academicInfo['campus']['name'] . ' (' . $academicInfo['campus']['code'] . ')',
            ],
            'enrollment' => [
                'enrollment_date' => $academicInfo['enrollment_date'],
                'expected_graduation_date' => $academicInfo['expected_graduation_date'],
                'study_mode' => $academicInfo['study_mode'],
                'study_mode_display' => $this->getStudyModeDisplay($academicInfo['study_mode']),
                'status' => $academicInfo['status'],
                'status_display' => $this->getStatusDisplay($academicInfo['status']),
                'status_color' => $this->getStatusColor($academicInfo['status']),
            ],
        ];
    }

    /**
     * Format contact information
     */
    protected function formatContactInfo(array $contactInfo): array
    {
        return [
            'primary_contact' => [
                'email' => $contactInfo['email'],
                'phone' => $contactInfo['phone'],
                'has_phone' => !empty($contactInfo['phone']),
            ],
            'emergency_contact' => [
                'name' => $contactInfo['emergency_contact_name'],
                'phone' => $contactInfo['emergency_contact_phone'],
                'relationship' => $contactInfo['emergency_contact_relationship'],
                'is_complete' => !empty($contactInfo['emergency_contact_name']) && 
                               !empty($contactInfo['emergency_contact_phone']),
            ],
            'address' => [
                'street' => $contactInfo['address']['street'],
                'city' => $contactInfo['address']['city'],
                'state' => $contactInfo['address']['state'],
                'postal_code' => $contactInfo['address']['postal_code'],
                'country' => $contactInfo['address']['country'],
                'formatted_address' => $this->formatAddress($contactInfo['address']),
                'is_complete' => $this->isAddressComplete($contactInfo['address']),
            ],
        ];
    }

    /**
     * Format enrollment information
     */
    protected function formatEnrollmentInfo(array $enrollmentInfo): array
    {
        return [
            'credit_summary' => [
                'total_credits_earned' => $enrollmentInfo['total_credits_earned'],
                'total_credits_required' => $enrollmentInfo['total_credits_required'],
                'credits_remaining' => $enrollmentInfo['credits_remaining'],
                'completion_percentage' => $enrollmentInfo['completion_percentage'],
                'current_semester_credits' => $enrollmentInfo['current_semester_credits'],
            ],
            'progress_indicators' => [
                'completion_status' => $this->getCompletionStatus($enrollmentInfo['completion_percentage']),
                'progress_color' => $this->getProgressColor($enrollmentInfo['completion_percentage']),
                'is_on_track' => $enrollmentInfo['completion_percentage'] >= 50, // Simplified logic
                'academic_standing' => $enrollmentInfo['academic_standing'],
                'standing_color' => $this->getStandingColor($enrollmentInfo['academic_standing']),
            ],
            'milestones' => [
                'halfway_complete' => $enrollmentInfo['completion_percentage'] >= 50,
                'near_graduation' => $enrollmentInfo['completion_percentage'] >= 80,
                'ready_to_graduate' => $enrollmentInfo['completion_percentage'] >= 95,
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
        return !empty($address['street']) && 
               !empty($address['city']) && 
               !empty($address['country']);
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

        if (in_array('emergency_contact_name', $completion['missing_fields']) || 
            in_array('emergency_contact_phone', $completion['missing_fields'])) {
            $recommendations[] = 'Add emergency contact information for safety purposes';
        }

        if (in_array('avatar_path', $completion['missing_fields'])) {
            $recommendations[] = 'Upload a profile photo to personalize your account';
        }

        return $recommendations;
    }
}
