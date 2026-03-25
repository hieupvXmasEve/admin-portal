<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('student_invoices', 'subtotal')) {
                $table->decimal('subtotal', 15, 2)->default(0)->after('semester_id');
            }

            if (! Schema::hasColumn('student_invoices', 'discount_total')) {
                $table->decimal('discount_total', 15, 2)->default(0)->after('subtotal');
            }

            if (! Schema::hasColumn('student_invoices', 'total_amount')) {
                $table->decimal('total_amount', 15, 2)->default(0)->after('discount_total');
            }

            if (! Schema::hasColumn('student_invoices', 'paid_amount')) {
                $table->decimal('paid_amount', 15, 2)->default(0)->after('total_amount');
            }

            if (! Schema::hasColumn('student_invoices', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('due_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_invoices', function (Blueprint $table) {
            $dropColumns = array_values(array_filter([
                Schema::hasColumn('student_invoices', 'subtotal') ? 'subtotal' : null,
                Schema::hasColumn('student_invoices', 'discount_total') ? 'discount_total' : null,
                Schema::hasColumn('student_invoices', 'total_amount') ? 'total_amount' : null,
                Schema::hasColumn('student_invoices', 'paid_amount') ? 'paid_amount' : null,
                Schema::hasColumn('student_invoices', 'paid_at') ? 'paid_at' : null,
            ]));

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
