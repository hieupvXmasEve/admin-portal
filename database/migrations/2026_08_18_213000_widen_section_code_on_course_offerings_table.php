<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_offerings', function (Blueprint $table): void {
            $table->string('section_code', 30)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('course_offerings', function (Blueprint $table): void {
            $table->string('section_code', 10)->nullable()->change();
        });
    }
};
