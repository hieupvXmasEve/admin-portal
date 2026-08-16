<?php

declare(strict_types=1);

namespace App\Modules\Academic\Catalog\Models;

use App\Models\Semester;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampusPeriodSchedule extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'campus_id',
        'semester_id',
        'operating_start_date',
        'operating_end_date',
        'registration_start_date',
        'registration_end_date',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'operating_start_date' => 'immutable_date:Y-m-d',
        'operating_end_date' => 'immutable_date:Y-m-d',
        'registration_start_date' => 'immutable_date:Y-m-d',
        'registration_end_date' => 'immutable_date:Y-m-d',
    ];

    public function academicPeriod(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }
}
