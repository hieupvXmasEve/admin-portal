<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_lifecycle_due_exception_reviews', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('dng_payment_request_id');
            $table->unsignedBigInteger('student_id')->nullable();
            $table->string('exception_reason', 64);
            $table->string('status', 32)->default('open');
            $table->string('resolution_action', 64)->nullable();
            $table->text('resolution_reason')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by_user_id')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'exception_reason', 'last_seen_at'], 'fl_der_status_reason_seen_idx');
            $table->index('student_id', 'fl_der_student_id_idx');
            $table->index('dng_payment_request_id', 'fl_der_dng_request_id_idx');

            $table->foreign('dng_payment_request_id', 'fl_der_dng_request_fk')
                ->references('id')
                ->on('dng_payment_requests')
                ->cascadeOnDelete();
            $table->foreign('student_id', 'fl_der_student_fk')
                ->references('id')
                ->on('students')
                ->nullOnDelete();
            $table->foreign('resolved_by_user_id', 'fl_der_resolved_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_lifecycle_due_exception_reviews');
    }
};
