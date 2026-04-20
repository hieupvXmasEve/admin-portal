<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE invoice_discounts MODIFY COLUMN discount_type ENUM('scholarship', 'voucher', 'egc_retake') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE invoice_discounts MODIFY COLUMN discount_type ENUM('scholarship', 'voucher') NOT NULL");
    }
};
