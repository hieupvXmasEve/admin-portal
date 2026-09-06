<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_payment_vouchers', function (Blueprint $table): void {
            $table->id();
            $table->string('voucher_number', 64)->unique();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->unsignedBigInteger('campus_id');
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->json('allocations_snapshot');
            $table->json('skipped_snapshot');
            $table->decimal('unapplied_amount', 15, 2)->default(0);
            $table->timestamps();

            $table->index('payment_id');
            $table->index('campus_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_payment_vouchers');
    }
};
