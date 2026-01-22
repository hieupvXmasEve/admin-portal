<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->enum('method', [
                'cash',
                'bank_transfer',
                'gateway',
                'wallet',
                'import',
                'other',
            ]);
            $table->string('source', 100)->nullable(); // Payment source identifier
            $table->string('external_ref', 255)->nullable(); // External transaction ID
            $table->timestamp('paid_at');
            $table->enum('status', ['pending', 'completed', 'refunded', 'cancelled'])->default('completed');
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('raw_payload')->nullable(); // Webhook/import raw data
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('student_id');
            $table->index('status');
            $table->index('paid_at');
            $table->index('external_ref');
            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
