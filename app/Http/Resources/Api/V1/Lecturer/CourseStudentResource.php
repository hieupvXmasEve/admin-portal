<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Lecturer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseStudentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'student_id' => $this->resource['student_id'],
            'student_number' => $this->resource['student_number'],
            'full_name' => $this->resource['full_name'],
            'email' => $this->resource['email'],
            'registration_date' => $this->resource['registration_date'],
            
            // Attendance Information
            'attendance' => [
                'percentage' => $this->resource['attendance_percentage'],
                'sessions_attended' => $this->resource['sessions_attended'],
                'total_sessions' => $this->resource['total_sessions'],
                'last_attendance' => $this->resource['last_attendance'],
                'status' => $this->resource['status'],
                'status_label' => $this->getStatusLabel($this->resource['status']),
                'status_color' => $this->getStatusColor($this->resource['status']),
            ],
            
            // Risk Assessment
            'risk_assessment' => [
                'level' => $this->getRiskLevel(),
                'factors' => $this->getRiskFactors(),
                'recommendations' => $this->getRecommendations(),
            ],
            
            // Quick Actions
            'actions' => [
                'can_contact' => true,
                'can_add_note' => true,
                'needs_attention' => $this->needsAttention(),
                'can_view_details' => true,
            ],
        ];
    }

    /**
     * Get status label for display
     */
    protected function getStatusLabel(string $status): string
    {
        return match ($status) {
            'excellent' => 'Excellent Attendance',
            'good' => 'Good Attendance',
            'warning' => 'Attendance Warning',
            'at_risk' => 'At Risk',
            default => 'Unknown Status',
        };
    }

    /**
     * Get status color for UI
     */
    protected function getStatusColor(string $status): string
    {
        return match ($status) {
            'excellent' => 'green',
            'good' => 'blue',
            'warning' => 'yellow',
            'at_risk' => 'red',
            default => 'gray',
        };
    }

    /**
     * Get risk level based on attendance
     */
    protected function getRiskLevel(): string
    {
        $percentage = $this->resource['attendance_percentage'];
        $lastAttendance = $this->resource['last_attendance'];
        
        // High risk if attendance below 60% or no attendance in last 2 weeks
        if ($percentage < 60) {
            return 'high';
        }
        
        if ($lastAttendance && \Carbon\Carbon::parse($lastAttendance)->lt(now()->subWeeks(2))) {
            return 'high';
        }
        
        // Medium risk if attendance below 75%
        if ($percentage < 75) {
            return 'medium';
        }
        
        // Low risk otherwise
        return 'low';
    }

    /**
     * Get risk factors
     */
    protected function getRiskFactors(): array
    {
        $factors = [];
        $percentage = $this->resource['attendance_percentage'];
        $lastAttendance = $this->resource['last_attendance'];
        
        if ($percentage < 50) {
            $factors[] = 'Very low attendance rate';
        } elseif ($percentage < 75) {
            $factors[] = 'Below average attendance rate';
        }
        
        if ($lastAttendance) {
            $daysSinceLastAttendance = \Carbon\Carbon::parse($lastAttendance)->diffInDays(now());
            if ($daysSinceLastAttendance > 14) {
                $factors[] = 'No recent attendance';
            } elseif ($daysSinceLastAttendance > 7) {
                $factors[] = 'Missing recent sessions';
            }
        } else {
            $factors[] = 'No attendance recorded';
        }
        
        if ($this->resource['sessions_attended'] === 0 && $this->resource['total_sessions'] > 0) {
            $factors[] = 'Never attended any session';
        }
        
        return $factors;
    }

    /**
     * Get recommendations for this student
     */
    protected function getRecommendations(): array
    {
        $recommendations = [];
        $riskLevel = $this->getRiskLevel();
        $percentage = $this->resource['attendance_percentage'];
        
        if ($riskLevel === 'high') {
            $recommendations[] = 'Contact student immediately';
            $recommendations[] = 'Schedule one-on-one meeting';
            $recommendations[] = 'Consider academic intervention';
        } elseif ($riskLevel === 'medium') {
            $recommendations[] = 'Send attendance reminder';
            $recommendations[] = 'Monitor closely';
        }
        
        if ($percentage < 75) {
            $recommendations[] = 'Discuss attendance policy';
            $recommendations[] = 'Provide additional support resources';
        }
        
        if (empty($recommendations)) {
            $recommendations[] = 'Continue monitoring';
        }
        
        return $recommendations;
    }

    /**
     * Check if student needs attention
     */
    protected function needsAttention(): bool
    {
        return $this->getRiskLevel() !== 'low';
    }
}
