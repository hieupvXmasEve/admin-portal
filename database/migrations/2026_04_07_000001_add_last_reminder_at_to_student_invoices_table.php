<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_invoices', function (Blueprint $table): void {
            $table->timestamp('last_reminder_at')->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('student_invoices', function (Blueprint $table): void {
            $table->dropColumn('last_reminder_at');
        });
    }
};
