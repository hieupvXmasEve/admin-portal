<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Models;

use App\Models\AuditableModel;
use App\Models\Campus;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DngCampusMapping extends AuditableModel
{
    protected $table = 'finance_dng_campus_mappings';

    protected $fillable = [
        'campus_id',
        'provider_code',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    protected function getIdentifierForLog(): string
    {
        return $this->provider_code;
    }
}
