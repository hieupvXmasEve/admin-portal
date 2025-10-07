<?php

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
        Schema::create('student_scholarship_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->string('scholarship_code', 50);
            $table->date('awarded_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('scholarship_code')
                ->references('code')
                ->on('scholarship_definitions')
                ->onUpdate('cascade');

            $table->unique('student_id', 'unique_student_scholarship');
            $table->index('student_id');
            $table->index('scholarship_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_scholarship_awards');
    }
};
