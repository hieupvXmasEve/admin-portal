<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquivalentUnit extends AuditableModel
{
    /** @use HasFactory<\Database\Factories\EquivalentUnitFactory> */
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'equivalent_unit_id',
        'reason',
    ];

    /**
     * Get the unit that has the equivalence.
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * Get the equivalent unit.
     */
    public function equivalentUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'equivalent_unit_id');
    }

    // ========== AUDIT LOGGING CONFIGURATION ==========

    /**
     * Configure standard logging for unit equivalencies
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
        return [
            'unit_id',
            'equivalent_unit_id',
            'reason',
        ];
    }

    /**
     * Get identifier for logging
     */
    protected function getIdentifierForLog(): string
    {
        $unitCode = $this->unit?->code ?? "Unit ID {$this->unit_id}";
        $equivalentCode = $this->equivalentUnit?->code ?? "Unit ID {$this->equivalent_unit_id}";
        
        return "{$unitCode} ≡ {$equivalentCode}";
    }

    /**
     * Custom activity descriptions
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        $identifier = $this->getIdentifierForLog();

        return match ($eventName) {
            'created' => "Established unit equivalency: {$identifier}",
            'updated' => "Updated unit equivalency: {$identifier}",
            'deleted' => "Removed unit equivalency: {$identifier}",
            'restored' => "Restored unit equivalency: {$identifier}",
            default => "{$eventName} unit equivalency: {$identifier}",
        };
    }

    /**
     * Additional properties to log
     */
    protected function getCustomLogProperties(): array
    {
        return [
            'unit' => [
                'code' => $this->unit?->code,
                'name' => $this->unit?->name,
                'credit_points' => $this->unit?->credit_points,
            ],
            'equivalent_unit' => [
                'code' => $this->equivalentUnit?->code,
                'name' => $this->equivalentUnit?->name,
                'credit_points' => $this->equivalentUnit?->credit_points,
            ],
            'reason' => $this->reason,
            'credit_point_match' => $this->unit?->credit_points === $this->equivalentUnit?->credit_points,
        ];
    }
}
