<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_action_logs', function (Blueprint $table) {
            $table->foreignId('decision_id')
                ->nullable()
                ->after('decision_signer')
                ->constrained('student_decisions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('student_action_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decision_id');
        });
    }
};
