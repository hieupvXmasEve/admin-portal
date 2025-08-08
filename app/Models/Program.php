<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends AuditableModel
{
    /** @use HasFactory<\Database\Factories\ProgramFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'description',
    ];

    protected $casts = [
        'code' => 'string',
        'description' => 'string',
    ];

    /**
     * Get the curriculum versions for the program.
     */
    public function curriculumVersions(): HasMany
    {
        return $this->hasMany(CurriculumVersion::class);
    }

    /**
     * Get the specializations for the program.
     */
    public function specializations(): HasMany
    {
        return $this->hasMany(Specialization::class);
    }

    /**
     * Get only active specializations for the program.
     */
    public function activeSpecializations(): HasMany
    {
        return $this->hasMany(Specialization::class)->where('is_active', true);
    }

    /**
     * Configure standard logging for programs
     */
    protected function getLoggingLevel(): string
    {
        return static::LOG_LEVEL_STANDARD;
    }

    /**
     * Get standard fields for logging
     */
    protected function getStandardLogFields(): array
    {
        return ['name', 'code', 'description'];
    }

    /**
     * Get identifier for logging
     */
    protected function getIdentifierForLog(): string
    {
        return $this->name ?? $this->code ?? "Program ID {$this->getKey()}";
    }

    /**
     * Custom activity descriptions for program events
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "Created program: {$identifier}",
            'updated' => "Updated program: {$identifier}",
            'deleted' => "Deleted program: {$identifier}",
            'restored' => "Restored program: {$identifier}",
            default => "{$eventName} program: {$identifier}",
        };
    }

    /**
     * Additional properties to log
     */
    protected function getCustomLogProperties(): array
    {
        return [
            'program_code' => $this->code,
            'specializations_count' => $this->specializations()->count(),
            'active_specializations_count' => $this->activeSpecializations()->count(),
        ];
    }
}
