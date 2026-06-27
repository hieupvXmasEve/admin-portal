<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A parent or other responsible adult linked to an Application (1-n).
 *
 * `relationship` is validated against a backend allow-list, not a DB enum, so
 * new values can be added without a migration. Exactly one Guardian per
 * Application is primary: the DB enforces "at most one" and the
 * ApplicationGuardianService keeps "at least one".
 */
class ApplicationGuardian extends Model
{
    use HasFactory;

    /**
     * Relationship allow-list (BE-validated; adding a value needs no migration).
     */
    public const RELATIONSHIPS = [
        'father',
        'mother',
        'guardian',
        'grandfather',
        'grandmother',
        'sibling',
        'uncle',
        'aunt',
        'other',
    ];

    /**
     * @return list<string>
     */
    public static function relationships(): array
    {
        return self::RELATIONSHIPS;
    }

    protected $fillable = [
        'student_application_id',
        'full_name',
        'relationship',
        'phone',
        'email',
        'occupation',
        'address',
        'is_primary',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    protected $attributes = [
        'is_primary' => false,
    ];

    /**
     * The Application this Guardian belongs to.
     */
    public function studentApplication(): BelongsTo
    {
        return $this->belongsTo(StudentApplication::class);
    }
}
