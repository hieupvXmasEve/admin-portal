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
        Schema::create('student_applications', function (Blueprint $table) {
            $table->id();
            $table->string('full_name', 100);
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('ethnicity', 100)->nullable();
            $table->tinyInteger('birth_day')->unsigned()->nullable();
            $table->tinyInteger('birth_month')->unsigned()->nullable();
            $table->year('birth_year')->nullable();
            $table->string('national_id', 20)->unique();
            $table->string('phone', 20)->nullable();
            $table->string('email', 255)->unique();
            $table->text('address')->nullable();
            $table->text('health_information')->nullable();

            $table->string('parent_phone', 20)->nullable();
            $table->string('parent_email', 255)->nullable();

            $table->string('campus_code', 20);
            $table->string('intended_program', 100)->nullable();
            $table->string('intended_specialization', 100)->nullable();
            $table->string('intake', 50)->nullable();

            $table->date('exam_date')->nullable();
            $table->string('english_test_type', 50)->nullable();
            $table->decimal('listening', 4, 2)->nullable();
            $table->decimal('reading', 4, 2)->nullable();
            $table->decimal('writing', 4, 2)->nullable();
            $table->decimal('speaking', 4, 2)->nullable();
            $table->decimal('overall', 4, 2)->nullable();

            $table->string('submitted_photo')->nullable();
            $table->string('submitted_cccd')->nullable();
            $table->string('submitted_ccta')->nullable();
            $table->string('submitted_tn_translate')->nullable();
            $table->string('submitted_hb_translate')->nullable();
            $table->string('submitted_other')->nullable();
            $table->string('submitted_insurance_card')->nullable();
            $table->string('submitted_exemption_gc')->nullable();

            $table->string('study_link_status', 50)->nullable();
            $table->string('english_qualifications', 100)->nullable();
            $table->string('sut_id', 50)->nullable();
            $table->boolean('is_international_applicant')->default(false);
            $table->text('exception_units')->nullable();
            $table->enum('status', ['pending', 'reviewed', 'approved', 'rejected'])
                  ->default('pending');
            
            $table->unsignedBigInteger('student_id')->nullable();
            $table->foreign('student_id')->references('id')->on('students')->onDelete('set null');
            $table->index('student_id');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_applications');
    }
};
