<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('gpa_calculations');

        Schema::create('gpa_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade')->comment('ID sinh viên');
            $table->foreignId('semester_id')->constrained('semesters')->onDelete('cascade')->comment('Học kỳ tính toán');
            $table->foreignId('program_id')->nullable()->constrained('programs')->nullOnDelete()->comment('Chương trình học tại thời điểm tính');

            // GPA values
            $table->decimal('semester_gpa', 4, 3)->default(0.000)->comment('GPA của riêng học kỳ này');
            $table->decimal('cumulative_gpa', 4, 3)->default(0.000)->comment('GPA tích lũy đến hết học kỳ này');
            
            // Quality points (Grade points * Credit hours)
            $table->decimal('semester_quality_points', 8, 3)->default(0.000)->comment('Tổng điểm chất lượng của học kỳ');
            $table->decimal('cumulative_quality_points', 10, 3)->default(0.000)->comment('Tổng điểm chất lượng tích lũy');
            
            // Credit points attempted
            $table->decimal('semester_credit_points', 8, 2)->default(0.00)->comment('Tổng số tín chỉ đăng ký trong học kỳ');
            $table->decimal('cumulative_credit_points', 10, 2)->default(0.00)->comment('Tổng số tín chỉ đăng ký tích lũy');
            
            // Credit points earned (passed)
            $table->decimal('semester_credit_points_earned', 8, 2)->default(0.00)->comment('Số tín chỉ đạt được trong học kỳ');
            $table->decimal('cumulative_credit_points_earned', 10, 2)->default(0.00)->comment('Số tín chỉ đạt được tích lũy');

            // Standing
            $table->string('academic_standing')->nullable()->default('normal')->comment('Trạng thái học vụ (Normal, Warning, v.v.)');
            
            // Finalization metadata
            $table->boolean('is_finalized')->default(false)->comment('Đã được admin chốt chưa');
            $table->timestamp('finalized_at')->nullable()->comment('Thời điểm chốt điểm');
            $table->foreignId('finalized_by_id')->nullable()->constrained('lectures')->nullOnDelete()->comment('Admin thực hiện chốt điểm');
            
            $table->text('remarks')->nullable()->comment('Ghi chú bổ sung');

            $table->boolean('is_current')->default(true)->comment('Đây có phải là bản ghi GPA mới nhất của sinh viên không');

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['student_id', 'semester_id'], 'student_semester_idx');
            $table->index(['student_id', 'is_current'], 'student_current_gpa_idx');
            $table->index(['is_finalized', 'finalized_at'], 'finalized_status_idx');
            
            // Unique constraint: one record per student per semester
            $table->unique(['student_id', 'semester_id'], 'unique_student_semester_gpa');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gpa_calculations');
    }
};
