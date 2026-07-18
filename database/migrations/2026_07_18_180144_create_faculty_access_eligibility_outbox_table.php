<?php

declare(strict_types=1);

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
        Schema::create('faculty_access_eligibility_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('deduplication_key', 120)->unique();
            $table->unsignedBigInteger('lecturer_id');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('token_subject_type', 120);
            $table->boolean('is_eligible');
            $table->string('reason', 80);
            $table->timestamp('evaluated_at');
            $table->string('status', 20)->default('pending');
            $table->timestamp('dispatched_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'id'], 'faculty_access_outbox_pending_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faculty_access_eligibility_outbox');
    }
};
