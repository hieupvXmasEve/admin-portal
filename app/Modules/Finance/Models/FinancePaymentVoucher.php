<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\Campus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancePaymentVoucher extends Model
{
    public const SKIP_DNG_HELD = 'dng_held';

    public const SKIP_NO_ACTIVE_LINE = 'no_active_line';

    public const SKIP_CAPPED = 'capped';

    public const SKIP_STUDENT_MISMATCH = 'student_mismatch';

    public const WORK_TYPE_ENROLLED_UNAPPLIED_CASH = 'enrolled_unapplied_cash';

    protected $fillable = [
        'voucher_number',
        'payment_id',
        'campus_id',
        'issued_by_user_id',
        'issued_at',
        'allocations_snapshot',
        'skipped_snapshot',
        'unapplied_amount',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'allocations_snapshot' => 'array',
        'skipped_snapshot' => 'array',
        'unapplied_amount' => 'decimal:2',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }
}
