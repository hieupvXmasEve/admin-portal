<?php

declare(strict_types=1);

namespace App\Modules\StudentRegistry\Models;

use Database\Factories\Modules\StudentRegistry\Models\StudentGuardianRelationshipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class StudentGuardianRelationship extends Model
{
    /** @use HasFactory<StudentGuardianRelationshipFactory> */
    use HasFactory;

    protected $fillable = [
        'student_id',
        'source_application_guardian_id',
        'legacy_parent_student_id',
        'full_name',
        'relationship_type',
        'phone',
        'email',
        'occupation',
        'address',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }
}
