<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('query_replies', function (Blueprint $table) {
            $table->dropForeign(['author_user_id']);
        });

        DB::statement('ALTER TABLE query_replies MODIFY author_user_id BIGINT UNSIGNED NULL');

        Schema::table('query_replies', function (Blueprint $table) {
            $table->foreign('author_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreignId('author_student_id')
                ->nullable()
                ->after('author_user_id')
                ->constrained('students')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('query_replies', function (Blueprint $table) {
            $table->dropForeign(['author_student_id']);
            $table->dropColumn('author_student_id');

            $table->dropForeign(['author_user_id']);
        });

        DB::statement('ALTER TABLE query_replies MODIFY author_user_id BIGINT UNSIGNED NOT NULL');

        Schema::table('query_replies', function (Blueprint $table) {
            $table->foreign('author_user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }
};
