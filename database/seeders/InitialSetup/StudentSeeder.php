<?php

declare(strict_types=1);

namespace Database\Seeders\InitialSetup;

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Student;
use App\Models\StudentWallet;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StudentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates sample students with proper academic relationships
     */
    public function run(): void
    {
        $this->command->info('👤 Creating students...');

        // Clean existing data
        $this->cleanExistingData();

        // Create students
        $this->createStudents();

        $this->command->info('✅ Students created successfully!');
    }

    private function cleanExistingData(): void
    {
        StudentWallet::query()->delete();
        Student::query()->delete();
        $this->command->info('🧹 Cleaned existing student data');
    }

    private function createStudents(): void
    {
        $campuses = Campus::all();
        $programs = Program::all();
        $curriculumVersions = CurriculumVersion::all();

        if ($campuses->isEmpty() || $programs->isEmpty() || $curriculumVersions->isEmpty()) {
            $this->command->warn('⚠️  No campuses, programs, or curriculum versions found. Please run previous seeders first.');
            return;
        }

        $students = [
            // Year 1 Students
            [
                'full_name' => 'Nguyễn Văn An',
                'email' => 'hieupv2412@gmail.com',
                'phone' => '0901234567',
                'date_of_birth' => '2005-03-15',
                'gender' => 'male',
                'nationality' => 'Vietnamese',
                'national_id' => '123456789012',
                'address' => '123 Đường Lê Lợi, Quận 1, TP.HCM',
                'campus_code' => 'HCM',
                'program_code' => 'IT',
                'admission_date' => '2024-09-01',
                'expected_graduation_date' => '2027-06-30',
                'high_school_name' => 'THPT Nguyễn Thị Minh Khai',
                'high_school_graduation_year' => 2024,
                'entrance_exam_score' => 85.5,
                'status' => 'active',
            ],
            [
                'full_name' => 'Trần Thị Bình',
                'email' => 'binh.tran@student.swinburne.edu.vn',
                'phone' => '0901234568',
                'date_of_birth' => '2005-07-22',
                'gender' => 'female',
                'nationality' => 'Vietnamese',
                'national_id' => '123456789013',
                'address' => '456 Đường Nguyễn Huệ, Quận 1, TP.HCM',
                'campus_code' => 'HCM',
                'program_code' => 'IT',
                'admission_date' => '2024-09-01',
                'expected_graduation_date' => '2027-06-30',
                'high_school_name' => 'THPT Lê Hồng Phong',
                'high_school_graduation_year' => 2024,
                'entrance_exam_score' => 88.0,
                'status' => 'active',
            ],
            [
                'full_name' => 'Lê Minh Cường',
                'email' => 'cuong.le@student.swinburne.edu.vn',
                'phone' => '0901234569',
                'date_of_birth' => '2005-01-10',
                'gender' => 'male',
                'nationality' => 'Vietnamese',
                'national_id' => '123456789014',
                'address' => '789 Đường Trần Hưng Đạo, Quận 5, TP.HCM',
                'campus_code' => 'HCM',
                'program_code' => 'IT',
                'admission_date' => '2024-09-01',
                'expected_graduation_date' => '2027-06-30',
                'high_school_name' => 'THPT Marie Curie',
                'high_school_graduation_year' => 2024,
                'entrance_exam_score' => 82.5,
                'status' => 'active',
            ],
            [
                'full_name' => 'Phạm Thị Dung',
                'email' => 'dung.pham@student.swinburne.edu.vn',
                'phone' => '0901234570',
                'date_of_birth' => '2005-05-18',
                'gender' => 'female',
                'nationality' => 'Vietnamese',
                'national_id' => '123456789015',
                'address' => '321 Đường Cách Mạng Tháng 8, Quận 10, TP.HCM',
                'campus_code' => 'HCM',
                'program_code' => 'IT',
                'admission_date' => '2024-09-01',
                'expected_graduation_date' => '2027-06-30',
                'high_school_name' => 'THPT Nguyễn Thị Diệu',
                'high_school_graduation_year' => 2024,
                'entrance_exam_score' => 90.0,
                'status' => 'active',
            ],
            [
                'full_name' => 'Hoàng Văn Em',
                'email' => 'em.hoang@student.swinburne.edu.vn',
                'phone' => '0901234571',
                'date_of_birth' => '2005-11-25',
                'gender' => 'male',
                'nationality' => 'Vietnamese',
                'national_id' => '123456789016',
                'address' => '654 Đường Võ Văn Tần, Quận 3, TP.HCM',
                'campus_code' => 'HCM',
                'program_code' => 'IT',
                'admission_date' => '2024-09-01',
                'expected_graduation_date' => '2027-06-30',
                'high_school_name' => 'THPT Trần Đại Nghĩa',
                'high_school_graduation_year' => 2024,
                'entrance_exam_score' => 87.5,
                'status' => 'active',
            ],

            // Hanoi Campus Students
            [
                'full_name' => 'Vũ Thị Phương',
                'email' => 'phuong.vu@student.swinburne.edu.vn',
                'phone' => '0901234572',
                'date_of_birth' => '2005-04-12',
                'gender' => 'female',
                'nationality' => 'Vietnamese',
                'national_id' => '123456789017',
                'address' => '987 Đường Giải Phóng, Quận Hai Bà Trưng, Hà Nội',
                'campus_code' => 'HN',
                'program_code' => 'IT',
                'admission_date' => '2024-09-01',
                'expected_graduation_date' => '2027-06-30',
                'high_school_name' => 'THPT Chu Văn An',
                'high_school_graduation_year' => 2024,
                'entrance_exam_score' => 89.0,
                'status' => 'active',
            ],
            [
                'full_name' => 'Đặng Minh Giang',
                'email' => 'giang.dang@student.swinburne.edu.vn',
                'phone' => '0901234573',
                'date_of_birth' => '2005-08-30',
                'gender' => 'male',
                'nationality' => 'Vietnamese',
                'national_id' => '123456789018',
                'address' => '147 Đường Láng, Quận Đống Đa, Hà Nội',
                'campus_code' => 'HN',
                'program_code' => 'IT',
                'admission_date' => '2024-09-01',
                'expected_graduation_date' => '2027-06-30',
                'high_school_name' => 'THPT Kim Liên',
                'high_school_graduation_year' => 2024,
                'entrance_exam_score' => 86.5,
                'status' => 'active',
            ],
            [
                'full_name' => 'Bùi Thị Hoa',
                'email' => 'hoa.bui@student.swinburne.edu.vn',
                'phone' => '0901234574',
                'date_of_birth' => '2005-12-05',
                'gender' => 'female',
                'nationality' => 'Vietnamese',
                'national_id' => '123456789019',
                'address' => '258 Đường Cầu Giấy, Quận Cầu Giấy, Hà Nội',
                'campus_code' => 'HN',
                'program_code' => 'IT',
                'admission_date' => '2024-09-01',
                'expected_graduation_date' => '2027-06-30',
                'high_school_name' => 'THPT Amsterdam',
                'high_school_graduation_year' => 2024,
                'entrance_exam_score' => 91.5,
                'status' => 'active',
            ],
            [
                'full_name' => 'Ngô Văn Ích',
                'email' => 'ich.ngo@student.swinburne.edu.vn',
                'phone' => '0901234575',
                'date_of_birth' => '2005-02-14',
                'gender' => 'male',
                'nationality' => 'Vietnamese',
                'national_id' => '123456789020',
                'address' => '369 Đường Nguyễn Trãi, Quận Thanh Xuân, Hà Nội',
                'campus_code' => 'HN',
                'program_code' => 'IT',
                'admission_date' => '2024-09-01',
                'expected_graduation_date' => '2027-06-30',
                'high_school_name' => 'THPT Nguyễn Gia Thiều',
                'high_school_graduation_year' => 2024,
                'entrance_exam_score' => 84.0,
                'status' => 'active',
            ],
            [
                'full_name' => 'Lý Thị Kim',
                'email' => 'kim.ly@student.swinburne.edu.vn',
                'phone' => '0901234576',
                'date_of_birth' => '2005-06-28',
                'gender' => 'female',
                'nationality' => 'Vietnamese',
                'national_id' => '123456789021',
                'address' => '741 Đường Lê Duẩn, Quận Ba Đình, Hà Nội',
                'campus_code' => 'HN',
                'program_code' => 'IT',
                'admission_date' => '2024-09-01',
                'expected_graduation_date' => '2027-06-30',
                'high_school_name' => 'THPT Thăng Long',
                'high_school_graduation_year' => 2024,
                'entrance_exam_score' => 88.5,
                'status' => 'active',
            ],

            // Year 2 Students (Advanced)
            [
                'full_name' => 'Trịnh Văn Long',
                'email' => 'long.trinh@student.swinburne.edu.vn',
                'phone' => '0901234577',
                'date_of_birth' => '2004-09-15',
                'gender' => 'male',
                'nationality' => 'Vietnamese',
                'national_id' => '123456789022',
                'address' => '852 Đường Nguyễn Văn Cừ, Quận 5, TP.HCM',
                'campus_code' => 'HCM',
                'program_code' => 'IT',
                'admission_date' => '2023-09-01',
                'expected_graduation_date' => '2026-06-30',
                'high_school_name' => 'THPT Nguyễn Thị Minh Khai',
                'high_school_graduation_year' => 2023,
                'entrance_exam_score' => 92.0,
                'status' => 'active',
            ],
            [
                'full_name' => 'Đinh Thị Mai',
                'email' => 'mai.dinh@student.swinburne.edu.vn',
                'phone' => '0901234578',
                'date_of_birth' => '2004-11-08',
                'gender' => 'female',
                'nationality' => 'Vietnamese',
                'national_id' => '123456789023',
                'address' => '963 Đường Điện Biên Phủ, Quận Bình Thạnh, TP.HCM',
                'campus_code' => 'HCM',
                'program_code' => 'IT',
                'admission_date' => '2023-09-01',
                'expected_graduation_date' => '2026-06-30',
                'high_school_name' => 'THPT Lê Hồng Phong',
                'high_school_graduation_year' => 2023,
                'entrance_exam_score' => 89.5,
                'status' => 'active',
            ],
            [
                'full_name' => 'Phan Văn Nam',
                'email' => 'nam.phan@student.swinburne.edu.vn',
                'phone' => '0901234579',
                'date_of_birth' => '2004-03-20',
                'gender' => 'male',
                'nationality' => 'Vietnamese',
                'national_id' => '123456789024',
                'address' => '159 Đường Cách Mạng Tháng 8, Quận 10, TP.HCM',
                'campus_code' => 'HCM',
                'program_code' => 'IT',
                'admission_date' => '2023-09-01',
                'expected_graduation_date' => '2026-06-30',
                'high_school_name' => 'THPT Marie Curie',
                'high_school_graduation_year' => 2023,
                'entrance_exam_score' => 87.0,
                'status' => 'active',
            ],
            [
                'full_name' => 'Võ Thị Oanh',
                'email' => 'oanh.vo@student.swinburne.edu.vn',
                'phone' => '0901234580',
                'date_of_birth' => '2004-07-03',
                'gender' => 'female',
                'nationality' => 'Vietnamese',
                'national_id' => '123456789025',
                'address' => '357 Đường Nguyễn Thị Minh Khai, Quận 1, TP.HCM',
                'campus_code' => 'HCM',
                'program_code' => 'IT',
                'admission_date' => '2023-09-01',
                'expected_graduation_date' => '2026-06-30',
                'high_school_name' => 'THPT Nguyễn Thị Diệu',
                'high_school_graduation_year' => 2023,
                'entrance_exam_score' => 90.5,
                'status' => 'active',
            ],
            [
                'full_name' => 'Hồ Văn Phúc',
                'email' => 'phuc.ho@student.swinburne.edu.vn',
                'phone' => '0901234581',
                'date_of_birth' => '2004-01-17',
                'gender' => 'male',
                'nationality' => 'Vietnamese',
                'national_id' => '123456789026',
                'address' => '468 Đường Võ Văn Tần, Quận 3, TP.HCM',
                'campus_code' => 'HCM',
                'program_code' => 'IT',
                'admission_date' => '2023-09-01',
                'expected_graduation_date' => '2026-06-30',
                'high_school_name' => 'THPT Trần Đại Nghĩa',
                'high_school_graduation_year' => 2023,
                'entrance_exam_score' => 85.5,
                'status' => 'active',
            ],

            // Year 3 Students (Final Year)
            [
                'full_name' => 'Đỗ Thị Quỳnh',
                'email' => 'quynh.do@student.swinburne.edu.vn',
                'phone' => '0901234582',
                'date_of_birth' => '2003-05-25',
                'gender' => 'female',
                'nationality' => 'Vietnamese',
                'national_id' => '123456789027',
                'address' => '579 Đường Giải Phóng, Quận Hai Bà Trưng, Hà Nội',
                'campus_code' => 'HN',
                'program_code' => 'IT',
                'admission_date' => '2022-09-01',
                'expected_graduation_date' => '2025-06-30',
                'high_school_name' => 'THPT Chu Văn An',
                'high_school_graduation_year' => 2022,
                'entrance_exam_score' => 93.0,
                'status' => 'active',
            ],
            [
                'full_name' => 'Tạ Văn Rồng',
                'email' => 'rong.ta@student.swinburne.edu.vn',
                'phone' => '0901234583',
                'date_of_birth' => '2003-10-12',
                'gender' => 'male',
                'nationality' => 'Vietnamese',
                'national_id' => '123456789028',
                'address' => '680 Đường Láng, Quận Đống Đa, Hà Nội',
                'campus_code' => 'HN',
                'program_code' => 'IT',
                'admission_date' => '2022-09-01',
                'expected_graduation_date' => '2025-06-30',
                'high_school_name' => 'THPT Kim Liên',
                'high_school_graduation_year' => 2022,
                'entrance_exam_score' => 88.0,
                'status' => 'active',
            ],
            [
                'full_name' => 'Lưu Thị Sương',
                'email' => 'suong.luu@student.swinburne.edu.vn',
                'phone' => '0901234584',
                'date_of_birth' => '2003-04-08',
                'gender' => 'female',
                'nationality' => 'Vietnamese',
                'national_id' => '123456789029',
                'address' => '791 Đường Cầu Giấy, Quận Cầu Giấy, Hà Nội',
                'campus_code' => 'HN',
                'program_code' => 'IT',
                'admission_date' => '2022-09-01',
                'expected_graduation_date' => '2025-06-30',
                'high_school_name' => 'THPT Amsterdam',
                'high_school_graduation_year' => 2022,
                'entrance_exam_score' => 91.0,
                'status' => 'active',
            ],
            [
                'full_name' => 'Cao Văn Tùng',
                'email' => 'tung.cao@student.swinburne.edu.vn',
                'phone' => '0901234585',
                'date_of_birth' => '2003-08-22',
                'gender' => 'male',
                'nationality' => 'Vietnamese',
                'national_id' => '123456789030',
                'address' => '802 Đường Nguyễn Trãi, Quận Thanh Xuân, Hà Nội',
                'campus_code' => 'HN',
                'program_code' => 'IT',
                'admission_date' => '2022-09-01',
                'expected_graduation_date' => '2025-06-30',
                'high_school_name' => 'THPT Nguyễn Gia Thiều',
                'high_school_graduation_year' => 2022,
                'entrance_exam_score' => 86.5,
                'status' => 'active',
            ],
            [
                'full_name' => 'Nguyễn Thị Uyên',
                'email' => 'uyen.nguyen@student.swinburne.edu.vn',
                'phone' => '0901234586',
                'date_of_birth' => '2003-12-15',
                'gender' => 'female',
                'nationality' => 'Vietnamese',
                'national_id' => '123456789031',
                'address' => '913 Đường Lê Duẩn, Quận Ba Đình, Hà Nội',
                'campus_code' => 'HN',
                'program_code' => 'IT',
                'admission_date' => '2022-09-01',
                'expected_graduation_date' => '2025-06-30',
                'high_school_name' => 'THPT Thăng Long',
                'high_school_graduation_year' => 2022,
                'entrance_exam_score' => 89.5,
                'status' => 'active',
            ],
        ];

        foreach ($students as $studentData) {
            $campus = $campuses->where('code', $studentData['campus_code'])->first();
            $program = $programs->where('code', $studentData['program_code'])->first();
            $curriculumVersion = $curriculumVersions->where('program_id', $program->id)->first();

            if (!$campus || !$program || !$curriculumVersion) {
                $this->command->warn("Skipping student {$studentData['full_name']} - missing campus, program, or curriculum version");
                continue;
            }

            // Generate student ID
            $studentId = $this->generateStudentId($campus->code, 2024);

            $student = Student::create([
                'student_id' => $studentId,
                'full_name' => $studentData['full_name'],
                'email' => $studentData['email'],
                'phone' => $studentData['phone'],
                'password' => Hash::make('123456'), // Default password
                'oauth_provider' => 'manual',
                'date_of_birth' => $studentData['date_of_birth'],
                'gender' => $studentData['gender'],
                'nationality' => $studentData['nationality'],
                'national_id' => $studentData['national_id'],
                'address' => $studentData['address'],
                'campus_id' => $campus->id,
                'program_id' => $program->id,
                'curriculum_version_id' => $curriculumVersion->id,
                'admission_date' => $studentData['admission_date'],
                'expected_graduation_date' => $studentData['expected_graduation_date'],
                'high_school_name' => $studentData['high_school_name'],
                'high_school_graduation_year' => $studentData['high_school_graduation_year'],
                'entrance_exam_score' => $studentData['entrance_exam_score'],
                'status' => $studentData['status'],
                'email_verified_at' => now(),
            ]);

            // Create student wallet
            $this->createStudentWallet($student);

            $this->command->info("👤 Created student: {$student->full_name} ({$student->student_id}) at {$campus->name}");
        }

        $this->command->info('🎉 Created ' . count($students) . ' students across ' . $campuses->count() . ' campuses');
    }

    private function generateStudentId(string $campusCode, int $year): string
    {
        $lastStudent = Student::where('student_id', 'like', $campusCode . $year . '%')
            ->orderBy('student_id', 'desc')
            ->first();

        if ($lastStudent) {
            $lastNumber = (int) substr($lastStudent->student_id, -3);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $campusCode . $year . str_pad((string) $newNumber, 3, '0', STR_PAD_LEFT);
    }

    private function createStudentWallet(Student $student): void
    {
        StudentWallet::create([
            'student_id' => $student->id,
            'balance' => rand(0, 500), // Random initial balance
        ]);
    }
}
