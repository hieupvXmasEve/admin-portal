<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upload_records', function (Blueprint $table) {
            if (!Schema::hasColumn('upload_records', 'student_id')) {
                $table->foreignId('student_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained()
                    ->onDelete('set null');

                $table->index(['context', 'student_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('upload_records', function (Blueprint $table) {
            if (Schema::hasColumn('upload_records', 'student_id')) {
                $table->dropIndex('upload_records_context_student_id_index');
                $table->dropConstrainedForeignId('student_id');
            }
        });
    }
};
