<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CanvasCourseMapping extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'canvas_integration_id',
        'canvas_course_id',
        'canvas_course_code',
        'canvas_course_name',
        'course_offering_id',
        'canvas_data',
        'sync_status',
        'last_synced_at',
    ];

    protected $casts = [
        'canvas_data' => 'array',
        'last_synced_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Validation Rules
    public static function validationRules(): array
    {
        return [
            'canvas_integration_id' => ['required', 'exists:canvas_integrations,id'],
            'canvas_course_id' => ['required', 'string', 'max:255'],
            'canvas_course_code' => ['nullable', 'string', 'max:255'],
            'canvas_course_name' => ['nullable', 'string', 'max:500'],
            'course_offering_id' => ['nullable', 'exists:course_offerings,id'],
            'canvas_data' => ['nullable', 'array'],
            'sync_status' => ['nullable', 'in:pending,mapped,ignored'],
        ];
    }

    // Relationships
    public function canvasIntegration(): BelongsTo
    {
        return $this->belongsTo(CanvasIntegration::class);
    }

    public function courseOffering(): BelongsTo
    {
        return $this->belongsTo(CourseOffering::class);
    }

    // Helper Methods
    public function isMapped(): bool
    {
        return $this->sync_status === 'mapped' && ! is_null($this->course_offering_id);
    }

    public function isIgnored(): bool
    {
        return $this->sync_status === 'ignored';
    }

    public function isPending(): bool
    {
        return $this->sync_status === 'pending';
    }

    public function mapTo(int $courseOfferingId): void
    {
        $this->update([
            'course_offering_id' => $courseOfferingId,
            'sync_status' => 'mapped',
            'last_synced_at' => now(),
        ]);
    }

    public function unmap(): void
    {
        $this->update([
            'course_offering_id' => null,
            'sync_status' => 'pending',
        ]);
    }

    public function ignore(): void
    {
        $this->update([
            'sync_status' => 'ignored',
        ]);
    }

    public function getCanvasUrl(): ?string
    {
        $integration = $this->canvasIntegration;
        if (! $integration) {
            return null;
        }

        return rtrim($integration->canvas_url, '/') . "/courses/{$this->canvas_course_id}";
    }
}
