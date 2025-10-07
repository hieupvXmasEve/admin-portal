<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherRedemption extends Model
{
    protected $fillable = [
        'voucher_id',
        'student_id',
        'invoice_id',
        'redeemed_at',
    ];

    protected $casts = [
        'redeemed_at' => 'datetime',
    ];

    /**
     * Get the voucher definition
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(VoucherDefinition::class, 'voucher_id');
    }

    /**
     * Get the student who redeemed the voucher
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the invoice associated with this redemption
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(StudentInvoice::class, 'invoice_id');
    }
}
