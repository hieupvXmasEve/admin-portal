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
        Schema::table('gpa_calculations', function (Blueprint $table) {
            $table->decimal('semester_gpa', 6, 3)->default(0.000)->comment('GPA của riêng học kỳ này')->change();
            $table->decimal('cumulative_gpa', 6, 3)->default(0.000)->comment('GPA tích lũy đến hết học kỳ này')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gpa_calculations', function (Blueprint $table) {
            $table->decimal('semester_gpa', 4, 3)->default(0.000)->comment('GPA của riêng học kỳ này')->change();
            $table->decimal('cumulative_gpa', 4, 3)->default(0.000)->comment('GPA tích lũy đến hết học kỳ này')->change();
        });
    }
};
