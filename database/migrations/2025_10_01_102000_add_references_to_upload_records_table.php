<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upload_records', function (Blueprint $table) {
            // Add foreign key constraints for existing columns
            // Columns were created in create_upload_records_table migration without foreign keys
            // to avoid dependency issues during migration

            if (Schema::hasColumn('upload_records', 'student_id')) {
                $table->foreign('student_id')
                    ->references('id')
                    ->on('students')
                    ->onDelete('set null');
            }

            if (Schema::hasColumn('upload_records', 'response_id')) {
                $table->foreign('response_id')
                    ->references('id')
                    ->on('responses')
                    ->onDelete('set null');
            }

            if (Schema::hasColumn('upload_records', 'answer_id')) {
                $table->foreign('answer_id')
                    ->references('id')
                    ->on('answers')
                    ->onDelete('set null');
            }

            if (Schema::hasColumn('upload_records', 'ticket_id')) {
                $table->foreign('ticket_id')
                    ->references('id')
                    ->on('queries_tickets')
                    ->onDelete('set null');
            }

            if (Schema::hasColumn('upload_records', 'reply_id')) {
                $table->foreign('reply_id')
                    ->references('id')
                    ->on('query_replies')
                    ->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('upload_records', function (Blueprint $table) {
            if (Schema::hasColumn('upload_records', 'reply_id')) {
                $table->dropConstrainedForeignId('reply_id');
            }

            if (Schema::hasColumn('upload_records', 'ticket_id')) {
                $table->dropConstrainedForeignId('ticket_id');
            }

            if (Schema::hasColumn('upload_records', 'answer_id')) {
                $table->dropConstrainedForeignId('answer_id');
            }

            if (Schema::hasColumn('upload_records', 'response_id')) {
                $table->dropConstrainedForeignId('response_id');
            }
        });
    }
};
