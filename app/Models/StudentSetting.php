<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentSetting extends Model
{
    use HasFactory;

    protected $primaryKey = 'student_id';
    public $incrementing = false;

    protected $fillable = [
        'student_id',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
