<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseRegistrationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'status' => $this->resource['status'] ?? 'pending',
            'status_display' => $this->getStatusDisplay($this->resource['status'] ?? 'pending'),
            'registration_date' => $this->resource['registration_date'],
            'drop_date' => $this->resource['drop_date'],
            'unit' => [
                'code' => $this->resource['unit']['code'],
                'name' => $this->resource['unit']['name'],
                'credit_hours' => (float) $this->resource['unit']['credit_hours'],
            ],
            'lecturer' => [
                'name' => $this->resource['lecturer']['name'] ?? null,
                'email' => $this->resource['lecturer']['email'] ?? null,
            ],
            'semester' => [
                'name' => $this->resource['semester']['name'],
                'code' => $this->resource['semester']['code'],
            ],
            'actions' => $this->getAvailableActions($this->resource['status'] ?? 'pending'),
        ];
    }

    /**
     * Get status display text
     */
    protected function getStatusDisplay(string $status): string
    {
        return match ($status) {
            'registered' => 'Registered',
            'pending' => 'Pending',
            'waitlisted' => 'Waitlisted',
            'dropped' => 'Dropped',
            'completed' => 'Completed',
            'failed' => 'Failed',
            default => ucfirst($status),
        };
    }

    /**
     * Get available actions for the registration
     */
    protected function getAvailableActions(string $status): array
    {
        $actions = [];

        switch ($status) {
            case 'registered':
                $actions[] = [
                    'action' => 'drop',
                    'label' => 'Drop Course',
                    'method' => 'DELETE',
                    'confirmation_required' => true,
                ];
                break;

            case 'waitlisted':
                $actions[] = [
                    'action' => 'remove_from_waitlist',
                    'label' => 'Remove from Waitlist',
                    'method' => 'DELETE',
                    'confirmation_required' => true,
                ];
                break;

            case 'pending':
                $actions[] = [
                    'action' => 'cancel',
                    'label' => 'Cancel Registration',
                    'method' => 'DELETE',
                    'confirmation_required' => true,
                ];
                break;
        }

        return $actions;
    }
}
