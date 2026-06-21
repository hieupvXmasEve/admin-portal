<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->string('default_model', 100);
            $table->text('encrypted_api_key')->nullable();
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('daily_limit_cents')->nullable();
            $table->unsignedInteger('monthly_limit_cents')->nullable();
            $table->timestamp('tested_at')->nullable();
            $table->string('last_test_status', 30)->nullable();
            $table->string('last_test_error_code', 80)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('user_id');
            $table->index(['provider', 'default_model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_settings');
    }
};
