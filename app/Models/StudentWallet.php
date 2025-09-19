<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentWallet extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'student_wallets';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'student_id',
        'balance',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'balance' => 'integer',
        'updated_at' => 'datetime',
    ];

    /**
     * Indicates if the model should be timestamped.
     * Only tracks updated_at, not created_at.
     */
    public $timestamps = false;

    /**
     * Get the student that owns this wallet.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get all transactions for this wallet.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'student_id', 'student_id')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get recent transactions for this wallet.
     */
    public function recentTransactions(int $limit = 10): HasMany
    {
        return $this->transactions()->limit($limit);
    }
}
