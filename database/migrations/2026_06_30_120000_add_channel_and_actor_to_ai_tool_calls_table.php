<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Channel-aware AI tool-call audit (ADR-0006).
 *
 * Makes the conversation/trace foreign keys nullable so a standalone MCP call
 * can be persisted without a chat run, and adds the MCP identity columns:
 * `channel`, `actor_user_id`, `mcp_client_id`. Existing chat rows keep
 * `channel = 'chat'` via the column default; no backfill is performed.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop the cascade foreign keys before relaxing the columns to nullable.
        Schema::table('ai_tool_calls', function (Blueprint $table): void {
            $table->dropForeign(['ai_conversation_id']);
            $table->dropForeign(['ai_agent_trace_id']);
        });

        Schema::table('ai_tool_calls', function (Blueprint $table): void {
            $table->unsignedBigInteger('ai_conversation_id')->nullable()->change();
            $table->unsignedBigInteger('ai_agent_trace_id')->nullable()->change();

            $table->string('channel', 30)->default('chat')->after('ai_agent_trace_id');
            $table->foreignId('actor_user_id')->nullable()->after('channel')
                ->constrained('users')->nullOnDelete();
            $table->string('mcp_client_id', 100)->nullable()->after('actor_user_id');

            $table->index(['channel', 'tool_name']);
        });

        // Re-add the conversation/trace foreign keys (now nullable-tolerant).
        Schema::table('ai_tool_calls', function (Blueprint $table): void {
            $table->foreign('ai_conversation_id')->references('id')->on('ai_conversations')->cascadeOnDelete();
            $table->foreign('ai_agent_trace_id')->references('id')->on('ai_agent_traces')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        // Standalone MCP-channel rows carry null conversation/trace links; they must be
        // removed before the columns are re-tightened to NOT NULL, or MariaDB rejects the
        // change. This is an intentional, controlled data loss on rollback of the spike.
        DB::table('ai_tool_calls')
            ->whereNull('ai_conversation_id')
            ->orWhereNull('ai_agent_trace_id')
            ->delete();

        Schema::table('ai_tool_calls', function (Blueprint $table): void {
            $table->dropForeign(['ai_conversation_id']);
            $table->dropForeign(['ai_agent_trace_id']);
            $table->dropForeign(['actor_user_id']);
            $table->dropIndex(['channel', 'tool_name']);
            $table->dropColumn(['channel', 'actor_user_id', 'mcp_client_id']);
        });

        Schema::table('ai_tool_calls', function (Blueprint $table): void {
            $table->unsignedBigInteger('ai_conversation_id')->nullable(false)->change();
            $table->unsignedBigInteger('ai_agent_trace_id')->nullable(false)->change();

            $table->foreign('ai_conversation_id')->references('id')->on('ai_conversations')->cascadeOnDelete();
            $table->foreign('ai_agent_trace_id')->references('id')->on('ai_agent_traces')->cascadeOnDelete();
        });
    }
};
