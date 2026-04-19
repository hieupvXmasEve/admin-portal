---
phase: 1
title: Database — Migration + Model
status: pending
---

# Phase 1: Database

## Overview

- Thêm `egc_retake_fee` vào enum `finance_charges.charge_type`
- Tạo bảng `egc_block_retakes` để audit + idempotency
- Tạo Model `EgcBlockRetake`

---

## 1.1 Migration: Add `egc_retake_fee` to finance_charges enum

**File:** `database/migrations/{timestamp}_add_egc_retake_fee_to_finance_charges_charge_type.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL: modify enum to add new value
        DB::statement("ALTER TABLE finance_charges MODIFY COLUMN charge_type ENUM(
            'tuition_term',
            'egc_level_fee',
            'egc_retake_fee',
            'retake_fee',
            'course_fee',
            'manual_fee',
            'admission_fee',
            'defer_credit',
            'egc_exempt_credit',
            'scholarship_credit',
            'voucher_credit',
            'adjustment'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE finance_charges MODIFY COLUMN charge_type ENUM(
            'tuition_term',
            'egc_level_fee',
            'retake_fee',
            'course_fee',
            'manual_fee',
            'admission_fee',
            'defer_credit',
            'egc_exempt_credit',
            'scholarship_credit',
            'voucher_credit',
            'adjustment'
        ) NOT NULL");
    }
};
```

> **Lưu ý:** Verify full enum list bằng cách query DB trước khi chạy migration:
> `SHOW COLUMNS FROM finance_charges LIKE 'charge_type';`

---

## 1.2 Migration: Create `egc_block_retakes` table

**File:** `database/migrations/{timestamp}_create_egc_block_retakes_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('egc_block_retakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignId('semester_id')->constrained('semesters')->restrictOnDelete();

            $table->unsignedTinyInteger('block_number')->default(1); // Block bị trượt (1)
            $table->unsignedTinyInteger('level');                    // Level bị trượt (X)

            // Finance charge references
            $table->foreignId('voided_charge_id')                   // Level X+1 charge bị void
                  ->constrained('finance_charges')
                  ->restrictOnDelete();
            $table->foreignId('retake_charge_id')                   // Level X retake charge (50%)
                  ->constrained('finance_charges')
                  ->restrictOnDelete();

            // Amounts snapshot (tại thời điểm xử lý)
            $table->decimal('original_amount', 15, 0);  // Amount của Level X+1 charge bị void (vd: 15,000,000)
            $table->decimal('retake_amount', 15, 0);    // Amount của retake charge (vd: 7,500,000)
            $table->decimal('credit_amount', 15, 0);    // Credit carry-forward = original - retake (vd: 7,500,000)

            // Attendance condition confirmed at time of processing
            $table->boolean('meets_attendance_requirement')->default(true);
            $table->decimal('attendance_percentage', 5, 2)->nullable();

            $table->foreignId('recorded_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // Idempotency: một student chỉ có một retake record per block per level per semester
            $table->unique(['student_id', 'semester_id', 'block_number', 'level'], 'egc_retake_unique');

            $table->index(['semester_id', 'block_number']);
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('egc_block_retakes');
    }
};
```

---

## 1.3 Model: EgcBlockRetake

**File:** `app/Models/EgcBlockRetake.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EgcBlockRetake extends Model
{
    protected $fillable = [
        'student_id',
        'semester_id',
        'block_number',
        'level',
        'voided_charge_id',
        'retake_charge_id',
        'original_amount',
        'retake_amount',
        'credit_amount',
        'meets_attendance_requirement',
        'attendance_percentage',
        'recorded_by_user_id',
    ];

    protected $casts = [
        'block_number' => 'integer',
        'level' => 'integer',
        'original_amount' => 'decimal:0',
        'retake_amount' => 'decimal:0',
        'credit_amount' => 'decimal:0',
        'meets_attendance_requirement' => 'boolean',
        'attendance_percentage' => 'decimal:2',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function voidedCharge(): BelongsTo
    {
        return $this->belongsTo(FinanceCharge::class, 'voided_charge_id');
    }

    public function retakeCharge(): BelongsTo
    {
        return $this->belongsTo(FinanceCharge::class, 'retake_charge_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'recorded_by_user_id');
    }
}
```

---

## 1.4 Update FinanceCharge Model

**File:** `app/Models/FinanceCharge.php`

Thêm constant và update VALID_TYPES array:

```php
// Thêm sau TYPE_EGC_LEVEL_FEE
public const TYPE_EGC_RETAKE_FEE = 'egc_retake_fee';

// Thêm vào VALID_TYPES array (nếu có)
self::TYPE_EGC_RETAKE_FEE,
```

---

## Todo

- [ ] Verify current enum values từ DB trước khi viết migration
- [ ] Tạo migration: add `egc_retake_fee` to charge_type enum
- [ ] Tạo migration: create `egc_block_retakes` table
- [ ] Tạo model `EgcBlockRetake`
- [ ] Update `FinanceCharge` model: add TYPE_EGC_RETAKE_FEE constant
- [ ] Chạy `php artisan migrate` và verify
- [ ] Chạy `./vendor/bin/pint --dirty`

## Success Criteria

- `php artisan migrate` không lỗi
- `FinanceCharge::TYPE_EGC_RETAKE_FEE` available
- `EgcBlockRetake::create([...])` hoạt động với unique constraint
