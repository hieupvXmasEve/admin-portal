<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_event_outbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('event_name', 120);
            $table->unsignedInteger('event_version')->default(1);
            $table->dateTime('occurred_at');
            $table->string('aggregate_type', 80);
            $table->string('aggregate_id', 80);
            $table->unsignedBigInteger('campus_id')->nullable();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->dateTime('next_retry_at')->nullable();
            $table->dateTime('dispatched_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_retry_at']);
            $table->index(['event_name', 'occurred_at']);
            $table->index(['campus_id', 'occurred_at']);
            $table->index(['aggregate_type', 'aggregate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_event_outbox');
    }
};
