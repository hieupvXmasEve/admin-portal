<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_invoices', function (Blueprint $table) {
            // Drop columns
            $table->dropColumn(['subtotal', 'discount_total', 'total_amount', 'paid_amount', 'paid_at']);

            // Make billing_cycle_id nullable
            $table->unsignedBigInteger('billing_cycle_id')->nullable()->change();

            // Drop old unique constraint
            $table->dropUnique('unique_student_billing_cycle');

            // Intentionally no unique(student_id, semester_id): separate fee
            // streams may issue multiple invoices in the same semester.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_invoices', function (Blueprint $table) {
            // Re-add columns
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount_total', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->timestamp('paid_at')->nullable();

            // Re-make billing_cycle_id NOT nullable
            $table->unsignedBigInteger('billing_cycle_id')->nullable(false)->change();

            // Re-add old unique constraint
            $table->unique(['student_id', 'billing_cycle_id'], 'unique_student_billing_cycle');
        });
    }
};
