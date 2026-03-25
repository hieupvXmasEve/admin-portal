<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_applications')) {
            return;
        }

        Schema::create('payment_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('invoice_line_id')->constrained('invoice_lines')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->enum('entry_type', ['application', 'reversal']);
            $table->unsignedBigInteger('source_ref_id')->nullable();
            $table->string('source_ref_type', 255)->nullable();
            $table->timestamp('applied_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('payment_id');
            $table->index('invoice_line_id');
            $table->index(['payment_id', 'invoice_line_id']);
            $table->index(['invoice_line_id', 'entry_type']);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_applications')) {
            Schema::drop('payment_applications');
        }
    }
};
