<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->nullOnDelete();
            $table->string('origin', 50);
            $table->string('title')->nullable();
            $table->string('status', 30)->default('open');
            $table->string('request_id', 100)->nullable();
            $table->json('actor_role_snapshot')->nullable();
            $table->json('campus_scope_snapshot')->nullable();
            $table->string('sdk_conversation_id', 100)->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'campus_id', 'status']);
            $table->index(['origin', 'created_at']);
            $table->index('request_id');
        });

        Schema::create('ai_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->string('role', 30);
            $table->text('redacted_content');
            $table->char('content_hash', 64)->nullable();
            $table->string('content_classification', 80)->nullable();
            $table->string('final_answer_id', 100)->nullable();
            $table->json('hidden_sections')->nullable();
            $table->timestamps();

            $table->index(['ai_conversation_id', 'role']);
            $table->index('final_answer_id');
        });

        Schema::create('ai_agent_traces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->foreignId('ai_message_id')->nullable()->constrained('ai_messages')->nullOnDelete();
            $table->string('provider', 50)->nullable();
            $table->string('model', 150)->nullable();
            $table->string('prompt_version', 100)->nullable();
            $table->string('catalog_version', 100)->nullable();
            $table->string('tool_schema_version', 100)->nullable();
            $table->string('status', 30)->default('running');
            $table->unsignedSmallInteger('step_count')->default(0);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('safe_error_code', 100)->nullable();
            $table->string('final_answer_id', 100)->nullable();
            $table->timestamps();

            $table->index(['ai_conversation_id', 'status']);
            $table->index(['provider', 'model']);
            $table->index('safe_error_code');
        });

        Schema::create('ai_tool_calls', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->foreignId('ai_agent_trace_id')->constrained('ai_agent_traces')->cascadeOnDelete();
            $table->string('tool_name', 120);
            $table->string('tool_schema_version', 100)->nullable();
            $table->json('redacted_arguments')->nullable();
            $table->string('permission_result', 80)->nullable();
            $table->json('campus_scope_snapshot')->nullable();
            $table->json('hidden_sections')->nullable();
            $table->unsignedInteger('record_count')->default(0);
            $table->json('source_references')->nullable();
            $table->json('redacted_result_summary')->nullable();
            $table->string('status', 30);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('safe_error_code', 100)->nullable();
            $table->timestamps();

            $table->index(['ai_conversation_id', 'tool_name']);
            $table->index(['ai_agent_trace_id', 'status']);
            $table->index('permission_result');
            $table->index('safe_error_code');
        });

        Schema::create('ai_provider_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->foreignId('ai_agent_trace_id')->nullable()->constrained('ai_agent_traces')->nullOnDelete();
            $table->foreignId('ai_message_id')->nullable()->constrained('ai_messages')->nullOnDelete();
            $table->string('provider', 50);
            $table->string('model', 150);
            $table->string('status', 30);
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->unsignedInteger('estimated_cost_minor')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('safe_error_code', 100)->nullable();
            $table->string('provider_request_id', 150)->nullable();
            $table->timestamps();

            $table->index(['ai_conversation_id', 'status']);
            $table->index(['provider', 'model']);
            $table->index('safe_error_code');
            $table->index('provider_request_id');
        });

        Schema::create('ai_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->foreignId('ai_message_id')->nullable()->constrained('ai_messages')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('rating', 30);
            $table->text('redacted_reason')->nullable();
            $table->timestamps();

            $table->index(['ai_conversation_id', 'rating']);
            $table->index('user_id');
        });

        Schema::create('ai_evaluation_cases', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 120);
            $table->string('dataset_version', 100);
            $table->text('question');
            $table->json('actor_fixture')->nullable();
            $table->json('permission_context')->nullable();
            $table->json('expected_tool_calls')->nullable();
            $table->json('expected_source_references')->nullable();
            $table->json('expected_answer_properties')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamps();

            $table->unique(['dataset_version', 'key']);
            $table->index(['dataset_version', 'status']);
        });

        Schema::create('ai_evaluation_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('dataset_version', 100);
            $table->string('code_version', 120)->nullable();
            $table->string('prompt_version', 100)->nullable();
            $table->string('catalog_version', 100)->nullable();
            $table->string('tool_schema_version', 100)->nullable();
            $table->string('provider_fake', 100)->nullable();
            $table->string('status', 30)->default('running');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['dataset_version', 'status']);
            $table->index('started_at');
        });

        Schema::create('ai_evaluation_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ai_evaluation_run_id')->constrained('ai_evaluation_runs')->cascadeOnDelete();
            $table->foreignId('ai_evaluation_case_id')->constrained('ai_evaluation_cases')->cascadeOnDelete();
            $table->string('status', 30);
            $table->json('assertions')->nullable();
            $table->text('redacted_diff_summary')->nullable();
            $table->json('tool_call_evidence')->nullable();
            $table->string('source_parity_status', 50)->nullable();
            $table->string('safe_error_code', 100)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['ai_evaluation_run_id', 'status']);
            $table->index(['ai_evaluation_case_id', 'status']);
            $table->index('safe_error_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_evaluation_results');
        Schema::dropIfExists('ai_evaluation_runs');
        Schema::dropIfExists('ai_evaluation_cases');
        Schema::dropIfExists('ai_feedback');
        Schema::dropIfExists('ai_provider_usages');
        Schema::dropIfExists('ai_tool_calls');
        Schema::dropIfExists('ai_agent_traces');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
