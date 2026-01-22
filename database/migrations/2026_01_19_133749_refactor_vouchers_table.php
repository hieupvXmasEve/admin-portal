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
        // 1. Update voucher_definitions
        Schema::table('voucher_definitions', function (Blueprint $table) {
            $table->decimal('max_discount_amount', 15, 2)->nullable()->after('discount_value');
            $table->boolean('is_stackable')->default(false)->after('max_discount_amount');
            $table->integer('max_uses_per_student')->nullable()->after('is_stackable');
        });

        // 2. Create voucher_applications

        Schema::create('voucher_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_definition_id')->constrained('voucher_definitions')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('semester_id')->constrained('semesters')->onDelete('cascade');
            $table->unsignedBigInteger('invoice_id')->nullable();
            
            $table->enum('status', ['applied', 'cancelled', 'expired'])->default('applied');
            $table->timestamp('applied_at')->useCurrent();
            $table->foreignId('applied_by_user_id')->nullable()->constrained('users');
            
            $table->decimal('base_amount', 15, 2)->nullable();
            $table->decimal('discount_amount', 15, 2)->nullable();
            $table->unsignedBigInteger('finance_charge_id')->nullable();
            
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'semester_id', 'voucher_definition_id'], 'student_semester_voucher_unique');
            
            $table->index('voucher_definition_id');
            $table->index('student_id');
            $table->index('semester_id');
            $table->index('invoice_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_applications');
        
        Schema::table('voucher_definitions', function (Blueprint $table) {
            $table->dropColumn(['max_discount_amount', 'is_stackable', 'max_uses_per_student']);
        });

        // Recreating voucher_redemptions is complex here, but since this is dev, 
        // usually we just drop and recreate anyway. 
        // For safety in a real migration, we should define the original structure in down().
    }
};
