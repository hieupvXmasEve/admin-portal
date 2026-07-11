<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_surplus_dispositions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->restrictOnDelete();
            $table->uuid('idempotency_key')->unique();
            $table->enum('type', ['reallocate', 'refund', 'retain_forfeit']);
            $table->decimal('amount', 15, 2);
            $table->foreignId('payment_application_id')->nullable()->constrained('payment_applications')->restrictOnDelete();
            $table->string('external_reference')->nullable();
            $table->string('policy_code')->nullable();
            $table->text('reason')->nullable();
            $table->json('evidence');
            $table->string('audit_signature', 64);
            $table->foreignId('approved_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('disposed_at');
            $table->timestamps();

            $table->index(['payment_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_surplus_dispositions');
    }
};
