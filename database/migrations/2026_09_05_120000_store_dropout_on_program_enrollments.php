<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('program_enrollments')
            ->where('enrollment_status', 'withdrawn')
            ->update(['enrollment_status' => 'dropout']);
    }

    public function down(): void
    {
        DB::table('program_enrollments')
            ->where('enrollment_status', 'dropout')
            ->update(['enrollment_status' => 'withdrawn']);
    }
};
