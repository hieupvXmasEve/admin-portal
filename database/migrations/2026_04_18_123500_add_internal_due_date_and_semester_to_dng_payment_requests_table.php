<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dng_payment_requests', function (Blueprint $table) {
            $table->foreignId('semester_id')
                ->nullable()
                ->after('description')
                ->constrained('semesters');
            $table->date('due_date')
                ->nullable()
                ->after('semester_id');
            $table->index(['semester_id', 'due_date'], 'dng_pr_semester_due_idx');
        });
    }

    public function down(): void
    {
        Schema::table('dng_payment_requests', function (Blueprint $table) {
            $table->dropIndex('dng_pr_semester_due_idx');
            $table->dropConstrainedForeignId('semester_id');
            $table->dropColumn('due_date');
        });
    }
};
