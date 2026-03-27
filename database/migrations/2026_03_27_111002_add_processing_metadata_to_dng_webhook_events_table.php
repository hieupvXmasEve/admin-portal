<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dng_webhook_events', function (Blueprint $table) {
            $table->timestamp('received_at')->nullable()->after('error_message');
            $table->unsignedInteger('attempt_count')->default(0)->after('received_at');
            $table->timestamp('last_attempt_at')->nullable()->after('attempt_count');
            $table->timestamp('next_retry_at')->nullable()->after('last_attempt_at');
            $table->string('error_category', 50)->nullable()->after('next_retry_at');

            $table->dropUnique('dng_webhook_events_payload_hash_unique');
            $table->index('payload_hash');
            $table->index(['processing_status', 'next_retry_at']);
        });
    }

    public function down(): void
    {
        Schema::table('dng_webhook_events', function (Blueprint $table) {
            $table->dropIndex(['processing_status', 'next_retry_at']);
            $table->dropIndex(['payload_hash']);
            $table->dropColumn([
                'received_at',
                'attempt_count',
                'last_attempt_at',
                'next_retry_at',
                'error_category',
            ]);

            $table->unique('payload_hash');
        });
    }
};
