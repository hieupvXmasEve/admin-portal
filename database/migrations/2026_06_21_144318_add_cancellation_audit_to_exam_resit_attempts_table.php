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
        Schema::table('exam_resit_attempts', function (Blueprint $table) {
            $table->foreignId('cancelled_by_user_id')
                ->nullable()
                ->after('cancelled_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('cancellation_fee_disposition', 40)
                ->nullable()
                ->after('cancellation_reason');
            $table->timestamp('cancellation_notice_sent_at')
                ->nullable()
                ->after('cancellation_fee_disposition');
            $table->foreignId('cancellation_notice_email_log_id')
                ->nullable()
                ->after('cancellation_notice_sent_at')
                ->constrained('email_logs')
                ->nullOnDelete();
            $table->text('cancellation_notice_error')
                ->nullable()
                ->after('cancellation_notice_email_log_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exam_resit_attempts', function (Blueprint $table) {
            $table->dropForeign(['cancelled_by_user_id']);
            $table->dropForeign(['cancellation_notice_email_log_id']);
            $table->dropColumn([
                'cancelled_by_user_id',
                'cancellation_fee_disposition',
                'cancellation_notice_sent_at',
                'cancellation_notice_email_log_id',
                'cancellation_notice_error',
            ]);
        });
    }
};
