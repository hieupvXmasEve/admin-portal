<?php

declare(strict_types=1);

namespace Database\Seeders\Timeline;

use App\Models\Student;
use App\Models\User;
use App\Models\CampusUserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class StudentAdmissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Processes student admissions and creates user accounts
     */
    public function run(): void
    {
        $this->command->info('🎓 Processing student admissions...');

        // Clean existing user accounts that might have been created for students
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('campus_user_roles')->delete();
        DB::table('users')->where('email', 'like', '%@student.swinburne.edu.au')->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $applicants = Student::where('status', 'inactive')->get();

        if ($applicants->isEmpty()) {
            throw new \Exception('No inactive students found. Please run CreateInitialStudentsSeeder first.');
        }

        $admittedCount = 0;
        $rejectedCount = 0;

        foreach ($applicants as $student) {
            // 90% admission rate
            // $isAdmitted = rand(1, 100) <= 90;

            // if ($isAdmitted) {
                $this->admitStudent($student);
                $admittedCount++;
            // } else {
            //     $this->rejectStudent($student);
            //     $rejectedCount++;
            // }
        }

        $this->command->info("✅ Admission processing complete!");
        $this->command->info("  📈 Admitted: {$admittedCount} students");
        $this->command->info("  📉 Rejected: {$rejectedCount} students");
    }

    private function admitStudent(Student $student): void
    {
        // Update student status and dates
        $admissionDate = Carbon::create(2024, 2, 15)->addDays(rand(0, 30)); // Feb-Mar 2024
        $expectedGraduationDate = $admissionDate->copy()->addYears(4); // 4-year program

        $student->update([
            'status' => 'active',
            'admission_date' => $admissionDate,
            'expected_graduation_date' => $expectedGraduationDate,
            'admission_notes' => 'Admitted based on academic merit and entrance exam score.',
        ]);

        // Create user account for the student
        $this->createUserAccount($student);

        // Assign student role
        $this->assignStudentRole($student);
    }

    private function rejectStudent(Student $student): void
    {
        $student->update([
            'status' => 'suspended',
            'admission_notes' => 'Application rejected due to insufficient entrance exam score or capacity limits.',
        ]);
    }

    private function createUserAccount(Student $student): User
    {
        return User::create([
            'name' => $student->full_name,
            'email' => $student->email,
            'phone' => $student->phone,
            'address' => $student->address,
            'password' => Hash::make('password123'), // Default password
            'email_verified_at' => now(),
        ]);
    }

    private function assignStudentRole(Student $student): void
    {
        // Find the user account
        $user = User::where('email', $student->email)->first();

        if (!$user) {
            $this->command->warn("User account not found for student {$student->student_id}");
            return;
        }

        // Find student role ID
        $studentRole = \App\Models\Role::where('name', 'Sinh Viên')->first();

        if (!$studentRole) {
            $this->command->warn("Student role not found");
            return;
        }

        // Assign student role for the campus
        CampusUserRole::create([
            'user_id' => $user->id,
            'campus_id' => $student->campus_id,
            'role_id' => $studentRole->id,
        ]);
    }
}
