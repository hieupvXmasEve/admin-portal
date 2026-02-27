<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_decisions', function (Blueprint $table) {
            $table->id();
            $table->string('decision_name');
            $table->string('decision_number');
            $table->string('decision_signer');
            $table->date('issued_at');
            $table->date('expires_at')->nullable();
            $table->foreignId('upload_record_id')->nullable()->constrained('upload_records')->nullOnDelete();
            $table->foreignId('changed_by_user_id')->constrained('users');
            $table->timestamps();

            $table->index('decision_number');
            $table->index('issued_at');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_decisions');
    }
};
