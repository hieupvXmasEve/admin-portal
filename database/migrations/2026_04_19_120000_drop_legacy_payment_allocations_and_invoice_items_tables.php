<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('invoice_items');
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_allocations')) {
            Schema::create('payment_allocations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('payment_id')->constrained('payments')->onDelete('cascade');
                $table->foreignId('charge_id')->constrained('finance_charges')->onDelete('cascade');
                $table->decimal('allocated_amount', 15, 2);
                $table->timestamp('allocated_at')->useCurrent();
                $table->foreignId('allocated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index('payment_id');
                $table->index('charge_id');
                $table->index('allocated_at');
            });
        }

        if (! Schema::hasTable('invoice_items')) {
            Schema::create('invoice_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('invoice_id')->constrained('student_invoices')->onDelete('cascade');
                $table->enum('item_type', ['tuition', 'egc', 'retake', 'miscellaneous']);
                $table->string('description');
                $table->integer('quantity')->default(1);
                $table->decimal('unit_price', 15, 2);
                $table->decimal('total_price', 15, 2);
                $table->decimal('paid_amount', 15, 2)->default(0);
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('reference_type')->nullable();
                $table->timestamps();

                $table->index(['reference_id', 'reference_type']);
                $table->index('item_type');
            });
        }
    }
};
