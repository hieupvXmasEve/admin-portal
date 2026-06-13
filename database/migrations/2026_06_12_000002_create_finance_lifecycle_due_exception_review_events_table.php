<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_lifecycle_due_exception_review_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('finance_lifecycle_due_exception_review_id')->nullable();
            $table->unsignedBigInteger('dng_payment_request_id');
            $table->unsignedBigInteger('student_id')->nullable();
            $table->string('event_type', 64);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->string('resolution_action', 64)->nullable();
            $table->text('resolution_reason')->nullable();
            $table->unsignedBigInteger('performed_by_user_id')->nullable();
            $table->timestamp('performed_at');
            $table->string('dng_status_before', 64)->nullable();
            $table->string('dng_status_after', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['dng_payment_request_id', 'performed_at'], 'fl_dere_dng_performed_idx');
            $table->index(['finance_lifecycle_due_exception_review_id', 'performed_at'], 'fl_dere_review_performed_idx');
            $table->index(['student_id', 'performed_at'], 'fl_dere_student_performed_idx');
            $table->index(['event_type', 'performed_at'], 'fl_dere_event_performed_idx');
            $table->index(['performed_by_user_id', 'performed_at'], 'fl_dere_actor_performed_idx');

            $table->foreign('finance_lifecycle_due_exception_review_id', 'fl_dere_review_fk')
                ->references('id')
                ->on('finance_lifecycle_due_exception_reviews')
                ->nullOnDelete();
            $table->foreign('dng_payment_request_id', 'fl_dere_dng_request_fk')
                ->references('id')
                ->on('dng_payment_requests')
                ->cascadeOnDelete();
            $table->foreign('student_id', 'fl_dere_student_fk')
                ->references('id')
                ->on('students')
                ->nullOnDelete();
            $table->foreign('performed_by_user_id', 'fl_dere_actor_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_lifecycle_due_exception_review_events');
    }
};
