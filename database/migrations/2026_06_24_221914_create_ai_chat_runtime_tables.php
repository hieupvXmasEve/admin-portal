<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->foreignId('user_message_id')->constrained('ai_messages')->cascadeOnDelete();
            $table->foreignId('assistant_message_id')->constrained('ai_messages')->cascadeOnDelete();
            $table->foreignId('ai_agent_trace_id')->constrained('ai_agent_traces')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 50)->nullable();
            $table->string('model', 150)->nullable();
            $table->string('runtime_mode', 50)->default('deterministic');
            $table->string('stream_transport', 30)->default('sse');
            $table->string('stream_mode', 50)->default('fallback_snapshot');
            $table->string('status', 30)->default('queued');
            $table->timestamp('cancellation_requested_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('safe_error_code', 100)->nullable();
            $table->unsignedBigInteger('last_event_id')->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->timestamps();

            $table->index(['ai_conversation_id', 'status']);
            $table->index(['user_id', 'campus_id', 'status']);
            $table->index(['assistant_message_id', 'status']);
            $table->index(['stream_transport', 'stream_mode']);
            $table->index('last_event_id');
            $table->unique('idempotency_key');
        });

        Schema::create('ai_run_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_chat_run_id')->constrained('ai_chat_runs')->cascadeOnDelete();
            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->foreignId('ai_message_id')->nullable()->constrained('ai_messages')->nullOnDelete();
            $table->foreignId('ai_tool_call_id')->nullable()->constrained('ai_tool_calls')->nullOnDelete();
            $table->string('event_type', 80);
            $table->unsignedInteger('sequence');
            $table->json('redacted_payload')->nullable();
            $table->timestamps();

            $table->unique(['ai_chat_run_id', 'sequence']);
            $table->index(['ai_chat_run_id', 'id']);
            $table->index(['ai_chat_run_id', 'event_type']);
            $table->index(['ai_conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_run_events');
        Schema::dropIfExists('ai_chat_runs');
    }
};
