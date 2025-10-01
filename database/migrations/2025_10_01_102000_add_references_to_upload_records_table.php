<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upload_records', function (Blueprint $table) {
            if (!Schema::hasColumn('upload_records', 'response_id')) {
                $table->foreignId('response_id')->nullable()->after('student_id')->constrained('responses')->nullOnDelete();
            }

            if (!Schema::hasColumn('upload_records', 'answer_id')) {
                $table->foreignId('answer_id')->nullable()->after('response_id')->constrained('answers')->nullOnDelete();
            }

            if (!Schema::hasColumn('upload_records', 'ticket_id')) {
                $table->foreignId('ticket_id')->nullable()->after('answer_id')->constrained('queries_tickets')->nullOnDelete();
            }

            if (!Schema::hasColumn('upload_records', 'reply_id')) {
                $table->foreignId('reply_id')->nullable()->after('ticket_id')->constrained('query_replies')->nullOnDelete();
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
