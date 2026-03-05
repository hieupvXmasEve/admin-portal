<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id');
            $table->string('event_name', 120);
            $table->string('type_key', 80);
            $table->unsignedBigInteger('campus_id')->nullable();
            $table->unsignedBigInteger('recipient_user_id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->json('recipient_meta')->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->string('status', 20)->default('active');
            $table->dateTime('read_at')->nullable();
            $table->dateTime('archived_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'type_key', 'recipient_user_id'], 'notification_messages_event_type_recipient_unique');
            $table->index(['recipient_user_id', 'read_at', 'created_at']);
            $table->index(['campus_id', 'type_key', 'created_at']);
            $table->index(['status', 'created_at']);

            $table->foreign('recipient_user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_messages');
    }
};
