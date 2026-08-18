<?php

declare(strict_types=1);

namespace App\Models;

use App\Modules\Finance\Models\InvoiceDiscount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EgcBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'semester_id',
        'block_number',
        'level_number',
        'result',
        'attendance_rate',
        'is_retake',
        'retake_discount_id',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'block_number' => 'integer',
            'level_number' => 'integer',
            'attendance_rate' => 'decimal:2',
            'is_retake' => 'boolean',
            'synced_at' => 'datetime',
        ];
    }

    public const RESULT_PENDING = 'pending';

    public const RESULT_PASS = 'pass';

    public const RESULT_FAIL = 'fail';

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function retakeDiscount(): BelongsTo
    {
        return $this->belongsTo(InvoiceDiscount::class, 'retake_discount_id');
    }
}
