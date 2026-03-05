<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('notification_messages')->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->string('provider_message_id')->nullable();
            $table->unsignedBigInteger('email_log_id')->nullable();
            $table->text('last_error')->nullable();
            $table->dateTime('queued_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('failed_at')->nullable();
            $table->dateTime('next_retry_at')->nullable();
            $table->timestamps();

            $table->unique(['message_id', 'channel']);
            $table->index(['status', 'next_retry_at']);
            $table->index(['channel', 'status', 'created_at']);

            $table->foreign('email_log_id')->references('id')->on('email_logs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
