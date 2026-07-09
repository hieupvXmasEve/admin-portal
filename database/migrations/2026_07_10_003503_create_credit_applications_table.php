<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finance_credit_entitlement_id')
                ->constrained('finance_credit_entitlements')
                ->cascadeOnDelete();
            $table->foreignId('invoice_line_id')
                ->constrained('invoice_lines')
                ->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->enum('entry_type', ['application', 'reversal']);
            $table->unsignedBigInteger('source_ref_id')->nullable();
            $table->string('source_ref_type', 255)->nullable();
            $table->timestamp('applied_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('finance_credit_entitlement_id', 'credit_applications_entitlement_id_idx');
            $table->index('invoice_line_id', 'credit_applications_invoice_line_id_idx');
            $table->index(
                ['finance_credit_entitlement_id', 'invoice_line_id'],
                'credit_applications_entitlement_line_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_applications');
    }
};
