<?php

use App\Models\Student;
use App\Models\User;
use App\Shared\Support\Enums\UserType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create parents table
        Schema::create('parents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->string('full_name');
            $table->string('phone')->nullable();
            $table->string('email_snapshot')->nullable();
            $table->string('status')->default('active')->comment('active, inactive');
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Create parent_student pivot table
        Schema::create('parent_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('parents')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->string('relationship')->nullable()->comment('father, mother, guardian, other');
            $table->boolean('is_primary')->default(false);
            $table->string('access_level')->default('read_only');
            $table->timestamps();

            $table->unique(['parent_id', 'student_id']);
        });

        // 3. Migrate Data
        $this->migrateData();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parent_student');
        Schema::dropIfExists('parents');
    }

    /**
     * Handle the data migration logic
     */
    protected function migrateData(): void
    {
        // 3.1. Update users with type = 'staff' and email ending in @gmail.com to type = 'parent'
        User::where('type', UserType::STAFF->value)
            ->where('email', 'like', '%@gmail.com')
            ->update(['type' => UserType::PARENT->value]);

        // 3.2. Create Parent profiles for all users with type = 'parent'
        $parentUsers = User::where('type', UserType::PARENT->value)->get();

        foreach ($parentUsers as $user) {
            DB::table('parents')->updateOrInsert(
                ['user_id' => $user->id],
                [
                    'full_name' => $user->name,
                    'phone' => null, // We might not have this on the user table
                    'email_snapshot' => $user->email,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // 3.3. Migrate relationships from students.parent_user_id to parent_student pivot
        $studentsWithParents = Student::whereNotNull('parent_user_id')->get();

        foreach ($studentsWithParents as $student) {
            $parentId = DB::table('parents')
                ->where('user_id', $student->parent_user_id)
                ->value('id');

            if ($parentId) {
                DB::table('parent_student')->updateOrInsert(
                    [
                        'parent_id' => $parentId,
                        'student_id' => $student->id,
                    ],
                    [
                        'relationship' => 'guardian', // Default for legacy data
                        'is_primary' => true,
                        'access_level' => 'read_only',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
};
