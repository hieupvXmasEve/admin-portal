<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CanvasCourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'canvas_course_id' => $this->canvas_course_id,
            'canvas_course_code' => $this->canvas_course_code,
            'canvas_course_name' => $this->canvas_course_name,
            'canvas_url' => $this->getCanvasUrl(),
            'sync_status' => $this->sync_status,
            'is_mapped' => $this->isMapped(),
            'is_ignored' => $this->isIgnored(),
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'course_offering' => $this->when($this->courseOffering, [
                'id' => $this->courseOffering?->id,
                'course_code' => $this->courseOffering?->course_code,
                'course_title' => $this->courseOffering?->course_title,
                'section_code' => $this->courseOffering?->section_code,
                'semester' => $this->courseOffering?->semester ? [
                    'id' => $this->courseOffering->semester->id,
                    'name' => $this->courseOffering->semester->name,
                    'code' => $this->courseOffering->semester->code,
                ] : null,
            ]),
            'canvas_data' => $this->when($request->has('include_canvas_data'), $this->canvas_data),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
