<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dng_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('dng_payment_id', 100)->nullable();
            $table->string('event_type', 50);
            $table->string('payload_hash', 64);
            $table->json('headers')->nullable();
            $table->json('payload');
            $table->boolean('is_valid_checksum')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->string('processing_status', 30)->default('pending');
            $table->foreignId('dng_payment_request_id')
                ->nullable()
                ->constrained('dng_payment_requests');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique('payload_hash');
            $table->index('dng_payment_id');
            $table->index('processing_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dng_webhook_events');
    }
};
