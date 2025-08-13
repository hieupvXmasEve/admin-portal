<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('recipient')->comment('Email recipient address');
            $table->string('sender')->nullable()->comment('Email sender address');
            $table->string('subject')->comment('Email subject');
            $table->foreignId('template_id')->nullable()->constrained('email_templates')->onDelete('set null');
            $table->enum('status', [
                'pending',
                'queued',
                'sending',
                'sent',
                'delivered',
                'failed',
                'bounced',
                'rejected'
            ])->default('pending')->comment('Email delivery status');
            $table->timestamp('queued_at')->nullable()->comment('When email was queued');
            $table->timestamp('sent_at')->nullable()->comment('When email was sent');
            $table->timestamp('delivered_at')->nullable()->comment('When email was delivered');
            $table->timestamp('failed_at')->nullable()->comment('When email failed');
            $table->text('error_message')->nullable()->comment('Error message if failed');
            $table->integer('retry_count')->default(0)->comment('Number of retry attempts');
            $table->json('metadata')->nullable()->comment('Additional email metadata');
            $table->string('message_id')->nullable()->comment('Email message ID from provider');
            $table->string('batch_id')->nullable()->comment('Batch ID for bulk emails');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['recipient', 'status']);
            $table->index(['status', 'created_at']);
            $table->index(['template_id', 'status']);
            $table->index(['batch_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('sent_at');
            $table->index('failed_at');
            $table->index('message_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
