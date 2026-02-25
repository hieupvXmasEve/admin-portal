<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_action_logs', function (Blueprint $table) {
            $table->string('decision_number')->nullable()->after('signed_at');
            $table->date('decision_signed_at')->nullable()->after('decision_number');
            $table->string('decision_signer')->nullable()->after('decision_signed_at');
        });
    }

    public function down(): void
    {
        Schema::table('student_action_logs', function (Blueprint $table) {
            $table->dropColumn([
                'decision_number',
                'decision_signed_at',
                'decision_signer',
            ]);
        });
    }
};

