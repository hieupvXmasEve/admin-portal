<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Module extends AuditableModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'campus_id',
        'code',
        'name',
        'description',
        'grading_type',
        'total_credits',
        'prerequisite_module_id',
    ];

    protected $casts = [
        'total_credits' => 'decimal:2',
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'module_units')
            ->withPivot('grading_type', 'weight', 'order')
            ->withTimestamps()
            ->orderBy('module_units.order');
    }

    public function curriculumModules(): HasMany
    {
        return $this->hasMany(CurriculumModule::class);
    }

    public function prerequisiteModule(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'prerequisite_module_id');
    }

    public function dependentModules(): HasMany
    {
        return $this->hasMany(Module::class, 'prerequisite_module_id');
    }

    public function calculateTotalCredits(): float
    {
        return (float) $this->units()->sum('units.credit_points');
    }

    public function getSubUnits(): Collection
    {
        return $this->units()->orderBy('module_units.order')->get();
    }

    protected function getLoggingLevel(): string
    {
        return static::LOG_LEVEL_COMPREHENSIVE;
    }

    protected function getIdentifierForLog(): string
    {
        return "{$this->code} - {$this->name}";
    }

    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "Created module: {$identifier}",
            'updated' => "Updated module: {$identifier}",
            'deleted' => "Deleted module: {$identifier}",
            'restored' => "Restored module: {$identifier}",
            default => "{$eventName} module: {$identifier}",
        };
    }

    protected function getCustomLogProperties(): array
    {
        return [
            'campus_id' => $this->campus_id,
            'code' => $this->code,
            'name' => $this->name,
            'grading_type' => $this->grading_type,
            'total_credits' => $this->total_credits,
            'units_count' => $this->units()->count(),
        ];
    }
}
