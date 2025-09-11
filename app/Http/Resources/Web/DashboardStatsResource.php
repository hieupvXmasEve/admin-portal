<?php

declare(strict_types=1);

namespace App\Http\Resources\Web;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardStatsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        return [
            'students' => [
                'total' => (int) ($data['students']['total'] ?? 0),
                'by_status' => [
                    'active' => (int) ($data['students']['by_status']['active'] ?? 0),
                    'inactive' => (int) ($data['students']['by_status']['inactive'] ?? 0),
                    'suspended' => (int) ($data['students']['by_status']['suspended'] ?? 0),
                    'graduated' => (int) ($data['students']['by_status']['graduated'] ?? 0),
                ],
            ],
            'lecturers' => [
                'total' => (int) ($data['lecturers']['total'] ?? 0),
                'by_employment_type' => [
                    'full_time' => (int) ($data['lecturers']['by_employment_type']['full_time'] ?? 0),
                    'part_time' => (int) ($data['lecturers']['by_employment_type']['part_time'] ?? 0),
                    'visiting' => (int) ($data['lecturers']['by_employment_type']['visiting'] ?? 0),
                    'contract' => (int) ($data['lecturers']['by_employment_type']['contract'] ?? 0),
                ],
            ],
            'academics' => [
                'programs' => (int) ($data['academics']['programs'] ?? 0),
                'specializations' => (int) ($data['academics']['specializations'] ?? 0),
                'active_curriculum_versions' => (int) ($data['academics']['active_curriculum_versions'] ?? 0),
            ],
            'semester' => $data['semester'] ?? null,
            'rooms' => [
                'total' => (int) ($data['rooms']['total'] ?? 0),
                'available' => (int) ($data['rooms']['available'] ?? 0),
                'occupied' => (int) ($data['rooms']['occupied'] ?? 0),
                'maintenance' => (int) ($data['rooms']['maintenance'] ?? 0),
            ],
        ];
    }
}
