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
            $table->date('due_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('student_invoices', function (Blueprint $table): void {
            $table->date('due_date')->nullable(false)->change();
        });
    }
};
