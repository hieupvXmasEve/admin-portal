<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('defer_cases', function (Blueprint $table) {
            if (! Schema::hasColumn('defer_cases', 'applies_until_semester_id')) {
                $table->foreignId('applies_until_semester_id')
                    ->nullable()
                    ->constrained('semesters')
                    ->nullOnDelete()
                    ->after('semester_id');
            }

            if (! Schema::hasColumn('defer_cases', 'applies_once')) {
                $table->boolean('applies_once')->default(true)->after('fee_policy');
            }

            if (! Schema::hasColumn('defer_cases', 'applied_at')) {
                $table->dateTime('applied_at')->nullable()->after('signed_at');
            }

            if (! Schema::hasColumn('defer_cases', 'applied_semester_id')) {
                $table->foreignId('applied_semester_id')
                    ->nullable()
                    ->constrained('semesters')
                    ->nullOnDelete()
                    ->after('applied_at');
            }
        });

        Schema::table('defer_case_items', function (Blueprint $table) {
            if (! Schema::hasColumn('defer_case_items', 'applied_at')) {
                $table->dateTime('applied_at')->nullable()->after('preserve_amount');
            }

            if (! Schema::hasColumn('defer_case_items', 'applied_semester_id')) {
                $table->foreignId('applied_semester_id')
                    ->nullable()
                    ->constrained('semesters')
                    ->nullOnDelete()
                    ->after('applied_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('defer_case_items', function (Blueprint $table) {
            if (Schema::hasColumn('defer_case_items', 'applied_semester_id')) {
                $table->dropConstrainedForeignId('applied_semester_id');
            }

            if (Schema::hasColumn('defer_case_items', 'applied_at')) {
                $table->dropColumn('applied_at');
            }
        });

        Schema::table('defer_cases', function (Blueprint $table) {
            if (Schema::hasColumn('defer_cases', 'applied_semester_id')) {
                $table->dropConstrainedForeignId('applied_semester_id');
            }

            if (Schema::hasColumn('defer_cases', 'applied_at')) {
                $table->dropColumn('applied_at');
            }

            if (Schema::hasColumn('defer_cases', 'applies_once')) {
                $table->dropColumn('applies_once');
            }

            if (Schema::hasColumn('defer_cases', 'applies_until_semester_id')) {
                $table->dropConstrainedForeignId('applies_until_semester_id');
            }
        });
    }
};
