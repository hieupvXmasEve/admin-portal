<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('student_invoices')->cascadeOnDelete();
            $table->foreignId('charge_id')->constrained('finance_charges')->cascadeOnDelete();
            $table->decimal('amount_snapshot', 15, 2); // Amount at time of invoicing
            $table->string('description_snapshot', 255); // Description at invoice time
            $table->timestamps();

            // Indexes
            $table->index('invoice_id');
            $table->index('charge_id');

            // Unique constraint to prevent duplicate charge on same invoice
            $table->unique(['invoice_id', 'charge_id'], 'unique_invoice_charge');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
    }
};
