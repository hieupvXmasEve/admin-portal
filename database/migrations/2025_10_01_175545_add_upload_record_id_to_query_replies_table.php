<?php

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
        if (!Schema::hasColumn('query_replies', 'upload_record_id')) {
            Schema::table('query_replies', function (Blueprint $table) {
                $table->foreignId('upload_record_id')
                    ->nullable()
                    ->after('is_official_answer')
                    ->constrained('upload_records')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('query_replies', 'upload_record_id')) {
            Schema::table('query_replies', function (Blueprint $table) {
                $table->dropConstrainedForeignId('upload_record_id');
            });
        }
    }
};
