<?php

namespace App\Models;

use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\StudentInvoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherApplication extends Model
{
    protected $fillable = [
        'voucher_definition_id',
        'student_id',
        'semester_id',
        'invoice_id',
        'status',
        'applied_at',
        'applied_by_user_id',
        'base_amount',
        'discount_amount',
        'finance_charge_id',
        'note',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'base_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
    ];

    /**
     * Get the voucher definition
     */
    public function voucherDefinition(): BelongsTo
    {
        return $this->belongsTo(VoucherDefinition::class, 'voucher_definition_id');
    }

    /**
     * Get the student
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the semester
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Get the invoice associated with this application, if already consumed.
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(StudentInvoice::class, 'invoice_id');
    }

    /**
     * Get the user who applied the voucher
     */
    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by_user_id');
    }

    /**
     * Get the finance charge associated with this application
     */
    public function financeCharge(): BelongsTo
    {
        return $this->belongsTo(FinanceCharge::class);
    }
}
